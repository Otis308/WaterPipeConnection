<?php

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

/** Match-scoped plain text chat. No authentication fallback or broadcast dependency. */
class PvpChatService
{
    public function __construct(private PDO $conn) {}

    // Real runtime DDL, deliberately outside game/send transactions (MySQL implicit commit).
    // Only chat tables are created; existing game tables/data are never reset or imported.
    public function ensureSchema(): void
    {
        $mysql = $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $id = $mysql ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $this->conn->exec("CREATE TABLE IF NOT EXISTS pvp_chat_matches (
            id VARCHAR(32) PRIMARY KEY, room_id BIGINT NOT NULL, created_at_ms BIGINT NOT NULL
        )" . $suffix);
        $this->conn->exec("CREATE TABLE IF NOT EXISTS pvp_chat_members (
            match_id VARCHAR(32) NOT NULL, user_id BIGINT NOT NULL,
            PRIMARY KEY (match_id, user_id)
        )" . $suffix);
        $this->conn->exec("CREATE TABLE IF NOT EXISTS pvp_chat_messages (
            id $id, match_id VARCHAR(32) NOT NULL, sender_id BIGINT NOT NULL,
            client_message_id VARCHAR(80) NOT NULL, sender_name VARCHAR(255) NOT NULL,
            content TEXT NOT NULL, created_at_ms BIGINT NOT NULL,
            UNIQUE (match_id, sender_id, client_message_id)" .
            ($mysql ? ', INDEX pvp_chat_cursor (match_id, id), INDEX pvp_chat_rate (sender_id, created_at_ms)' : '') .
        ')' . $suffix);
        if (!$mysql) {
            $this->conn->exec('CREATE INDEX IF NOT EXISTS pvp_chat_cursor ON pvp_chat_messages (match_id, id)');
            $this->conn->exec('CREATE INDEX IF NOT EXISTS pvp_chat_rate ON pvp_chat_messages (sender_id, created_at_ms)');
        }
    }

    /** Called inside startMatch's existing transaction; every start gets a new identity. */
    public function createMatch(int $roomId, array $players): string
    {
        $id = bin2hex(random_bytes(16));
        $this->conn->prepare('INSERT INTO pvp_chat_matches (id, room_id, created_at_ms) VALUES (?, ?, ?)')
            ->execute([$id, $roomId, (int)floor(microtime(true) * 1000)]);
        $member = $this->conn->prepare('INSERT INTO pvp_chat_members (match_id, user_id) VALUES (?, ?)');
        foreach ($players as $player) $member->execute([$id, (int)$player['user_id']]);
        return $id;
    }

