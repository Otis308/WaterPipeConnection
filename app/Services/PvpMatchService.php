<?php

namespace App\Services;

use App\Events\Pvp\MatchEnded;
use App\Events\Pvp\MatchLeaderboardUpdated;
use App\Events\Pvp\MatchResultsPublished;
use App\Events\Pvp\MatchStarted;
use App\Events\Pvp\PlayerCompleted;
use App\Events\Pvp\PlayerProgressUpdated;
use App\Events\Pvp\PlayerRankUpdated;
use App\Events\Pvp\PlayerScoreUpdated;
use App\Events\Pvp\RoomCountdownStarted;
use PDO;

class PvpMatchService
{
    private PdoDatabaseService $dbService;
    private PDO $conn;
    private BroadcastingService $broadcaster;
    private PvpRoomService $roomService;
    private PvpBoardStateService $boardStateService;

    public function __construct(
        ?PdoDatabaseService $dbService = null,
        ?BroadcastingService $broadcaster = null,
        ?PvpRoomService $roomService = null
    ) {
        $this->dbService         = $dbService ?? new PdoDatabaseService();
        $this->conn              = $this->dbService->getConnection();
        $this->broadcaster       = $broadcaster ?? new BroadcastingService();
        $this->roomService       = $roomService ?? new PvpRoomService($this->dbService, $this->broadcaster);
        $this->boardStateService = new PvpBoardStateService($this->dbService);
    }

    /**
     * Bắt đầu trận đấu PvP (chỉ khi ĐỦ SỐ NGƯỜI MỤC TIÊU và TẤT CẢ SẴN SÀNG)
     * AC2: Room Discipline — kiểm tra bên trong transaction để ngăn double-start
     */
    public function startMatch(string $roomCode, int $requesterUserId): array
    {
        $roomCode = strtoupper(trim($roomCode));

        $chat = new PvpChatService($this->conn);
        $chat->ensureSchema(); // DDL must not implicitly commit the match transaction.

        // Dùng transaction + FOR UPDATE để ngăn 2 request đồng thời bắt đầu cùng lúc
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare("SELECT * FROM pvp_rooms WHERE room_code = ?" . $this->forUpdate());
            $stmt->execute([$roomCode]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$room) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Phòng không tồn tại.'];
            }

            // Kiểm tra quyền chủ phòng
            if ((int)$room['host_user_id'] !== $requesterUserId) {
                $this->conn->rollBack();
                return ['success' => false, 'status' => 403, 'message' => 'Chỉ chủ phòng mới có quyền bắt đầu trận đấu!'];
            }

