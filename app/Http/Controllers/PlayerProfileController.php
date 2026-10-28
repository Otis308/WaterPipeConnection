<?php

namespace App\Http\Controllers;

use App\Services\PdoDatabaseService;
use App\Services\PlayerProfileService;
use App\Services\AvatarStorage;
use InvalidArgumentException;

class PlayerProfileController extends Controller
{
    public function __construct(private ?PdoDatabaseService $db = null) {}

    private function ownerId(): int
    {
        header('Cache-Control: private, no-store');
        $id = $_SESSION['huit_auth_user']['id'] ?? null;
        if (!is_scalar($id) || !ctype_digit((string)$id) || (int)$id < 1) {
            header('Location: /');
            exit;
        }
        return (int)$id;
    }

    private function service(): PlayerProfileService
    {
        return new PlayerProfileService($this->db ??= new PdoDatabaseService());
    }

    public function show(): void
    {
        $userId = $this->ownerId();
        $source = ($_GET['source'] ?? '') === 'pvp' ? 'pvp' : 'solo';
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1;
        $notice = $_SESSION['profile_notice'] ?? null;
        $old = $_SESSION['profile_old'] ?? [];
        unset($_SESSION['profile_notice'], $_SESSION['profile_old']);
        $profile = null;
        $loadError = false;
        $avatarReady = (new AvatarStorage())->ready();
        $soloStats = $pvpStats = null;
        $history = ['available' => false, 'items' => [], 'page' => 1, 'pages' => 1];
        try {
            $service = $this->service();
            $profile = $service->find($userId);
            if (!$profile) {
                unset($_SESSION['huit_auth_user'], $_SESSION['huit_auth_token']);
                header('Location: /');
                exit;
            }
            $soloStats = $service->statistics($userId, false);
            $pvpStats = $service->statistics($userId, true);
            $history = $service->history($userId, $source === 'pvp', $page);
        } catch (\Throwable $e) {
            http_response_code(503);
            $loadError = true;
        }
        view('game.profile', compact('profile', 'source', 'notice', 'old', 'loadError', 'soloStats', 'pvpStats', 'history', 'avatarReady'));
    }

    public function update(): void
    {
        $userId = $this->ownerId();
        $token = $_POST['_token'] ?? '';
        if (!is_string($token) || $token === '' || !hash_equals(csrf_token(), $token)) abort(419, 'Phiên biểu mẫu đã hết hạn.');
        try {
            if (array_key_exists('avatar', $_POST) || array_diff(array_keys($_FILES), ['avatar'])) {
                throw new InvalidArgumentException('Vui lòng gửi ảnh bằng ô chọn ảnh đại diện.');
            }
            $upload = $_FILES['avatar'] ?? null;
            if ($upload !== null && !is_array($upload)) throw new InvalidArgumentException('Dữ liệu ảnh không hợp lệ.');
            if (($upload['error'] ?? null) === UPLOAD_ERR_NO_FILE) $upload = null;
            $values = $this->service()->update($userId, $_POST, $upload);
            $_SESSION['huit_auth_user']['name'] = $values['name'];
            $_SESSION['profile_notice'] = ['success' => true, 'message' => 'Đã lưu hồ sơ của bạn.'];
            unset($_SESSION['profile_old']);
        } catch (InvalidArgumentException $e) {
            $_SESSION['profile_notice'] = ['success' => false, 'message' => $e->getMessage()];
            $this->rememberInput();
        } catch (\Throwable $e) {
            $_SESSION['profile_notice'] = ['success' => false, 'message' => 'Chưa lưu được hồ sơ. Vui lòng thử lại; thông tin hiện tại được giữ nguyên.'];
            $this->rememberInput();
        }
        header('Location: /profile', true, 303);
        exit;
    }

    private function rememberInput(): void
    {
        $_SESSION['profile_old'] = [];
        foreach (['name' => 100, 'bio' => 500] as $field => $limit) {
            $value = $_POST[$field] ?? '';
            // Only retain valid, bounded strings in the session.
            if (is_string($value) && preg_match('//u', $value) && preg_match_all('/./us', $value) <= $limit) {
                $_SESSION['profile_old'][$field] = $value;
            }
        }
    }
}
