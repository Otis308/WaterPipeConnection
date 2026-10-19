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

        // Dùng transaction + FOR UPDATE để ngăn 2 request đồng thời bắt đầu cùng lúc
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare("SELECT * FROM pvp_rooms WHERE room_code = ? FOR UPDATE");
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

            $this->conn->commit();

            // 4. Khởi tạo board state riêng cho từng người chơi
            foreach ($players as $p) {
                $this->boardStateService->initBoardForPlayer((int)$p['id'], $mapData);
            }

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
                'map_data'  => $mapData,
                'players'   => $formattedPlayers,
            ];
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => 'Lỗi bắt đầu trận: ' . $e->getMessage()];
        }
    }

    /**
     * Cập nhật tiến độ / điểm tạm thời khi người chơi thao tác hoặc nối thành công 1 cặp đường ống
     * NOTE: Endpoint này KHÔNG xác nhận chiến thắng. Chỉ cập nhật tiến trình tiến độ.
     * Điểm tạm tối đa 99 — 100 điểm chỉ do submitSolution (BFS verified) cấp.
     */
    public function submitMoveOrProgress(int $userId, string $roomCode, array $progressData): array
    {
        $roomCode = strtoupper(trim($roomCode));
        $room     = $this->getActiveRoom($roomCode);

        if (!$room) {
            return ['success' => false, 'message' => 'Trận đấu không tồn tại hoặc đã kết thúc.'];
        }

        $player = $this->getRoomPlayer($room['id'], $userId);
        if (!$player) {
            return ['success' => false, 'message' => 'Bạn không thuộc phòng đấu này.'];
        }

        // Idempotency: người đã hoàn thành không nhận cập nhật tiến độ nữa
        if ($player['solve_status'] === 'completed') {
            return ['success' => true, 'current_score' => (int)$player['current_score']];
        }

        // Chống gửi điểm giả: không cho client tự gửi score = 100 hoặc is_winner = true
        if (!empty($progressData['score']) && (int)$progressData['score'] >= 100) {
            return ['success' => false, 'message' => 'Điểm 100 phải được xác minh qua submit-solution.'];
        }
        if (!empty($progressData['is_winner'])) {
            return ['success' => false, 'message' => 'Kết quả thắng phải do server xác minh.'];
        }

        $mapData = json_decode($room['map_config_json'], true) ?: [];

        // 1. Xử lý thao tác xoay ống (Server-Authoritative)
        $action = $progressData['action'] ?? '';
        if ($action === 'rotate' || (isset($progressData['row']) && isset($progressData['col']) && !isset($progressData['rowB']))) {
            $r = (int)$progressData['row'];
            $c = (int)$progressData['col'];
            $deg = (int)($progressData['degrees'] ?? 90);
            $rotRes = $this->boardStateService->applyRotation((int)$player['id'], $r, $c, $deg, $mapData);
            if (!$rotRes['success']) {
                return ['success' => false, 'message' => $rotRes['error']];
            }
        } elseif ($action === 'swap' || (isset($progressData['rowA']) && isset($progressData['colA']) && isset($progressData['rowB']) && isset($progressData['colB']))) {
            $rA = (int)$progressData['rowA'];
            $cA = (int)$progressData['colA'];
            $rB = (int)$progressData['rowB'];
            $cB = (int)$progressData['colB'];
            $swapRes = $this->boardStateService->applySwap((int)$player['id'], $rA, $cA, $rB, $cB, $mapData);
            if (!$swapRes['success']) {
                return ['success' => false, 'message' => $swapRes['error']];
            }
        }

        // 2. Chạy BFS trên bàn cờ server để tính điểm và số cặp đã nối chính xác
        $boardSnapshot = $this->boardStateService->getPlayerBoardSnapshot((int)$room['id'], $userId);
        if ($boardSnapshot && !empty($boardSnapshot['board_state'])) {
            $bfsRes = PvpBoardStateService::verifyBoardSolution($mapData, $boardSnapshot['board_state']);
            $completedPairs = $bfsRes['completed_pairs'];
            // Điểm tiến trình tạm thời khóa trần ở 99 (điểm 100 chỉ do submitSolution công nhận)
            $tentativeScore = min(99, $bfsRes['official_score']);
        } else {
            $completedPairs = max(0, min(
                (int)$room['num_pairs'],
                (int)($progressData['completed_pairs'] ?? $progressData['pairs_connected'] ?? 0)
            ));
            $tentativeScore = max(0, min(99,
                (int)($progressData['tentative_score'] ?? $progressData['current_score'] ?? 0)
            ));
        }

        $now = time();
        $this->conn->prepare("
            UPDATE pvp_room_players
            SET current_score = ?, completed_pairs = ?, last_heartbeat_ts = ?
            WHERE id = ?
        ")->execute([$tentativeScore, $completedPairs, $now, $player['id']]);

        // Broadcast sự kiện tiến độ
        $this->broadcaster->broadcast(new PlayerProgressUpdated(
            $roomCode, $userId, $player['player_name'],
            $completedPairs, (int)$room['num_pairs'],
            $tentativeScore, 'playing'
        ));

        // Cập nhật Leaderboard real-time
        $this->broadcastLiveLeaderboard($room['id'], $roomCode);

        return [
            'success'         => true,
            'completed_pairs' => $completedPairs,
            'current_score'   => $tentativeScore,
        ];
    }

    /**
     * NỘP BÀI HOÀN THÀNH — SERVER-AUTHORITATIVE BFS VALIDATION (AC1)
     *
     * Luồng xử lý:
     *  1. Xác thực người chơi và trạng thái trận
     *  2. Lấy board_rotations_json từ DB (KHÔNG tin client gửi lên)
     *  3. Chạy BFS trên trạng thái server để xác minh kết nối
     *  4. Nếu KHÔNG hoàn thành hoặc điểm < 100: TỪ CHỐI chiến thắng, giữ solve_status = 'playing'
     *  5. Chỉ khi BFS xác nhận 100% nối đúng và đạt 100 điểm: công nhận solve_status = 'completed'
     *  6. Cập nhật DB trong transaction
     *  7. Kiểm tra xem tất cả đã hoàn thành → finalizeMatch
     *
     * @param array $submission Dữ liệu từ client (chỉ dùng để nhận finish_time_ms, không tin score)
     */
    public function submitSolution(int $userId, string $roomCode, array $submission): array
    {
        $roomCode = strtoupper(trim($roomCode));
        $room     = $this->getActiveRoom($roomCode);

        if (!$room) {
            return ['success' => false, 'message' => 'Trận đấu không còn ở trạng thái thi đấu.'];
        }

        $player = $this->getRoomPlayer($room['id'], $userId);
        if (!$player) {
            return ['success' => false, 'message' => 'Không tìm thấy đấu thủ trong trận.'];
        }

        // Idempotency: không cho nộp lặp nếu đã hoàn thành
        if ($player['solve_status'] === 'completed') {
            return [
                'success'      => true,
                'message'      => 'Bạn đã hoàn thành bài thi trước đó.',
                'score'        => (int)$player['current_score'],
                'is_completed' => true,
            ];
        }

        // Chặn các trường giả mạo từ client
        if (!empty($submission['is_winner']) || !empty($submission['force_win'])) {
            return ['success' => false, 'message' => 'Không được phép tự tuyên bố chiến thắng.'];
        }

        // =============================================================
        // BFS SERVER-AUTHORITATIVE: Lấy board state từ DB, KHÔNG tin client
        // =============================================================
        $mapData = json_decode($room['map_config_json'], true) ?: [];

        // Lấy board state của người chơi này từ database
        $boardSnapshot = $this->boardStateService->getPlayerBoardSnapshot((int)$room['id'], $userId);
        $boardState = $boardSnapshot ? $boardSnapshot['board_state'] : [];

        // Chạy BFS phía server
        $bfsResult = PvpBoardStateService::verifyBoardSolution($mapData, $boardState);

        $officialScore  = $bfsResult['official_score'];
        $isCompleted    = $bfsResult['is_all_completed'];
        $completedPairs = $bfsResult['completed_pairs'];

        // finish_time_ms: nhận từ client nhưng validate range
        $finishTimeMs = !empty($submission['finish_time_ms'])
            ? max(1000, (int)$submission['finish_time_ms'])
            : (isset($submission['duration']) ? max(1000, (int)($submission['duration'] * 1000)) : 60000);

        // Giới hạn tối đa = time_limit_seconds của phòng (tránh client gửi giá trị âm hoặc quá lớn)
        $timeLimitMs = (int)$room['time_limit_seconds'] * 1000;
        $finishTimeMs = min($finishTimeMs, $timeLimitMs);

        // =============================================================
        // NẾU CHƯA HOÀN THÀNH HOẶC CHƯA ĐẠT 100 ĐIỂM: TỪ CHỐI CHIẾN THẮNG
        // =============================================================
        if (!$isCompleted || $officialScore < 100) {
            // Cập nhật điểm tạm (tối đa 99), giữ trạng thái playing
            $safeScore = min(99, $officialScore);
            $this->conn->prepare("
                UPDATE pvp_room_players 
                SET current_score = ?, completed_pairs = ?
                WHERE id = ?
            ")->execute([$safeScore, $completedPairs, $player['id']]);

            return [
                'success'         => false,
                'message'         => 'Xác minh thất bại: Đường ống chưa được nối thông hoặc chưa đạt điểm tối đa (100đ). Cần hoàn thành đúng tất cả các cặp ống bằng đường đi ngắn nhất.',
                'official_score'  => $safeScore,
                'is_completed'    => false,
                'completed_pairs' => $completedPairs,
                'total_pairs'     => (int)$room['num_pairs'],
                'pairs_result'    => $bfsResult['pairs_result'],
            ];
        }

        // =============================================================
        // KHI ĐÃ ĐẠT ĐỦ 100 ĐIỂM VÀ TẤT CẢ CẶP KẾT NỐI HỢP LỆ:
        // Cập nhật database trong transaction để tránh race condition
        // =============================================================
        $this->conn->beginTransaction();
        try {
            // Re-check trong transaction để ngăn race condition
            $stmtCheck = $this->conn->prepare("SELECT solve_status FROM pvp_room_players WHERE id = ? FOR UPDATE");
            $stmtCheck->execute([$player['id']]);
            $freshPlayer = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($freshPlayer && $freshPlayer['solve_status'] === 'completed') {
                $this->conn->rollBack();
                return [
                    'success'      => true,
                    'message'      => 'Bạn đã hoàn thành bài thi (concurrent check).',
                    'score'        => (int)$player['current_score'],
                    'is_completed' => true,
                ];
            }

            // Xác định thứ hạng hiện tại (trong transaction)
            $stmtRank = $this->conn->prepare("
                SELECT COUNT(*) FROM pvp_room_players
                WHERE room_id = ? AND solve_status = 'completed'
            ");
            $stmtRank->execute([$room['id']]);
            $currentRank = (int)$stmtRank->fetchColumn() + 1;

            // Cập nhật bản ghi chính thức hoàn thành
            $this->conn->prepare("
                UPDATE pvp_room_players
                SET current_score = 100, completed_pairs = ?,
                    solve_status = 'completed', finish_time_ms = ?, final_rank = ?
                WHERE id = ?
            ")->execute([$completedPairs, $finishTimeMs, $currentRank, $player['id']]);

            $this->conn->commit();
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            return ['success' => false, 'message' => 'Lỗi cập nhật kết quả: ' . $e->getMessage()];
        }

        // Broadcast PlayerCompleted
        $this->broadcaster->broadcast(new PlayerCompleted(
            $roomCode, $userId, $player['player_name'],
            100, $finishTimeMs, $currentRank
        ));

        // Broadcast Leaderboard
        $leaderboard = $this->broadcastLiveLeaderboard($room['id'], $roomCode);

        // Kiểm tra tất cả đã hoàn thành → kết thúc trận sớm
        $stmtCheckAll = $this->conn->prepare("
            SELECT COUNT(*) FROM pvp_room_players
            WHERE room_id = ? AND solve_status != 'completed'
        ");
        $stmtCheckAll->execute([$room['id']]);
        $remainingCount = (int)$stmtCheckAll->fetchColumn();

        if ($remainingCount === 0) {
            $this->finalizeMatch($roomCode, 'all_completed');
        }

        return [
            'success'        => true,
            'official_score' => 100,
            'is_completed'   => true,
            'bfs_verified'   => true,
            'pairs_result'   => $bfsResult['pairs_result'],
            'rank'           => $currentRank,
            'finish_time_ms' => $finishTimeMs,
            'leaderboard'    => $leaderboard,
        ];
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
            $stmt = $this->conn->prepare("SELECT * FROM pvp_rooms WHERE room_code = ? FOR UPDATE");
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

            // Sắp xếp thứ hạng chính thức:
            // 1. Điểm cao nhất
            // 2. Đã hoàn thành (solve_status = 'completed') đứng trước
            // 3. Thời gian hoàn thành ngắn hơn
            // 4. Số cặp ống nhiều hơn
            usort($players, function ($a, $b) {
                if ($a['current_score'] !== $b['current_score']) {
                    return $b['current_score'] <=> $a['current_score'];
                }
                $aDone = ($a['solve_status'] === 'completed') ? 1 : 0;
                $bDone = ($b['solve_status'] === 'completed') ? 1 : 0;
                if ($aDone !== $bDone) {
                    return $bDone <=> $aDone;
                }
                if ($aDone && $bDone && $a['finish_time_ms'] && $b['finish_time_ms']) {
                    return $a['finish_time_ms'] <=> $b['finish_time_ms'];
                }
                return $b['completed_pairs'] <=> $a['completed_pairs'];
            });

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
                   rp.solve_status, rp.finish_time_ms, rp.connection_status, u.pvp_tier
            FROM pvp_room_players rp
            INNER JOIN users u ON rp.user_id = u.id
            WHERE rp.room_id = ?
            ORDER BY rp.current_score DESC,
                     (CASE WHEN rp.solve_status = 'completed' THEN 1 ELSE 0 END) DESC,
                     rp.finish_time_ms ASC,
                     rp.completed_pairs DESC
        ");
        $stmt->execute([$roomId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $leaderboard = [];
        foreach ($rows as $idx => $r) {
            $leaderboard[] = [
                'rank'              => $idx + 1,
                'user_id'           => (int)$r['user_id'],
                'player_name'       => $r['player_name'],
                'current_score'     => (int)$r['current_score'],
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
