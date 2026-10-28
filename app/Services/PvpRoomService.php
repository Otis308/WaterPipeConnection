<?php

namespace App\Services;

use App\Events\Pvp\RoomConfigurationUpdated;
use App\Events\Pvp\RoomCountdownStarted;
use App\Events\Pvp\RoomPlayerJoined;
use App\Events\Pvp\RoomPlayerLeft;
use App\Events\Pvp\RoomPlayerStatusUpdated;
use PDO;

class PvpRoomService
{
    private PdoDatabaseService $dbService;
    private PDO $conn;
    private BroadcastingService $broadcaster;

    public function __construct(?PdoDatabaseService $dbService = null, ?BroadcastingService $broadcaster = null)
    {
        $this->dbService = $dbService ?? new PdoDatabaseService();
        $this->conn = $this->dbService->getConnection();
        $this->broadcaster = $broadcaster ?? new BroadcastingService();
    }

    /**
     * Sinh mã phòng ngẫu nhiên duy nhất (VD: HUIT88, PIPE77)
     */
    public function generateUniqueRoomCode(): string
    {
        $attempts = 0;
        do {
            $code = 'HUIT' . str_pad((string)random_int(10, 99), 2, '0', STR_PAD_LEFT);
            $stmt = $this->conn->prepare("SELECT id FROM pvp_rooms WHERE room_code = ? LIMIT 1");
            $stmt->execute([$code]);
            $exists = $stmt->fetchColumn();
            $attempts++;
        } while ($exists && $attempts < 20);

        if ($exists) {
            $code = 'P' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        }

        return $code;
    }

