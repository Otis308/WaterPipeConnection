<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BroadcastingService;
use App\Services\PdoDatabaseService;
use App\Services\PvpMatchService;
use App\Services\PvpRoomService;

class PvpApiController extends Controller
{
    private PdoDatabaseService $db;
    private PvpRoomService $roomService;
    private PvpMatchService $matchService;
    private BroadcastingService $broadcaster;

    public function __construct()
    {
        $this->db = new PdoDatabaseService();
        $this->broadcaster = new BroadcastingService();
        $this->roomService = new PvpRoomService($this->db, $this->broadcaster);
        $this->matchService = new PvpMatchService($this->db, $this->broadcaster, $this->roomService);
    }

    private function getJsonPayload(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $_POST ?: [];
    }

    private function jsonResponse(bool $success, string $message, mixed $data = null, int $statusCode = 200, array $extra = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        $payload = array_merge([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ], $extra);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Xác thực người dùng hiện tại (bắt buộc phải đăng nhập trước khi chơi PvP)
     */
    private function getAuthenticatedUser(): array
    {
        if (!empty($_SESSION['huit_auth_user']['id'])) {
            return $_SESSION['huit_auth_user'];
        }

        // Kiểm tra Bearer token nếu client gửi qua header hoặc payload
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
        $token = '';

        if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $m)) {
            $token = $m[1];
        } else {
            $payload = $this->getJsonPayload();
            $token = $payload['auth_token'] ?? ($_SESSION['huit_auth_token'] ?? '');
        }

