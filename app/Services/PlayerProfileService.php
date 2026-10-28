<?php

namespace App\Services;

use PDO;
use PDOException;
use InvalidArgumentException;

/** Private profile only. The caller supplies the authenticated session ID. */
class PlayerProfileService
{
    private PDO $conn;

    public function __construct(private PdoDatabaseService $db, private ?AvatarStorage $avatars = null)
    {
        $this->conn = $db->getConnection();
    }

    public function find(int $userId): ?array
    {
        $stmt = $this->conn->prepare('SELECT id, username, name, email, created_at FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) return null;
        $stmt = $this->conn->prepare('SELECT * FROM user_profiles WHERE user_id = ?');
        $stmt->execute([$userId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $user['bio'] = $profile['bio'] ?? '';
        $avatar = (string)($profile['avatar'] ?? '');
        $user['avatar_url'] = ($this->avatars ??= new AvatarStorage())->url($userId, $avatar)
            ?? (preg_match('~^Images/[a-zA-Z0-9_/-]+\.(?:png|jpe?g|webp)$~D', $avatar)
            && is_file(dirname(__DIR__, 2) . '/public/' . $avatar)
            ? '/' . $avatar : '/Images/CupSao.png');
        return $user;
    }

    public static function validate(array $input): array
    {
        $values = [];
        foreach (['name' => 100, 'bio' => 500] as $field => $max) {
            $value = $input[$field] ?? '';
            if (!is_string($value) || !preg_match('//u', $value)) {
                throw new InvalidArgumentException('Tên hiển thị và giới thiệu phải là văn bản UTF-8 hợp lệ.');
            }
            $value = trim($value);
            $length = preg_match_all('/./us', $value);
            if ($length > $max || ($field === 'name' && $length === 0)) {
                throw new InvalidArgumentException('Tên hiển thị cần 1–100 ký tự; giới thiệu tối đa 500 ký tự.');
            }
            if (preg_match($field === 'name' ? '/[\x00-\x1F\x7F]/' : '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
                throw new InvalidArgumentException('Nội dung chứa ký tự điều khiển không hợp lệ.');
            }
            $values[$field] = $value;
        }
        return $values;
    }

    private function hasBioColumn(): bool
    {
        if ($this->db->getDriverName() === 'sqlite') {
            foreach ($this->conn->query('PRAGMA table_info(user_profiles)')->fetchAll(PDO::FETCH_ASSOC) as $column) {
                if ($column['name'] === 'bio') return true;
            }
            return false;
        }
        $stmt = $this->conn->prepare('SHOW COLUMNS FROM user_profiles LIKE ?');
        $stmt->execute(['bio']);
        return (bool)$stmt->fetch();
    }

    /** Only called on a valid save, never during read-only profile requests. */
    public function ensureBioColumn(): void
    {
        if ($this->hasBioColumn()) return;
        try {
            $this->conn->exec('ALTER TABLE user_profiles ADD COLUMN bio TEXT NULL');
        } catch (PDOException $e) {
            // Another request may have added it after our metadata read.
            if (!$this->hasBioColumn()) throw $e;
        }
    }

    public function update(int $userId, array $input, ?array $upload = null): array
    {
        $values = self::validate($input);
        $avatars = $this->avatars ??= new AvatarStorage();
        $extension = $upload === null ? null : $avatars->validateUpload($upload);
        if (!$this->find($userId)) throw new InvalidArgumentException('Tài khoản không còn tồn tại.');
        $this->ensureBioColumn();
        $newAvatar = null;
        $oldAvatar = null;
        $this->conn->beginTransaction();
        try {
            // Lock the owner row on MySQL so concurrent saves cannot create two profiles.
            $lock = $this->conn->prepare('SELECT id FROM users WHERE id = ?' . ($this->db->getDriverName() === 'mysql' ? ' FOR UPDATE' : ''));
            $lock->execute([$userId]);
            if (!$lock->fetchColumn()) throw new InvalidArgumentException('Tài khoản không còn tồn tại.');
            $this->conn->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$values['name'], $userId]);
            $stmt = $this->conn->prepare('SELECT avatar FROM user_profiles WHERE user_id = ?');
            $stmt->execute([$userId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            $oldAvatar = $profile['avatar'] ?? null;
            if ($upload !== null) $newAvatar = $avatars->store($userId, $upload, $extension);
            if ($profile) {
                $sql = 'UPDATE user_profiles SET bio = ?' . ($newAvatar !== null ? ', avatar = ?' : '') . ' WHERE user_id = ?';
                $params = [$values['bio']];
                if ($newAvatar !== null) $params[] = $newAvatar;
                $params[] = $userId;
                $this->conn->prepare($sql)->execute($params);
            } else {
                $sql = $newAvatar !== null ? 'INSERT INTO user_profiles (user_id, bio, avatar) VALUES (?, ?, ?)'
                    : 'INSERT INTO user_profiles (user_id, bio) VALUES (?, ?)';
                $params = [$userId, $values['bio']];
                if ($newAvatar !== null) $params[] = $newAvatar;
                $this->conn->prepare($sql)->execute($params);
            }
            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            if ($newAvatar !== null) $avatars->deleteOwned($userId, $newAvatar);
            throw $e;
        }
        // The new image and DB reference are committed before touching the old file.
        if ($newAvatar !== null) $avatars->deleteOwned($userId, $oldAvatar);
        return $values;
    }

    private static function score(array $row, bool $pvp): array
    {
        // PvP history has no immutable scoring version/nullable quality field.
        // Room-player boards can be deleted by leaveRoom or reset by startMatch;
        // they cannot establish the meaning of a historical integer (including 0).
        if ($pvp) return [($row['score'] ?? '—') . ' (điểm lưu, chưa rõ thang điểm)', null];
        $json = json_decode((string)($row['telemetry_nosql'] ?? ''), true);
        $meta = is_array($json) ? $json : [];
        $current = ($meta['scoring_version'] ?? '') === PipeScoringService::VERSION;
        $quality = $meta['quality_score'] ?? null;
        if ($current) {
            $quality = is_int($quality) && $quality >= 0 && $quality <= 100 ? $quality : null;
            return [$quality === null ? 'Chưa xác định' : $quality . '/100', $quality];
        }
        // No certificate/version: stored legacy score cannot be compared with new quality.
        return [($row['score_earned'] ?? '—') . ' (điểm cũ)', null];
    }

    public function statistics(int $userId, bool $pvp): ?array
    {
        try {
            $sql = $pvp
                ? 'SELECT score, final_rank FROM pvp_match_history WHERE user_id = ?'
                : "SELECT score_earned, result, telemetry_nosql FROM game_sessions WHERE user_id = ? AND game_mode = 'player'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$userId]);
            $stats = ['games' => 0, 'wins' => 0, 'win_rate' => null, 'best' => null];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stats['games']++;
                // Rank is authoritative for PvP wins. Old rows may have received
                // win_status='loss' when that column was added later.
                if ($pvp ? (int)$row['final_rank'] === 1 : ($row['result'] ?? '') === 'win') $stats['wins']++;
                [, $quality] = self::score($row, $pvp);
                if ($quality !== null) $stats['best'] = max($stats['best'] ?? 0, $quality);
            }
            if ($stats['games']) $stats['win_rate'] = round(100 * $stats['wins'] / $stats['games'], 1);
            return $stats;
        } catch (PDOException $e) {
            return null; // Missing PvP tables are a supported deployment state.
        }
    }

    public function history(int $userId, bool $pvp, int $page): array
    {
        try {
            $table = $pvp ? 'pvp_match_history' : 'game_sessions';
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = ?");
            $stmt->execute([$userId]);
            $total = (int)$stmt->fetchColumn();
            $pages = max(1, (int)ceil($total / 10));
            $page = max(1, min($page, $pages));
            if ($pvp) {
                $rows = $this->db->getUserPvpMatchHistory($userId, 10, ($page - 1) * 10);
            } else {
                $stmt = $this->conn->prepare('SELECT played_at, game_mode, result, score_earned, map_size, difficulty, duration_ms, telemetry_nosql
                    FROM game_sessions WHERE user_id = ? ORDER BY played_at DESC, id DESC LIMIT ? OFFSET ?');
                foreach ([$userId, 10, ($page - 1) * 10] as $i => $value) $stmt->bindValue($i + 1, $value, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            // Whitelist output: no tokens, full telemetry, boards, seeds or opponent data.
            $items = [];
            foreach ($rows as $row) {
                [$score] = self::score($row, $pvp);
                $items[] = [
                    'time' => $row[$pvp ? 'created_at' : 'played_at'] ?? null,
                    'mode' => $pvp ? 'PvP' : (($row['game_mode'] ?? '') === 'ai' ? 'AI' : 'Chơi đơn'),
                    'result' => $pvp && (int)$row['final_rank'] === 1 ? 'win' : ($row[$pvp ? 'win_status' : 'result'] ?? null),
                    'score' => $score, 'map_size' => $row['map_size'] ?? null,
                    'difficulty' => $row['difficulty'] ?? null, 'duration_ms' => $row['duration_ms'] ?? null,
                ];
            }
            return compact('items', 'total', 'page', 'pages') + ['available' => true];
        } catch (PDOException $e) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'available' => false];
        }
    }
}