    /**
     * Tạo phòng đấu PvP mới
     */
    public function createRoom(int $userId, string $playerName, array $options = []): array
    {
        // Kiểm tra xem người chơi có đang trong phòng nào đang chạy không
        $this->cleanupStalePlayerRooms($userId);

        $maxPlayers = max(2, min(4, (int)($options['max_players'] ?? 3))); // Mặc định 3 người
        $mapSize = in_array($options['map_size'] ?? '', ['3x8', '3x10', '3x12', '5x4', '5x6', '5x8', '5x10', '5x12'], true)
            ? $options['map_size']
            : '5x8';
        $difficulty = in_array(strtolower($options['difficulty'] ?? ''), ['easy', 'medium', 'hard'], true)
            ? strtolower($options['difficulty'])
            : 'medium';
        $numPairs = max(2, min(4, (int)($options['num_pairs'] ?? 2)));
        $roomType = ($options['room_type'] ?? '') === 'random' ? 'random' : 'custom';
        $timeLimit = max(60, min(600, (int)($options['time_limit_seconds'] ?? 300))); // 5 phút

        $roomCode = !empty($options['room_code']) ? strtoupper(trim($options['room_code'])) : $this->generateUniqueRoomCode();

        // Tạo bản ghi Room
        $stmtRoom = $this->conn->prepare("
            INSERT INTO pvp_rooms (
                room_code, host_user_id, room_type, max_players, current_players, 
                status, map_size, difficulty, num_pairs, time_limit_seconds
            ) VALUES (?, ?, ?, ?, 1, 'waiting', ?, ?, ?, ?)
        ");
        $stmtRoom->execute([$roomCode, $userId, $roomType, $maxPlayers, $mapSize, $difficulty, $numPairs, $timeLimit]);
        $roomId = (int)$this->conn->lastInsertId();

        // Thêm Host vào phòng ở Ghế 1
        $rating = $this->getUserRating($userId);
        $stmtPlayer = $this->conn->prepare("
            INSERT INTO pvp_room_players (
                room_id, user_id, player_name, seat_number, is_host, is_ready, 
                connection_status, initial_rating, total_pairs
            ) VALUES (?, ?, ?, 1, 1, 1, 'connected', ?, ?)
        ");
        $stmtPlayer->execute([$roomId, $userId, $playerName, $rating, $numPairs]);

        $roomData = $this->getRoomDetails($roomCode, $userId);

        return [
            'success'   => true,
            'message'   => 'Tạo phòng thành công!',
            'room_code' => $roomCode,
            'room'      => $roomData,
        ];
    }

    /**
     * Tham gia phòng đấu bằng mã phòng
     */
    public function joinRoom(int $userId, string $playerName, string $roomCode): array
    {
        $roomCode = strtoupper(trim($roomCode));
        $this->cleanupStalePlayerRooms($userId, $roomCode);

        // Lấy thông tin phòng
        $stmt = $this->conn->prepare("SELECT * FROM pvp_rooms WHERE room_code = ? LIMIT 1");
        $stmt->execute([$roomCode]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            return ['success' => false, 'status' => 404, 'message' => "Mã phòng [{$roomCode}] không tồn tại."];
        }

        if (in_array($room['status'], ['starting', 'in_progress', 'completed'], true)) {
            return ['success' => false, 'status' => 403, 'message' => 'Trận đấu đang diễn ra hoặc đã kết thúc, không thể tham gia!'];
        }

        if ($room['status'] === 'cancelled') {
            return ['success' => false, 'status' => 410, 'message' => 'Phòng này đã bị hủy.'];
        }

        // Lấy danh sách thành viên hiện tại
        $stmtMembers = $this->conn->prepare("SELECT * FROM pvp_room_players WHERE room_id = ? ORDER BY seat_number ASC");
        $stmtMembers->execute([$room['id']]);
        $members = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);

        // Kiểm tra xem đã trong phòng chưa
        foreach ($members as $m) {
            if ((int)$m['user_id'] === $userId) {
                // Đã ở trong phòng rồi, phục hồi trạng thái
                $this->conn->prepare("UPDATE pvp_room_players SET connection_status = 'connected' WHERE id = ?")
                           ->execute([$m['id']]);
                return [
                    'success'   => true,
                    'message'   => 'Bạn đã ở trong phòng này.',
                    'room_code' => $roomCode,
                    'room'      => $this->getRoomDetails($roomCode, $userId),
                ];
            }
        }

        // Kiểm tra số lượng người mục tiêu
        $currentCount = count($members);
        $maxPlayers = (int)$room['max_players'];

        if ($currentCount >= $maxPlayers) {
            return ['success' => false, 'status' => 400, 'message' => "Phòng đã đủ {$maxPlayers}/{$maxPlayers} người chơi!"];
        }

        // Tìm ghế trống đầu tiên (1..4)
        $occupiedSeats = array_column($members, 'seat_number');
        $seatNumber = 1;
        for ($s = 1; $s <= $maxPlayers; $s++) {
            if (!in_array($s, $occupiedSeats, false)) {
                $seatNumber = $s;
                break;
            }
        }

        $rating = $this->getUserRating($userId);
        $stmtAdd = $this->conn->prepare("
            INSERT INTO pvp_room_players (
                room_id, user_id, player_name, seat_number, is_host, is_ready, 
                connection_status, initial_rating, total_pairs
            ) VALUES (?, ?, ?, ?, 0, 0, 'connected', ?, ?)
        ");
        $stmtAdd->execute([$room['id'], $userId, $playerName, $seatNumber, $rating, (int)$room['num_pairs']]);

        $newCount = $currentCount + 1;
        $newStatus = ($newCount >= $maxPlayers) ? 'ready' : 'waiting';

        $this->conn->prepare("UPDATE pvp_rooms SET current_players = ?, status = ? WHERE id = ?")
                   ->execute([$newCount, $newStatus, $room['id']]);

        $roomData = $this->getRoomDetails($roomCode, $userId);

        // Phát sự kiện RoomPlayerJoined tới toàn bộ phòng
        $newPlayerInfo = [
            'user_id'     => $userId,
            'player_name' => $playerName,
            'seat_number' => $seatNumber,
            'is_host'     => false,
            'is_ready'    => false,
            'rating'      => $rating,
            'tier'        => PvpRatingService::getTier($rating),
        ];

        $this->broadcaster->broadcast(new RoomPlayerJoined(
            $roomCode,
            $newPlayerInfo,
            $newCount,
            $maxPlayers,
            $newStatus
        ));

        return [
            'success'   => true,
            'message'   => 'Tham gia phòng thành công!',
            'room_code' => $roomCode,
            'room'      => $roomData,
        ];
    }

    /**
     * Rời phòng đấu
     */
    public function leaveRoom(int $userId, string $roomCode): array
    {
        $roomCode = strtoupper(trim($roomCode));
        $stmt = $this->conn->prepare("SELECT * FROM pvp_rooms WHERE room_code = ? LIMIT 1");
        $stmt->execute([$roomCode]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            return ['success' => false, 'message' => 'Phòng không tồn tại.'];
        }

        // Tìm thành viên
        $stmtMember = $this->conn->prepare("SELECT * FROM pvp_room_players WHERE room_id = ? AND user_id = ? LIMIT 1");
        $stmtMember->execute([$room['id'], $userId]);
        $member = $stmtMember->fetch(PDO::FETCH_ASSOC);

        if (!$member) {
            return ['success' => true, 'message' => 'Bạn đã rời phòng.'];
        }

        $wasHost = (bool)$member['is_host'];
        $playerName = $member['player_name'];

        // Xóa thành viên khỏi phòng
        $this->conn->prepare("DELETE FROM pvp_room_players WHERE id = ?")->execute([$member['id']]);

        // Lấy danh sách thành viên còn lại
        $stmtRemain = $this->conn->prepare("SELECT * FROM pvp_room_players WHERE room_id = ? ORDER BY seat_number ASC");
        $stmtRemain->execute([$room['id']]);
        $remaining = $stmtRemain->fetchAll(PDO::FETCH_ASSOC);
        $newCount = count($remaining);

        $newHostId = null;

        if ($newCount === 0) {
            // Không còn ai trong phòng -> Đóng phòng
            $this->conn->prepare("UPDATE pvp_rooms SET status = 'cancelled', current_players = 0 WHERE id = ?")
                       ->execute([$room['id']]);
        } else {
            // Chuyển quyền chủ phòng cho người kế tiếp nếu Host rời
            if ($wasHost) {
                $newHost = $remaining[0];
                $newHostId = (int)$newHost['user_id'];
                $this->conn->prepare("UPDATE pvp_room_players SET is_host = 1 WHERE id = ?")
                           ->execute([$newHost['id']]);
                $this->conn->prepare("UPDATE pvp_rooms SET host_user_id = ? WHERE id = ?")
                           ->execute([$newHostId, $room['id']]);
            }

            // Cập nhật lại status về waiting vì vừa có người rời
            $this->conn->prepare("UPDATE pvp_rooms SET current_players = ?, status = 'waiting' WHERE id = ?")
                       ->execute([$newCount, $room['id']]);
        }

        // Broadcast sự kiện RoomPlayerLeft
        $this->broadcaster->broadcast(new RoomPlayerLeft(
            $roomCode,
            $userId,
            $playerName,
            $newCount,
            (int)$room['max_players'],
            $newHostId
        ));

        return [
            'success'   => true,
            'message'   => 'Đã rời phòng thành công.',
            'remaining' => $newCount,
        ];
    }

    /**
     * Bật/Tắt trạng thái Sẵn Sàng (Ready)
     */
    public function toggleReady(int $userId, string $roomCode): array
    {
        $roomCode = strtoupper(trim($roomCode));
        $stmt = $this->conn->prepare("
            SELECT rp.*, r.max_players, r.current_players, r.status as room_status
            FROM pvp_room_players rp
            INNER JOIN pvp_rooms r ON rp.room_id = r.id
            WHERE r.room_code = ? AND rp.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$roomCode, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['success' => false, 'message' => 'Không tìm thấy người chơi trong phòng.'];
        }

        $newReady = $row['is_ready'] ? 0 : 1;
        $this->conn->prepare("UPDATE pvp_room_players SET is_ready = ? WHERE id = ?")
                   ->execute([$newReady, $row['id']]);

        // Broadcast RoomPlayerStatusUpdated
        $this->broadcaster->broadcast(new RoomPlayerStatusUpdated(
            $roomCode,
            $userId,
            (bool)$newReady,
            $row['room_status']
        ));

        return [
            'success'  => true,
            'is_ready' => (bool)$newReady,
            'room'     => $this->getRoomDetails($roomCode, $userId),
        ];
    }

    /**
     * Chủ phòng cập nhật cấu hình bản đồ/độ khó (chỉ khi phòng đang ở trạng thái WAITING)
     */
    public function updateConfig(int $userId, string $roomCode, array $config): array
    {
        $roomCode = strtoupper(trim($roomCode));
        $stmt = $this->conn->prepare("SELECT * FROM pvp_rooms WHERE room_code = ? LIMIT 1");
        $stmt->execute([$roomCode]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            return ['success' => false, 'message' => 'Phòng không tồn tại.'];
        }

        if ((int)$room['host_user_id'] !== $userId) {
            return ['success' => false, 'status' => 403, 'message' => 'Chỉ chủ phòng mới có quyền thay đổi cấu hình!'];
        }

        if ($room['status'] !== 'waiting') {
            return ['success' => false, 'status' => 400, 'message' => 'Không thể đổi cấu hình khi trận đấu đang bắt đầu!'];
        }

        $mapSize = in_array($config['map_size'] ?? '', ['3x8', '3x10', '3x12', '5x4', '5x6', '5x8', '5x10', '5x12'], true)
            ? $config['map_size']
            : $room['map_size'];
        $difficulty = in_array(strtolower($config['difficulty'] ?? ''), ['easy', 'medium', 'hard'], true)
            ? strtolower($config['difficulty'])
            : $room['difficulty'];
        $numPairs = max(2, min(4, (int)($config['num_pairs'] ?? $room['num_pairs'])));
        $maxPlayers = max(2, min(4, (int)($config['max_players'] ?? $room['max_players'])));

        // Nếu giảm max_players nhỏ hơn số người hiện có thì từ chối
        if ($maxPlayers < (int)$room['current_players']) {
            return ['success' => false, 'message' => 'Số người mục tiêu không thể nhỏ hơn số người đang có trong phòng!'];
        }

        $this->conn->prepare("
            UPDATE pvp_rooms 
            SET map_size = ?, difficulty = ?, num_pairs = ?, max_players = ?
            WHERE id = ?
        ")->execute([$mapSize, $difficulty, $numPairs, $maxPlayers, $room['id']]);

        // Cập nhật total_pairs cho các players
        $this->conn->prepare("UPDATE pvp_room_players SET total_pairs = ? WHERE room_id = ?")
                   ->execute([$numPairs, $room['id']]);

        // Broadcast cấu hình mới
        $this->broadcaster->broadcast(new RoomConfigurationUpdated(
            $roomCode,
            $mapSize,
            $difficulty,
            $numPairs,
            $maxPlayers
        ));

        return [
            'success' => true,
            'message' => 'Đã cập nhật cấu hình phòng.',
            'room'    => $this->getRoomDetails($roomCode, $userId),
        ];
    }

    /**
     * Chi tiết snapshot phòng đấu đầy đủ
     */
    public function getRoomDetails(string $roomCode, ?int $forUserId = null): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM pvp_rooms WHERE room_code = ? LIMIT 1");
        $stmt->execute([$roomCode]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            return null;
        }

        $stmtPlayers = $this->conn->prepare("
            SELECT rp.*, u.pvp_rating, u.pvp_tier, u.high_score
            FROM pvp_room_players rp
            INNER JOIN users u ON rp.user_id = u.id
            WHERE rp.room_id = ?
            ORDER BY rp.seat_number ASC
        ");
        $stmtPlayers->execute([$room['id']]);
        $players = $stmtPlayers->fetchAll(PDO::FETCH_ASSOC);

        $formattedPlayers = [];
        $isUserInRoom = false;

        foreach ($players as $p) {
            $uId = (int)$p['user_id'];
            if ($forUserId && $uId === $forUserId) {
                $isUserInRoom = true;
            }
            $rating = (int)($p['pvp_rating'] ?? $p['initial_rating']);
            $formattedPlayers[] = [
                'user_id'           => $uId,
                'player_name'       => $p['player_name'],
                'seat_number'       => (int)$p['seat_number'],
                'is_host'           => (bool)$p['is_host'],
                'is_ready'          => (bool)$p['is_ready'],
                'connection_status' => $p['connection_status'],
                'current_score'     => (int)$p['current_score'],
                'quality_score'     => json_decode($p['board_rotations_json'] ?? '{}', true)['scoring']['quality_score'] ?? null,
                'completed_pairs'   => (int)$p['completed_pairs'],
                'total_pairs'       => (int)$p['total_pairs'],
                'solve_status'      => $p['solve_status'],
                'finish_time_ms'    => $p['finish_time_ms'] ? (int)$p['finish_time_ms'] : null,
                'rating'            => $rating,
                'tier'              => PvpRatingService::getTier($rating),
            ];
        }

        $mapData = null;
        if (!empty($room['map_config_json'])) {
            $mapData = PvpMapService::publicMapPayload(json_decode($room['map_config_json'], true) ?: []);
        }

        return [
            'id'                 => (int)$room['id'],
            'room_code'          => $room['room_code'],
            'host_user_id'       => (int)$room['host_user_id'],
            'room_type'          => $room['room_type'],
            'max_players'        => (int)$room['max_players'],
            'current_players'    => count($formattedPlayers),
            'status'             => $room['status'],
            'map_size'           => $room['map_size'],
            'difficulty'         => $room['difficulty'],
            'num_pairs'          => (int)$room['num_pairs'],
            'map_seed'           => $room['map_seed'],
            'time_limit_seconds' => (int)$room['time_limit_seconds'],
            'started_at_ts'      => $room['started_at_ts'] ? (int)$room['started_at_ts'] : null,
            'players'            => $formattedPlayers,
            'map_data'           => $mapData,
            'is_me_host'         => ($forUserId && (int)$room['host_user_id'] === $forUserId),
            'is_me_in_room'      => $isUserInRoom,
        ];
    }

    /**
     * Tham gia hàng đợi tìm trận ngẫu nhiên (Matchmaking Queue)
     */
    public function joinMatchmaking(int $userId, string $playerName, int $targetPlayers = 3, string $preferredSize = '5x8', string $preferredDiff = 'medium'): array
    {
        $targetPlayers = max(2, min(4, $targetPlayers)); // 2, 3, hoặc 4 người
        $this->cleanupStalePlayerRooms($userId);

        // Hủy hàng đợi cũ nếu có
        $this->cancelMatchmaking($userId);

        $rating = $this->getUserRating($userId);
        $now = time();

        $stmt = $this->conn->prepare("
            INSERT INTO pvp_matchmaking_queue (
                user_id, target_players, player_rating, preferred_size, preferred_diff, 
                status, queued_at_ts
            ) VALUES (?, ?, ?, ?, ?, 'waiting', ?)
        ");
        $stmt->execute([$userId, $targetPlayers, $rating, $preferredSize, $preferredDiff, $now]);

        // Kiểm tra xem đã đủ số người để tự động ghép phòng chưa!
        $matchedRoom = $this->checkAndMatchQueue($targetPlayers, $preferredSize, $preferredDiff);

        if ($matchedRoom) {
            return [
                'success'      => true,
                'status'       => 'matched',
                'message'      => 'Đã ghép trận thành công!',
                'room_code'    => $matchedRoom['room_code'],
                'room'         => $matchedRoom,
            ];
        }

        // Đếm số người đang chờ cùng target
        $stmtCount = $this->conn->prepare("
            SELECT COUNT(*) FROM pvp_matchmaking_queue 
            WHERE target_players = ? AND status = 'waiting'
        ");
        $stmtCount->execute([$targetPlayers]);
        $waitingCount = (int)$stmtCount->fetchColumn();

        return [
            'success'        => true,
            'status'         => 'waiting',
            'message'        => 'Đang tìm kiếm đối thủ...',
            'target_players' => $targetPlayers,
            'matched_count'  => $waitingCount,
        ];
    }

    /**
     * Hủy tìm trận ngẫu nhiên
     */
    public function cancelMatchmaking(int $userId): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM pvp_matchmaking_queue WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }

    /**
     * Kiểm tra hàng đợi và ghép trận khi đủ số người
     */
    public function checkAndMatchQueue(int $targetPlayers, string $preferredSize = '5x8', string $preferredDiff = 'medium'): ?array
    {
        // Lấy danh sách $targetPlayers người chơi đang chờ lâu nhất
        $stmt = $this->conn->prepare("
            SELECT q.*, u.name, u.username
            FROM pvp_matchmaking_queue q
            INNER JOIN users u ON q.user_id = u.id
            WHERE q.target_players = ? AND q.status = 'waiting'
            ORDER BY q.queued_at_ts ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $targetPlayers, PDO::PARAM_INT);
        $stmt->bindValue(2, $targetPlayers, PDO::PARAM_INT);
        $stmt->execute();
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Chỉ ghép khi đủ ĐÚNG số người mục tiêu (2, 3, hoặc 4 người)!
        if (count($candidates) < $targetPlayers) {
            return null;
        }

        // Tạo phòng cho nhóm
        $host = $candidates[0];
        $hostName = $host['name'] ?: $host['username'];

        $createRes = $this->createRoom((int)$host['user_id'], $hostName, [
            'max_players' => $targetPlayers,
            'map_size'    => $preferredSize,
            'difficulty'  => $preferredDiff,
            'num_pairs'   => 2,
            'room_type'   => 'random',
        ]);

        $roomCode = $createRes['room_code'];

        // Cho các người chơi còn lại tham gia
        for ($i = 1; $i < count($candidates); $i++) {
            $p = $candidates[$i];
            $pName = $p['name'] ?: $p['username'];
            $this->joinRoom((int)$p['user_id'], $pName, $roomCode);
        }

        // Xóa các user này khỏi hàng đợi
        $userIds = array_column($candidates, 'user_id');
        $inPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmtDel = $this->conn->prepare("DELETE FROM pvp_matchmaking_queue WHERE user_id IN ({$inPlaceholders})");
        $stmtDel->execute($userIds);

        return $this->getRoomDetails($roomCode);
    }

    private function getUserRating(int $userId): int
    {
        $stmt = $this->conn->prepare("SELECT pvp_rating FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null ? (int)$val : 1000;
    }

    private function cleanupStalePlayerRooms(int $userId, string $exceptRoomCode = ''): void
    {
        // Rời các phòng cũ đang ở trạng thái 'waiting'
        $stmt = $this->conn->prepare("
            SELECT r.room_code 
            FROM pvp_room_players rp
            INNER JOIN pvp_rooms r ON rp.room_id = r.id
            WHERE rp.user_id = ? AND r.status = 'waiting'
        ");
        $stmt->execute([$userId]);
        $oldRooms = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($oldRooms as $oldCode) {
            if ($oldCode !== $exceptRoomCode) {
                $this->leaveRoom($userId, $oldCode);
            }
        }
    }
}
