<?php

namespace App\Services;

use App\Events\ShouldBroadcast;

class BroadcastingService
{
    private string $broadcastUrl;

    public function __construct()
    {
        $url = getenv('WEBSOCKET_BROADCAST_URL');
        if (!$url) {
            // Tự động nhận diện môi trường Docker hay Host
            if (file_exists('/.dockerenv')) {
                // Kiểm tra xem host.docker.internal hay 172.21.0.1 hay 127.0.0.1
                $url = 'http://host.docker.internal:8080/broadcast';
            } else {
                $url = 'http://127.0.0.1:8080/broadcast';
            }
        }
        $this->broadcastUrl = $url;
    }

    /**
     * Phát sự kiện real-time tới WebSocket server
     */
    public function broadcast(ShouldBroadcast $event): bool
    {
        $channels = $event->broadcastOn();
        $eventName = $event->broadcastAs();
        $data = $event->broadcastWith();

        $payload = json_encode([
            'channels'  => $channels,
            'event'     => $eventName,
            'data'      => $data,
            'timestamp' => microtime(true),
        ], JSON_UNESCAPED_UNICODE);

        return $this->sendHttpBroadcast($payload);
    }

    private function sendHttpBroadcast(string $jsonPayload): bool
    {
        // Thử URL chính, nếu thất bại thử fallback sang 172.21.0.1:8080 hoặc 127.0.0.1:8080
        $targets = [$this->broadcastUrl];
        if (file_exists('/.dockerenv')) {
            $targets[] = 'http://172.21.0.1:8080/broadcast';
            $targets[] = 'http://172.17.0.1:8080/broadcast';
            $targets[] = 'http://localhost:8080/broadcast';
        } else {
            $targets[] = 'http://localhost:8080/broadcast';
        }

        foreach ($targets as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $jsonPayload,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($jsonPayload),
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT_MS     => 600, // Timeout cực nhanh 600ms không nghẽn request
                CURLOPT_CONNECTTIMEOUT_MS => 300,
            ]);
            $response = curl_exec($ch);
            $errNo = curl_errno($ch);
            curl_close($ch);

            if ($errNo === 0 && $response !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Xác thực quyền truy cập Private / Presence Channel
     */
    public static function authorizeChannel(array $user, string $channelName, PdoDatabaseService $db): array
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return ['authorized' => false, 'error' => 'Chưa đăng nhập.'];
        }

        // 1. Kênh phòng đấu: presence-room.{roomCode}
        if (str_starts_with($channelName, 'presence-room.')) {
            $roomCode = substr($channelName, strlen('presence-room.'));
            // Kiểm tra user có thuộc phòng này không
            $stmt = $db->getConnection()->prepare("
                SELECT rp.id, rp.is_host, rp.seat_number 
                FROM pvp_room_players rp
                INNER JOIN pvp_rooms r ON rp.room_id = r.id
                WHERE r.room_code = ? AND rp.user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$roomCode, $userId]);
            $member = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$member) {
                return ['authorized' => false, 'error' => 'Bạn không phải thành viên của phòng này.'];
            }

            return [
                'authorized' => true,
                'channel_data' => [
                    'user_id'   => $userId,
                    'user_info' => [
                        'id'          => $userId,
                        'name'        => $user['name'] ?? $user['username'],
                        'username'    => $user['username'],
                        'seat_number' => (int)$member['seat_number'],
                        'is_host'     => (bool)$member['is_host'],
                    ],
                ]
            ];
        }

        // 2. Kênh cá nhân: private-user.{userId}
        if (str_starts_with($channelName, 'private-user.')) {
            $targetUserId = (int)substr($channelName, strlen('private-user.'));
            if ($userId !== $targetUserId) {
                return ['authorized' => false, 'error' => 'Không có quyền truy cập kênh cá nhân này.'];
            }
            return ['authorized' => true];
        }

        return ['authorized' => true];
    }
}
