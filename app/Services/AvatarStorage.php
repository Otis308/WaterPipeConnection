<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/** Real Laravel public disk; client filenames/paths are never used. */
class AvatarStorage
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    private static ?\Illuminate\Filesystem\FilesystemAdapter $disk = null;

    public function disk(): \Illuminate\Filesystem\FilesystemAdapter
    {
        return self::$disk ??= require dirname(__DIR__, 2) . '/bootstrap/avatar_storage.php';
    }

    public function ready(): bool
    {
        $root = dirname(__DIR__, 2);
        if (!filter_var(ini_get('file_uploads'), FILTER_VALIDATE_BOOLEAN)
            || !is_file($root . '/bootstrap/avatar-storage/vendor/autoload.php')
            || !class_exists('finfo') || !function_exists('imagecreatefromstring')
            || !is_dir($root . '/storage/app/public/avatars')
            || !is_writable($root . '/storage/app/public/avatars')
            || realpath($root . '/public/storage') !== realpath($root . '/storage/app/public')) {
            return false;
        }
        $gd = gd_info();
        if (empty($gd['JPEG Support']) || empty($gd['PNG Support']) || empty($gd['WebP Support'])) return false;
        try {
            return $this->disk() instanceof \Illuminate\Filesystem\FilesystemAdapter;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function owned(int $userId, ?string $path): bool
    {
        return $path !== null && preg_match('~^avatars/' . $userId . '/[a-f0-9]{40}\.(?:jpg|png|webp)$~D', $path) === 1;
    }

    public function url(int $userId, ?string $path): ?string
    {
        if (!$this->owned($userId, $path)) return null;
        try {
            return $this->disk()->exists($path) ? $this->disk()->url($path) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Validate before any file/DB write; a genuine HTTP upload is required. */
    public function validateUpload(array $upload): string
    {
        $error = $upload['error'] ?? null;
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException('Ảnh đại diện tối đa 2 MB.');
        }
        if ($error !== UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null)
            || !is_uploaded_file($upload['tmp_name'])) {
            throw new InvalidArgumentException('Tải ảnh chưa hoàn tất. Vui lòng chọn lại ảnh.');
        }
        $size = filesize($upload['tmp_name']);
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Ảnh đại diện cần có nội dung và tối đa 2 MB.');
        }
        if (!$this->ready()) throw new RuntimeException('Avatar Storage is not ready.');
        $types = ['image/jpeg' => ['jpg', IMAGETYPE_JPEG], 'image/png' => ['png', IMAGETYPE_PNG],
            'image/webp' => ['webp', IMAGETYPE_WEBP]];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $info = @getimagesize($upload['tmp_name']);
        if (!isset($types[$mime]) || !$info || $info[2] !== $types[$mime][1]
            || ($info['mime'] ?? '') !== $mime) {
            throw new InvalidArgumentException('Chỉ nhận ảnh JPEG, PNG hoặc WebP có nội dung hợp lệ.');
        }
        // Bound decoding memory before invoking GD on untrusted compressed data.
        if ($info[0] < 1 || $info[1] < 1 || $info[0] > 4096 || $info[1] > 4096 || $info[0] * $info[1] > 8388608) {
            throw new InvalidArgumentException('Ảnh tối đa 4096 px mỗi chiều và 8 megapixel.');
        }
        $decoded = @imagecreatefromstring(file_get_contents($upload['tmp_name']));
        if ($decoded === false) throw new InvalidArgumentException('Không đọc được nội dung ảnh. Vui lòng chọn ảnh khác.');
        imagedestroy($decoded);
        return $types[$mime][0];
    }

    public function store(int $userId, array $upload, string $extension): string
    {
        if (!in_array($extension, ['jpg', 'png', 'webp'], true)) throw new RuntimeException('Invalid image extension.');
        $path = 'avatars/' . $userId . '/' . bin2hex(random_bytes(20)) . '.' . $extension;
        $stream = fopen($upload['tmp_name'], 'rb');
        if ($stream === false) throw new RuntimeException('Cannot read uploaded image.');
        try {
            if (!$this->disk()->put($path, $stream, ['visibility' => 'public'])) {
                throw new RuntimeException('Avatar write failed.');
            }
        } catch (\Throwable $e) {
            $this->deleteOwned($userId, $path);
            throw $e;
        } finally {
            fclose($stream);
        }
        return $path;
    }

    /** Best effort cleanup must never undo a committed new avatar. */
    public function deleteOwned(int $userId, ?string $path): void
    {
        if (!$this->owned($userId, $path)) return;
        try {
            $this->disk()->delete($path);
        } catch (\Throwable $e) {
            error_log('Avatar: managed image cleanup failed.');
        }
    }
}