    private function memberRoom(int $userId, string $matchId): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $matchId)) throw new RuntimeException('Trận không hợp lệ.', 422);
        $stmt = $this->conn->prepare('SELECT r.* FROM pvp_chat_matches c
            INNER JOIN pvp_chat_members m ON m.match_id = c.id AND m.user_id = ?
            INNER JOIN pvp_rooms r ON r.id = c.room_id WHERE c.id = ?');
        $stmt->execute([$userId, $matchId]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$room) throw new RuntimeException('Bạn không có quyền truy cập chat trận này.', 403);
        return $room;
    }

    private function canSend(array $room, string $matchId, int $userId): bool
    {
        $map = json_decode($room['map_config_json'] ?? '{}', true);
        if (($map['chat_match_id'] ?? '') !== $matchId || $room['status'] !== 'in_progress'
            || time() >= (int)$room['started_at_ts'] + (int)$room['time_limit_seconds']) return false;
        $stmt = $this->conn->prepare('SELECT 1 FROM pvp_room_players WHERE room_id = ? AND user_id = ?');
        $stmt->execute([(int)$room['id'], $userId]);
        return (bool)$stmt->fetchColumn();
    }

    private function message(array $row): array
    {
        return ['id' => (int)$row['id'], 'sender_id' => (int)$row['sender_id'],
            'client_message_id' => $row['client_message_id'],
            'sender_name' => $row['sender_name'], 'content' => $row['content'],
            'created_at_ms' => (int)$row['created_at_ms']];
    }

    public function listMessages(int $userId, string $matchId, int $afterId): array
    {
        $room = $this->memberRoom($userId, $matchId);
        if ($afterId < 0) throw new RuntimeException('Cursor không hợp lệ.', 422);
        if ($afterId === 0) {
            $stmt = $this->conn->prepare('SELECT * FROM pvp_chat_messages WHERE match_id = ? ORDER BY id DESC LIMIT 50');
            $stmt->execute([$matchId]);
            $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
        } else {
            $stmt = $this->conn->prepare('SELECT * FROM pvp_chat_messages WHERE match_id = ? AND id > ? ORDER BY id ASC LIMIT 50');
            $stmt->execute([$matchId, $afterId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return ['messages' => array_map([$this, 'message'], $rows),
            'after_id' => $rows ? (int)end($rows)['id'] : $afterId,
            'has_more' => $afterId > 0 && count($rows) === 50,
            'can_send' => $this->canSend($room, $matchId, $userId), 'my_user_id' => $userId];
    }

    public function sendMessage(int $userId, string $matchId, mixed $content, mixed $clientId): array
    {
        if (!is_string($content) || strlen($content) > 8000 || !preg_match('//u', $content)) {
            throw new RuntimeException('Nội dung phải là văn bản Unicode.', 422);
        }
        $content = preg_replace('/^\s+|\s+$/u', '', str_replace(["\r\n", "\r"], "\n", $content));
        if ($content === '' || preg_match_all('/./us', $content) > 500) {
            throw new RuntimeException('Tin nhắn phải có từ 1 đến 500 ký tự.', 422);
        }
        if (!is_string($clientId) || !preg_match('/^[A-Za-z0-9_-]{16,80}$/D', $clientId)) {
            throw new RuntimeException('Mã tin nhắn không hợp lệ.', 422);
        }
        $room = $this->memberRoom($userId, $matchId);
        $mysql = $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $this->conn->beginTransaction();
        try {
            // Same room lock/order as scoring/finalization. SQLite takes its writer lock first.
            if (!$mysql) $this->conn->prepare('UPDATE pvp_rooms SET id = id WHERE id = ?')->execute([$room['id']]);
            $lock = $this->conn->prepare('SELECT * FROM pvp_rooms WHERE id = ?' . ($mysql ? ' FOR UPDATE' : ''));
            $lock->execute([$room['id']]);
            $room = $lock->fetch(PDO::FETCH_ASSOC);
            // Serialize rate checks for this user across different matches/tabs, too.
            $user = $this->conn->prepare('SELECT username, name FROM users WHERE id = ?' . ($mysql ? ' FOR UPDATE' : ''));
            $user->execute([$userId]);
            $sender = $user->fetch(PDO::FETCH_ASSOC);
            if (!$sender) throw new RuntimeException('Vui lòng đăng nhập lại.', 401);
            $existing = $this->conn->prepare('SELECT * FROM pvp_chat_messages WHERE match_id = ? AND sender_id = ? AND client_message_id = ?');
            $existing->execute([$matchId, $userId, $clientId]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if ($row['content'] !== $content) throw new RuntimeException('Mã tin đã được dùng cho nội dung khác.', 409);
                $this->conn->commit();
                return $this->message($row); // Lost response may be retrieved after the match ends.
            }
            if (!$this->canSend($room, $matchId, $userId)) throw new RuntimeException('Trận đã kết thúc hoặc bạn đã rời trận. Chat chỉ đọc.', 409);
            $now = (int)floor(microtime(true) * 1000);
            $rate = $this->conn->prepare('SELECT COUNT(*) FROM pvp_chat_messages WHERE sender_id = ? AND created_at_ms > ?');
            $rate->execute([$userId, $now - 10000]);
            if ((int)$rate->fetchColumn() >= 5) throw new RuntimeException('Tối đa 5 tin trong 10 giây. Hãy chờ rồi gửi lại.', 429);
            $stmt = $this->conn->prepare('INSERT INTO pvp_chat_messages
                (match_id, sender_id, client_message_id, sender_name, content, created_at_ms) VALUES (?, ?, ?, ?, ?, ?)');
            $name = $sender['name'] ?: $sender['username'];
            $stmt->execute([$matchId, $userId, $clientId, $name, $content, $now]);
            $message = ['id' => (int)$this->conn->lastInsertId(), 'sender_id' => $userId,
                'client_message_id' => $clientId,
                'sender_name' => $name, 'content' => $content, 'created_at_ms' => $now];
            $this->conn->commit();
            return $message;
        } catch (Throwable $error) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $error;
        }
    }
}