            // Idempotency: trận đang chạy rồi
            if ($room['status'] === 'in_progress') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Trận đấu đang diễn ra.'];
            }

            if ($room['status'] === 'completed' || $room['status'] === 'cancelled') {
                $this->conn->rollBack();
                return ['success' => false, 'status' => 400, 'message' => 'Phòng đã kết thúc hoặc bị hủy.'];
            }

            // Lấy danh sách thành viên hiện tại
            $stmtPlayers = $this->conn->prepare("SELECT * FROM pvp_room_players WHERE room_id = ? ORDER BY seat_number ASC");
            $stmtPlayers->execute([$room['id']]);
            $players = $stmtPlayers->fetchAll(PDO::FETCH_ASSOC);
            $currentCount = count($players);
            $maxPlayers   = (int)$room['max_players'];

            // AC2 — Kiểm tra đủ số người
            if ($currentCount < $maxPlayers) {
                $this->conn->rollBack();
                return [
                    'success' => false,
                    'status'  => 400,
                    'message' => "Chưa đủ người chơi! Phòng yêu cầu {$maxPlayers} người, hiện có {$currentCount}/{$maxPlayers} người.",
                ];
            }

            // AC2 — Kiểm tra tất cả khách (không phải host) đã sẵn sàng
            foreach ($players as $p) {
                if ((int)$p['user_id'] !== $requesterUserId && empty($p['is_ready'])) {
                    $pName = $p['player_name'] ?: ('Người chơi ' . $p['user_id']);
                    $this->conn->rollBack();
                    return [
                        'success' => false,
                        'status'  => 400,
                        'message' => "Chưa thể bắt đầu! '{$pName}' chưa bấm Sẵn Sàng.",
                    ];
                }
            }

            // 1. Sinh bản đồ duy nhất và deterministic cho trận đấu
            $seedKey = 'HUIT_PVP_' . $roomCode . '_' . time();
            $mapData = PvpMapService::generateMap(
                $room['map_size'],
                $room['difficulty'],
                (int)$room['num_pairs'],
                $seedKey
            );

            $mapData['_scoring'] = PipeScoringService::baseline($mapData);
            $mapData['chat_match_id'] = $chat->createMatch((int)$room['id'], $players);
            $now     = time();
            $mapJson = json_encode($mapData, JSON_UNESCAPED_UNICODE);

            // 2. Cập nhật phòng sang in_progress (atomic trong transaction)
            $this->conn->prepare("
                UPDATE pvp_rooms
                SET status = 'in_progress', map_seed = ?, map_config_json = ?, started_at_ts = ?
                WHERE id = ?
            ")->execute([$seedKey, $mapJson, $now, $room['id']]);

            // 3. Reset điểm và khởi tạo board_rotations_json cho từng người chơi
            foreach ($players as $p) {
                $this->conn->prepare("
                    UPDATE pvp_room_players
                    SET current_score = 0, completed_pairs = 0,
                        solve_status = 'playing', finish_time_ms = NULL, final_rank = NULL,
                        board_rotations_json = NULL, board_state_version = 0
                    WHERE id = ?
                ")->execute([$p['id']]);
            }

            foreach ($players as $p) {
                $this->boardStateService->initBoardForPlayer((int)$p['id'], $mapData);
            }
            $this->conn->commit();

            // 5. Chuẩn bị danh sách người chơi cho event
            $formattedPlayers = [];
            foreach ($players as $p) {
                $formattedPlayers[] = [
                    'user_id'         => (int)$p['user_id'],
                    'player_name'     => $p['player_name'],
                    'seat_number'     => (int)$p['seat_number'],
                    'current_score'   => 0,
                    'completed_pairs' => 0,
                    'total_pairs'     => (int)$room['num_pairs'],
                    'solve_status'    => 'playing',
                ];
            }

            // 6. Broadcast sự kiện bắt đầu
            $this->broadcaster->broadcast(new RoomCountdownStarted($roomCode, 3));
            $this->broadcaster->broadcast(new MatchStarted(
                $roomCode,
                $mapData,
                $formattedPlayers,
                (int)$room['time_limit_seconds'],
                $now
            ));

            return [
                'success'   => true,
                'message'   => 'Trận đấu đã chính thức bắt đầu!',
                'room_code' => $roomCode,
                'map_data'  => PvpMapService::publicMapPayload($mapData),
                'players'   => $formattedPlayers,
            ];
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => 'Lỗi bắt đầu trận: ' . $e->getMessage()];
        }
    }

    /** Ordered, idempotent actions and submissions share the room row lock.
     * Scores and completion flags from clients are never evaluation inputs. */
    public function submitMoveOrProgress(int $userId, string $roomCode, array $progressData): array
    {
        return $this->evaluatePlayer($userId, $roomCode, $progressData, false);
    }

    public function submitSolution(int $userId, string $roomCode, array $submission): array
    {
        return $this->evaluatePlayer($userId, $roomCode, $submission, true);
    }

    private function forUpdate(): string
    {
        return $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }

    private function evaluatePlayer(int $userId, string $roomCode, array $input, bool $submit): array
    {
        $roomCode = strtoupper(trim($roomCode));
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare('SELECT * FROM pvp_rooms WHERE room_code = ?' . $this->forUpdate());
            $stmt->execute([$roomCode]); $room = $stmt->fetch(PDO::FETCH_ASSOC);
            $player = $room ? $this->getRoomPlayer((int)$room['id'], $userId) : null;
            if (!$room || !$player) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Trận đấu không còn diễn ra hoặc bạn không thuộc phòng.'];
            }
            $state = PvpBoardStateService::decodeBoardState($player['board_rotations_json'] ?? null);
            $version = (int)$player['board_state_version'];
            if ($player['solve_status'] === 'completed') {
                $this->conn->rollBack();
                return ['success' => true, 'is_completed' => true, 'current_score' => (int)$player['current_score'],
                    'evaluation' => $state['scoring'] ?? null, 'board_state_version' => $version];
            }
            if ($room['status'] !== 'in_progress') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Trận đấu không còn diễn ra.'];
            }
            if (time() >= (int)$room['started_at_ts'] + (int)$room['time_limit_seconds']) {
                $this->conn->rollBack();
                $this->finalizeMatch($roomCode, 'time_out');
                return ['success' => false, 'match_ended' => true, 'message' => 'Đã hết giờ thi đấu.'];
            }
            if (!$state['rotations']) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Thiếu trạng thái bàn trên server.'];
            }
            $action = $input['action'] ?? '';
            $duplicate = false;
            if ($action !== '' || $submit) {
                $expected = $input['expected_version'] ?? null;
                $actionId = $input['action_id'] ?? '';
                $hash = hash('sha256', json_encode(array_intersect_key($input, array_flip(
                    ['action', 'row', 'col', 'degrees', 'rowA', 'colA', 'rowB', 'colB', 'expected_version']))));
                $last = $state['last_action'] ?? [];
                $duplicate = !$submit && is_int($expected) && is_string($actionId) && $actionId !== ''
                    && ($last['id'] ?? '') === $actionId && ($last['hash'] ?? '') === $hash && $version === $expected + 1;
                if (!is_int($expected) || (!$duplicate && $expected !== $version)) {
                    $this->conn->rollBack();
                    return ['success' => false, 'message' => 'Phiên bản bàn không khớp. Hãy tải lại bàn từ server.',
                        'board_state_version' => $version];
                }
                if (!$submit && (!is_string($actionId) || strlen($actionId) < 1 || strlen($actionId) > 100)) {
                    $this->conn->rollBack();
                    return ['success' => false, 'message' => 'Thiếu mã thao tác.'];
                }
            }
            $map = json_decode($room['map_config_json'], true) ?: [];
            $map['_scoring'] = PipeScoringService::baseline($map);
            if (!$duplicate && !$submit && $action !== '') {
                if ($action === 'rotate' && isset($input['row'], $input['col'])) {
                    $result = $this->boardStateService->applyRotation((int)$player['id'],
                        (int)$input['row'], (int)$input['col'], (int)($input['degrees'] ?? 90), $map);
                } elseif ($action === 'swap' && isset($input['rowA'], $input['colA'], $input['rowB'], $input['colB'])) {
                    $result = $this->boardStateService->applySwap((int)$player['id'], (int)$input['rowA'],
                        (int)$input['colA'], (int)$input['rowB'], (int)$input['colB'], $map);
                } else {
                    $result = ['success' => false, 'error' => 'Thao tác không hợp lệ.'];
                }
                if (!$result['success']) {
                    $this->conn->rollBack();
                    return ['success' => false, 'message' => $result['error']];
                }
                $state = $result['board_state']; $version = $result['board_state_version'];
                $state['last_action'] = ['id' => $actionId, 'hash' => $hash];
            }
            $evaluation = PvpBoardStateService::verifyBoardSolution($map, $state);
            $state['scoring'] = $evaluation;
            $done = $submit && $evaluation['is_all_completed'];
            $finish = $done ? max(1, (time() - (int)$room['started_at_ts']) * 1000) : null;
            $this->conn->prepare('UPDATE pvp_room_players SET board_rotations_json = ?, current_score = ?,
                completed_pairs = ?, solve_status = ?, finish_time_ms = ?, last_heartbeat_ts = ? WHERE id = ?')
                ->execute([json_encode($state), $evaluation['official_score'], $evaluation['completed_pairs'],
                    $done ? 'completed' : 'playing', $finish, time(), $player['id']]);
            $this->conn->prepare('UPDATE pvp_rooms SET map_config_json = ? WHERE id = ?')
                ->execute([json_encode($map), $room['id']]);
            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return ['success' => false, 'message' => 'Không thể cập nhật bàn: ' . $e->getMessage()];
        }
        $leaderboard = $this->broadcastLiveLeaderboard((int)$room['id'], $roomCode);
        if ($done) {
            $rank = 1;
            foreach ($leaderboard as $entry) if ($entry['user_id'] === $userId) $rank = $entry['rank'];
            $this->broadcaster->broadcast(new PlayerCompleted($roomCode, $userId, $player['player_name'],
                $evaluation['official_score'], $finish, $rank));
            $check = $this->conn->prepare("SELECT COUNT(*) FROM pvp_room_players WHERE room_id = ? AND solve_status != 'completed'");
            $check->execute([$room['id']]);
            if ((int)$check->fetchColumn() === 0) $this->finalizeMatch($roomCode, 'all_completed');
        }
        return ['success' => !$submit || $done, 'is_completed' => $done, 'evaluation' => $evaluation,
            'current_score' => $evaluation['official_score'], 'official_score' => $evaluation['official_score'],
            'board_state_version' => $version, 'completed_pairs' => $evaluation['completed_pairs'],
            'message' => $submit && !$done ? 'Cần nối đúng tất cả các cặp trước khi nộp bài.' : 'Đã ghi nhận bàn.',
            'leaderboard' => $leaderboard];
    }

    public function finalizeIfExpired(string $roomCode): void
    {
        $room = $this->getActiveRoom(strtoupper(trim($roomCode)));
        if ($room && time() >= (int)$room['started_at_ts'] + (int)$room['time_limit_seconds']) {
            $this->finalizeMatch($roomCode, 'time_out');
        }
    }

    /**
     * Kết thúc trận đấu — tính Elo pairwise, ghi lịch sử, broadcast kết quả
     * Dùng DB transaction + FOR UPDATE để tránh finalize 2 lần (P1 Concurrency)
     */
    public function finalizeMatch(string $roomCode, string $reason = 'time_out'): array
    {
        $roomCode = strtoupper(trim($roomCode));

        $this->conn->beginTransaction();
        try {
            // Lock phòng trước khi xử lý (ngăn 2 request finalizeMatch đồng thời)
            $stmt = $this->conn->prepare("SELECT * FROM pvp_rooms WHERE room_code = ?" . $this->forUpdate());
            $stmt->execute([$roomCode]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$room || $room['status'] === 'completed') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Trận đấu đã được chốt kết quả trước đó.'];
            }

            $now = time();

            // Lấy toàn bộ người chơi
            $stmtPlayers = $this->conn->prepare("
                SELECT rp.*, u.pvp_rating, u.pvp_tier, u.name as real_name
                FROM pvp_room_players rp
                INNER JOIN users u ON rp.user_id = u.id
                WHERE rp.room_id = ?
            ");
            $stmtPlayers->execute([$room['id']]);
            $players = $stmtPlayers->fetchAll(PDO::FETCH_ASSOC);

            if (empty($players)) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Không có người chơi trong phòng.'];
            }

            // At the deadline the last accepted server board is the submission.
            if ($reason === 'time_out') {
                $map = json_decode($room['map_config_json'], true) ?: [];
                foreach ($players as &$p) {
                    if ($p['solve_status'] === 'completed') continue;
                    $state = PvpBoardStateService::decodeBoardState($p['board_rotations_json'] ?? null);
                    $evaluation = PvpBoardStateService::verifyBoardSolution($map, $state);
                    $state['scoring'] = $evaluation;
                    $p['current_score'] = $evaluation['official_score'];
                    $p['completed_pairs'] = $evaluation['completed_pairs'];
                    $p['solve_status'] = $evaluation['is_all_completed'] ? 'completed' : 'playing';
                    $p['finish_time_ms'] = $evaluation['is_all_completed'] ? (int)$room['time_limit_seconds'] * 1000 : null;
                    $p['board_rotations_json'] = json_encode($state);
                    $this->conn->prepare('UPDATE pvp_room_players SET current_score = ?, completed_pairs = ?,
                        solve_status = ?, finish_time_ms = ?, board_rotations_json = ? WHERE id = ?')
                        ->execute([$p['current_score'], $p['completed_pairs'], $p['solve_status'],
                            $p['finish_time_ms'], $p['board_rotations_json'], $p['id']]);
                }
                unset($p);
            }
            usort($players, [PipeScoringService::class, 'comparePlayers']);

            // Gán rank 1..N
            $rankInputForElo = [];
            foreach ($players as $idx => &$p) {
                $rank          = $idx + 1;
                $p['final_rank'] = $rank;
                $rankInputForElo[] = [
                    'user_id' => (int)$p['user_id'],
                    'rating'  => (int)$p['pvp_rating'],
                    'rank'    => $rank,
                ];
            }
            unset($p);

            // Tính Elo pairwise (AC6: tổng delta = 0 đảm bảo bởi PvpRatingService)
            $eloResults = PvpRatingService::calculateMatchEloDeltas($rankInputForElo);

            $finalResults = [];
            $durationMs   = ($now - (int)($room['started_at_ts'] ?? $now)) * 1000;

            foreach ($players as $p) {
                $uId = (int)$p['user_id'];
                $elo = $eloResults[$uId] ?? [
                    'rating_before' => (int)$p['pvp_rating'],
                    'rating_after'  => (int)$p['pvp_rating'],
                    'rating_delta'  => 0,
                    'tier_name'     => $p['pvp_tier'] ?? 'Bạc',
                    'tier_color'    => '#94a3b8',
                    'tier_badge'    => '🥈',
                ];

                $delta     = $elo['rating_delta'];
                $newRating = $elo['rating_after'];
                $newTier   = $elo['tier_name'];
                $rank      = (int)$p['final_rank'];

                // Xác định win_status cho lịch sử
                $winStatus = match (true) {
                    $rank === 1 => 'win',
                    $reason === 'abandoned' => 'abandoned',
                    default => 'loss',
                };

                // Cập nhật pvp_room_players
                $this->conn->prepare("
                    UPDATE pvp_room_players
                    SET final_rank = ?, rating_change = ?
                    WHERE id = ?
                ")->execute([$rank, $delta, $p['id']]);

                // Cập nhật bảng users (Rating, Số trận, Thắng/Thua)
                $isWin  = ($rank === 1) ? 1 : 0;
                $isLoss = ($rank > 1)   ? 1 : 0;
                $this->conn->prepare("
                    UPDATE users
                    SET pvp_rating = ?, pvp_tier = ?,
                        pvp_matches = pvp_matches + 1,
                        pvp_wins = pvp_wins + ?,
                        pvp_losses = pvp_losses + ?
                    WHERE id = ?
                ")->execute([$newRating, $newTier, $isWin, $isLoss, $uId]);

                // Ghi pvp_match_history (bao gồm win_status và k_factor đầy đủ)
                $this->conn->prepare("
                    INSERT INTO pvp_match_history (
                        room_id, user_id, final_rank, score, duration_ms,
                        rating_before, rating_after, rating_delta,
                        win_status, k_factor
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $room['id'], $uId, $rank,
                    (int)$p['current_score'], $durationMs,
                    $elo['rating_before'], $newRating, $delta,
                    $winStatus, PvpRatingService::K_FACTOR,
                ]);

                $finalResults[] = [
                    'user_id'         => $uId,
                    'player_name'     => $p['player_name'],
                    'final_rank'      => $rank,
                    'score'           => (int)$p['current_score'],
                    'quality_score' => json_decode($p['board_rotations_json'] ?? '{}', true)['scoring']['quality_score'] ?? null,
                    'completed_pairs' => (int)$p['completed_pairs'],
                    'solve_status'    => $p['solve_status'],
                    'finish_time_ms'  => $p['finish_time_ms'] ? (int)$p['finish_time_ms'] : null,
                    'rating_before'   => $elo['rating_before'],
                    'rating_after'    => $newRating,
                    'rating_delta'    => $delta,
                    'tier_name'       => $newTier,
                    'tier_color'      => $elo['tier_color'] ?? '#94a3b8',
                    'tier_badge'      => $elo['tier_badge'] ?? '🥈',
                    'win_status'      => $winStatus,
                ];

                // Broadcast rank update riêng cho từng người
                $this->broadcaster->broadcast(new PlayerRankUpdated(
                    $uId,
                    $elo['rating_before'],
                    $newRating,
                    $delta,
                    $newTier,
                    $elo['tier_color'] ?? '#94a3b8',
                    $elo['tier_badge'] ?? '🥈'
                ));
            }

            // Đánh dấu phòng hoàn tất
            $this->conn->prepare("
                UPDATE pvp_rooms
                SET status = 'completed', ended_at_ts = ?
                WHERE id = ?
            ")->execute([$now, $room['id']]);

            $this->conn->commit();

            // Broadcast kết quả cuối cùng
            $this->broadcaster->broadcast(new MatchResultsPublished($roomCode, $finalResults));
            $this->broadcaster->broadcast(new MatchEnded($roomCode, $reason, $finalResults));

            return [
                'success' => true,
                'message' => 'Trận đấu đã kết thúc và chốt kết quả!',
                'results' => $finalResults,
            ];
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => 'Lỗi chốt kết quả trận: ' . $e->getMessage()];
        }
    }

    /**
     * Lấy snapshot đầy đủ cho một người chơi khi reconnect (AC5)
     * Bao gồm: map_data, board_rotations, điểm, leaderboard, đồng hồ
     */
    public function getPlayerReconnectSnapshot(int $userId, string $roomCode): array
    {
        $roomCode = strtoupper(trim($roomCode));

        $stmt = $this->conn->prepare("SELECT * FROM pvp_rooms WHERE room_code = ? LIMIT 1");
        $stmt->execute([$roomCode]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            return ['success' => false, 'message' => 'Phòng không tồn tại.'];
        }

        $player = $this->getRoomPlayer($room['id'], $userId);
        if (!$player) {
            return ['success' => false, 'message' => 'Bạn không thuộc phòng này.'];
        }

        // Cập nhật connection_status = 'connected' khi reconnect
        $this->conn->prepare("
            UPDATE pvp_room_players SET connection_status = 'connected', last_heartbeat_ts = ?
            WHERE id = ?
        ")->execute([time(), $player['id']]);

        // Lấy snapshot bàn cờ cá nhân từ boardStateService
        $boardSnapshot = $this->boardStateService->getPlayerBoardSnapshot($room['id'], $userId);

        // Lấy leaderboard hiện tại
        $leaderboard = $this->getCurrentLeaderboard($room['id']);

        // Thông tin phòng đầy đủ
        $roomDetails = $this->roomService->getRoomDetails($roomCode, $userId);

        return [
            'success'             => true,
            'room_status'         => $room['status'],
            'room_code'           => $roomCode,
            'player_snapshot'     => $boardSnapshot,
            'leaderboard'         => $leaderboard,
            'room'                => $roomDetails,
            'reconnected_at'      => time(),
        ];
    }

    /**
     * Broadcast bảng xếp hạng in-game live
     */
    public function broadcastLiveLeaderboard(int $roomId, string $roomCode): array
    {
        $leaderboard = $this->getCurrentLeaderboard($roomId);
        $this->broadcaster->broadcast(new MatchLeaderboardUpdated($roomCode, $leaderboard));
        return $leaderboard;
    }

    /**
     * Lấy leaderboard hiện tại (nội bộ)
     */
    private function getCurrentLeaderboard(int $roomId): array
    {
        $stmt = $this->conn->prepare("
            SELECT rp.user_id, rp.player_name, rp.current_score, rp.completed_pairs, rp.total_pairs,
                   rp.solve_status, rp.finish_time_ms, rp.connection_status, rp.board_rotations_json, u.pvp_tier
            FROM pvp_room_players rp
            INNER JOIN users u ON rp.user_id = u.id
            WHERE rp.room_id = ?
        ");
        $stmt->execute([$roomId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        usort($rows, [PipeScoringService::class, 'comparePlayers']);

        $leaderboard = [];
        foreach ($rows as $idx => $r) {
            $leaderboard[] = [
                'rank'              => $idx + 1,
                'user_id'           => (int)$r['user_id'],
                'player_name'       => $r['player_name'],
                'current_score'     => (int)$r['current_score'],
                'quality_score'     => json_decode($r['board_rotations_json'] ?? '{}', true)['scoring']['quality_score'] ?? null,
                'completed_pairs'   => (int)$r['completed_pairs'],
                'total_pairs'       => (int)$r['total_pairs'],
                'solve_status'      => $r['solve_status'],
                'finish_time_ms'    => $r['finish_time_ms'] ? (int)$r['finish_time_ms'] : null,
                'connection_status' => $r['connection_status'],
                'tier'              => $r['pvp_tier'],
            ];
        }

        return $leaderboard;
    }

    /**
     * Bảng xếp hạng PvP toàn cầu
     */
    public function getRankedLeaderboard(int $limit = 20): array
    {
        $stmt = $this->conn->prepare("
            SELECT id as user_id, username, name, pvp_rating, pvp_tier,
                   pvp_matches, pvp_wins, pvp_losses, high_score
            FROM users
            WHERE is_active = 1 AND pvp_matches > 0
            ORDER BY pvp_rating DESC, pvp_wins DESC, high_score DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $ranked = [];
        foreach ($users as $idx => $u) {
            $matches = (int)$u['pvp_matches'];
            $wins    = (int)$u['pvp_wins'];
            $winRate = $matches > 0 ? round(($wins / $matches) * 100, 1) : 0.0;
            $tierInfo = PvpRatingService::getTier((int)$u['pvp_rating']);

            $ranked[] = [
                'rank'        => $idx + 1,
                'user_id'     => (int)$u['user_id'],
                'username'    => $u['username'],
                'name'        => $u['name'],
                'rating'      => (int)$u['pvp_rating'],
                'tier_name'   => $tierInfo['name'],
                'tier_color'  => $tierInfo['color'],
                'tier_badge'  => $tierInfo['badge'],
                'pvp_matches' => $matches,
                'pvp_wins'    => $wins,
                'pvp_losses'  => (int)$u['pvp_losses'],
                'win_rate'    => $winRate,
            ];
        }

        return $ranked;
    }

    private function getActiveRoom(string $roomCode): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM pvp_rooms WHERE room_code = ? AND status = 'in_progress' LIMIT 1");
        $stmt->execute([$roomCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getRoomPlayer(int $roomId, int $userId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM pvp_room_players WHERE room_id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$roomId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