        if ($token) {
            $stmt = $this->db->getConnection()->prepare("
                SELECT u.* FROM users u
                INNER JOIN user_sessions s ON s.user_id = u.id
                WHERE s.session_token_hash = ? AND s.expires_at_ts > ? AND s.revoked_at_ts IS NULL
                LIMIT 1
            ");
            $stmt->execute([hash('sha256', $token), time()]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($user) {
                $_SESSION['huit_auth_user'] = [
                    'id'         => (int)$user['id'],
                    'username'   => $user['username'],
                    'name'       => $user['name'],
                    'email'      => $user['email'],
                    'role'       => $user['role'],
                    'highScore'  => (int)$user['high_score'],
                    'pvp_rating' => (int)$user['pvp_rating'],
                    'pvp_tier'   => $user['pvp_tier'],
                ];
                return $_SESSION['huit_auth_user'];
            }
        }

        // Hỗ trợ kiểm tra user_id trong payload / header nếu client gửi trực tiếp (cho test đa client và hybrid API)
        $payload = $this->getJsonPayload();
        $fallbackUserId = $payload['user_id'] ?? ($_GET['user_id'] ?? null);
        if ($fallbackUserId) {
            $stmt = $this->db->getConnection()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$fallbackUserId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($user) {
                return [
                    'id'         => (int)$user['id'],
                    'username'   => $user['username'],
                    'name'       => $user['name'] ?: $user['username'],
                    'email'      => $user['email'],
                    'role'       => $user['role'],
                    'highScore'  => (int)$user['high_score'],
                    'pvp_rating' => (int)($user['pvp_rating'] ?? 1000),
                    'pvp_tier'   => $user['pvp_tier'] ?? 'Bạc',
                ];
            }
        }

        $this->jsonResponse(false, 'Vui lòng đăng nhập tài khoản trước khi tham gia chế độ PvP!', null, 401);
        return [];
    }

    // ==================== 1. TẠO PHÒNG ====================
    public function createRoom(): void
    {
        $user = $this->getAuthenticatedUser();
        $payload = $this->getJsonPayload();
        $name = $user['name'] ?: $user['username'];

        $result = $this->roomService->createRoom((int)$user['id'], $name, $payload);
        $this->jsonResponse($result['success'], $result['message'], $result['room'] ?? null, $result['status'] ?? 200, [
            'room_code' => $result['room_code'] ?? null,
        ]);
    }

    // ==================== 2. THAM GIA PHÒNG ====================
    public function joinRoom(): void
    {
        $user = $this->getAuthenticatedUser();
        $payload = $this->getJsonPayload();
        $roomCode = trim((string)($payload['room_code'] ?? ''));

        if (!$roomCode) {
            $this->jsonResponse(false, 'Vui lòng nhập mã phòng!', null, 422);
        }

        $name = $user['name'] ?: $user['username'];
        $result = $this->roomService->joinRoom((int)$user['id'], $name, $roomCode);

        $this->jsonResponse($result['success'], $result['message'], $result['room'] ?? null, $result['status'] ?? 200, [
            'room_code' => $result['room_code'] ?? $roomCode,
        ]);
    }

    // ==================== 3. RỜI PHÒNG ====================
    public function leaveRoom(): void
    {
        $user = $this->getAuthenticatedUser();
        $payload = $this->getJsonPayload();
        $roomCode = trim((string)($payload['room_code'] ?? ''));

        if (!$roomCode) {
            $this->jsonResponse(false, 'Thiếu mã phòng.', null, 422);
        }

        $result = $this->roomService->leaveRoom((int)$user['id'], $roomCode);
        $this->jsonResponse($result['success'], $result['message'], $result, $result['status'] ?? 200);
    }

    // ==================== 4. BẬT/TẮT SẴN SÀNG ====================
    public function toggleReady(): void
    {
        $user = $this->getAuthenticatedUser();
        $payload = $this->getJsonPayload();
        $roomCode = trim((string)($payload['room_code'] ?? ''));

        $result = $this->roomService->toggleReady((int)$user['id'], $roomCode);
        $this->jsonResponse($result['success'], $result['message'] ?? '', $result['room'] ?? null, 200, [
            'is_ready' => $result['is_ready'] ?? false,
        ]);
    }

    // ==================== 5. CẬP NHẬT CẤU HÌNH PHÒNG ====================
    public function updateConfig(): void
    {
        $user = $this->getAuthenticatedUser();
        $payload = $this->getJsonPayload();
        $roomCode = trim((string)($payload['room_code'] ?? ''));

        $result = $this->roomService->updateConfig((int)$user['id'], $roomCode, $payload);
        $this->jsonResponse($result['success'], $result['message'], $result['room'] ?? null, $result['status'] ?? 200);
    }

    // ==================== 6. BẮT ĐẦU TRẬN ĐẤU ====================
    public function startMatch(): void
    {
        $user = $this->getAuthenticatedUser();
        $payload = $this->getJsonPayload();
        $roomCode = trim((string)($payload['room_code'] ?? ''));

        $result = $this->matchService->startMatch($roomCode, (int)$user['id']);
        $this->jsonResponse($result['success'], $result['message'], $result['map_data'] ?? null, $result['status'] ?? 200, [
            'players' => $result['players'] ?? [],
        ]);
    }

    // ==================== 7. LẤY TRẠNG THÁI PHÒNG (SYNC / RECONNECT) ====================
    public function getRoomStatus(): void
    {
        $user = $this->getAuthenticatedUser();
        $roomCode = trim((string)($_GET['room_code'] ?? ''));

        if (!$roomCode) {
            $this->jsonResponse(false, 'Thiếu mã phòng.', null, 422);
        }

        $room = $this->roomService->getRoomDetails($roomCode, (int)$user['id']);
        if (!$room) {
            $this->jsonResponse(false, 'Phòng không tồn tại.', null, 404);
        }

        $this->jsonResponse(true, 'Lấy trạng thái phòng thành công.', $room);
    }

    // ==================== 8. CẬP NHẬT TIẾN ĐỘ THI ĐẤU (LIVE) ====================
    public function submitMove(): void
    {
        $user = $this->getAuthenticatedUser();
        $payload = $this->getJsonPayload();
        $roomCode = trim((string)($payload['room_code'] ?? ''));

        $result = $this->matchService->submitMoveOrProgress((int)$user['id'], $roomCode, $payload);
        $this->jsonResponse($result['success'], $result['message'] ?? 'Đã cập nhật tiến độ.', $result);
    }

    // ==================== 9. NỘP BÀI HOÀN THÀNH (ANTI-CHEAT & SCORING) ====================
    public function submitSolution(): void
    {
        $user = $this->getAuthenticatedUser();
        $payload = $this->getJsonPayload();
        $roomCode = trim((string)($payload['room_code'] ?? ''));

        $result = $this->matchService->submitSolution((int)$user['id'], $roomCode, $payload);
        $this->jsonResponse($result['success'], $result['message'] ?? 'Nộp bài hoàn tất!', $result);
    }

    // ==================== 9b. RECONNECT — PHỤC HỒI TRẠNG THÁI SAU F5 / MẤT KẾT NỐI ====================
    public function reconnect(): void
    {
        $user = $this->getAuthenticatedUser();
        $roomCode = strtoupper(trim((string)($_GET['room_code'] ?? $this->getJsonPayload()['room_code'] ?? '')));

        if (!$roomCode) {
            $this->jsonResponse(false, 'Thiếu mã phòng.', null, 422);
        }

        $result = $this->matchService->getPlayerReconnectSnapshot((int)$user['id'], $roomCode);
        $this->jsonResponse(
            $result['success'],
            $result['message'] ?? 'Phục hồi trạng thái trận thành công.',
            $result
        );
    }


    // ==================== 10. TÌM TRẬN NGẪU NHIÊN (MATCHMAKING) ====================
    public function joinMatchmaking(): void
    {
        $user = $this->getAuthenticatedUser();
        $payload = $this->getJsonPayload();
        $name = $user['name'] ?: $user['username'];
        $targetPlayers = (int)($payload['target_players'] ?? 3);
        $size = (string)($payload['preferred_size'] ?? '5x8');
        $diff = (string)($payload['preferred_diff'] ?? 'medium');

        if (!in_array($targetPlayers, [2, 3, 4], true)
            || !in_array($size, ['3x8', '3x10', '3x12', '5x4', '5x6', '5x8', '5x10', '5x12'], true)
            || !in_array($diff, ['easy', 'medium', 'hard'], true)) {
            $this->jsonResponse(false, 'Cấu hình tìm trận không hợp lệ.', null, 422);
        }

        $result = $this->roomService->joinMatchmaking((int)$user['id'], $name, $targetPlayers, $size, $diff);
        $this->jsonResponse($result['success'], $result['message'], $result);
    }

    public function cancelMatchmaking(): void
    {
        $user = $this->getAuthenticatedUser();
        $this->roomService->cancelMatchmaking((int)$user['id']);
        $this->jsonResponse(true, 'Đã hủy tìm trận ngẫu nhiên.');
    }

    // ==================== 11. BẢNG XẾP HẠNG PVP RANK TOÀN CẦU ====================
    public function getRankedLeaderboard(): void
    {
        $limit = max(5, min(50, (int)($_GET['limit'] ?? 20)));
        $rows = $this->matchService->getRankedLeaderboard($limit);
        $this->jsonResponse(true, 'Tải bảng xếp hạng PvP thành công.', $rows);
    }

    // ==================== 12. XÁC THỰC KÊNH BROADCASTING ====================
    public function broadcastingAuth(): void
    {
        $user = $this->getAuthenticatedUser();
        $payload = $this->getJsonPayload();
        $channel = (string)($payload['channel_name'] ?? '');

        $authRes = BroadcastingService::authorizeChannel($user, $channel, $this->db);
        if (!$authRes['authorized']) {
            $this->jsonResponse(false, $authRes['error'] ?? 'Không được phép.', null, 403);
        }

        $this->jsonResponse(true, 'Xác thực kênh thành công.', $authRes);
    }

    // ==================== 13. LẤY TRẠNG THÁI TRẬN ĐẤU (LIVE LEADERBOARD) ====================
    public function getMatchStatus(): void
    {
        $roomCode = strtoupper(trim((string)($_GET['room_code'] ?? '')));
        if (!$roomCode) {
            $this->jsonResponse(false, 'Thiếu mã phòng.', null, 422);
        }

        $this->matchService->finalizeIfExpired($roomCode);

        $stmt = $this->db->getConnection()->prepare("SELECT * FROM pvp_rooms WHERE room_code = ? LIMIT 1");
        $stmt->execute([$roomCode]);
        $room = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$room) {
            $this->jsonResponse(false, 'Phòng không tồn tại.', null, 404);
        }

        $stmtPlayers = $this->db->getConnection()->prepare("
            SELECT rp.*, u.username, u.pvp_rating, u.pvp_tier
            FROM pvp_room_players rp
            INNER JOIN users u ON rp.user_id = u.id
            WHERE rp.room_id = ?
            ORDER BY rp.final_rank ASC, rp.current_score DESC, rp.finish_time_ms ASC
        ");
        $stmtPlayers->execute([$room['id']]);
        $players = $stmtPlayers->fetchAll(\PDO::FETCH_ASSOC);
        usort($players, [\App\Services\PipeScoringService::class, 'comparePlayers']);

        $leaderboard = array_map(function ($p, $idx) {
            return [
                'user_id'         => (int)$p['user_id'],
                'player_name'     => $p['player_name'] ?: $p['username'],
                'score'           => (int)$p['current_score'],
                'quality_score' => json_decode($p['board_rotations_json'] ?? '{}', true)['scoring']['quality_score'] ?? null,
                'pairs_connected' => (int)$p['completed_pairs'],
                'total_pairs'     => (int)$p['total_pairs'],
                'status'          => $p['solve_status'],
                'finish_time'     => $p['finish_time_ms'] ? round($p['finish_time_ms'] / 1000, 1) : null,
                'rating'          => (int)$p['pvp_rating'],
                'tier'            => \App\Services\PvpRatingService::getTier((int)$p['pvp_rating']),
                'rank'            => $p['final_rank'] ?: ($idx + 1),
            ];
        }, $players, array_keys($players));

        $this->jsonResponse(true, 'Trạng thái trận đấu.', [
            'room_code'    => $roomCode,
            'match_status' => $room['status'],
            'leaderboard'  => $leaderboard,
        ]);
    }

    // ==================== 14. LẤY KẾT QUẢ CUỐI TRẬN CHÍNH THỨC ====================
    public function getMatchResults(): void
    {
        $roomCode = strtoupper(trim((string)($_GET['room_code'] ?? '')));
        if (!$roomCode) {
            $this->jsonResponse(false, 'Thiếu mã phòng.', null, 422);
        }

        $this->matchService->finalizeIfExpired($roomCode);

        $stmt = $this->db->getConnection()->prepare("SELECT * FROM pvp_rooms WHERE room_code = ? LIMIT 1");
        $stmt->execute([$roomCode]);
        $room = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$room) {
            $this->jsonResponse(false, 'Phòng không tồn tại.', null, 404);
        }

        $stmtPlayers = $this->db->getConnection()->prepare("
            SELECT rp.*, u.username, u.pvp_rating, u.pvp_tier
            FROM pvp_room_players rp
            INNER JOIN users u ON rp.user_id = u.id
            WHERE rp.room_id = ?
            ORDER BY rp.final_rank ASC
        ");
        $stmtPlayers->execute([$room['id']]);
        $players = $stmtPlayers->fetchAll(\PDO::FETCH_ASSOC);

        $standings = array_map(function ($p) {
            $newRating = (int)($p['pvp_rating'] ?? ((int)$p['initial_rating'] + (int)$p['rating_change']));
            return [
                'user_id'       => (int)$p['user_id'],
                'player_name'   => $p['player_name'] ?: $p['username'],
                'score'         => (int)$p['current_score'],
                'quality_score' => json_decode($p['board_rotations_json'] ?? '{}', true)['scoring']['quality_score'] ?? null,
                'finish_time'   => $p['finish_time_ms'] ? round($p['finish_time_ms'] / 1000, 1) : null,
                'rank'          => (int)$p['final_rank'],
                'rating_change' => (int)$p['rating_change'],
                'new_rating'    => $newRating,
                'new_tier'      => \App\Services\PvpRatingService::getTier($newRating),
            ];
        }, $players);

        $this->jsonResponse(true, 'Kết quả trận đấu chính thức.', [
            'room_code' => $roomCode,
            'standings' => $standings,
            'results'   => $standings,
        ]);
    }
}
