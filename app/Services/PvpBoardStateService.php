<?php

namespace App\Services;

/**
 * PvpBoardStateService — Quản lý trạng thái bàn cờ server-authoritative cho PvP
 *
 * NHIỆM VỤ (AC1 — Server Authority):
 *  1. Lưu trạng thái xoay ống riêng của từng người chơi vào database
 *  2. Chạy BFS phía backend để xác minh kết nối ống nước thực tế
 *  3. Tính điểm chính thức từ kết quả BFS, không tin client
 *  4. Hỗ trợ reconnect: trả snapshot đầy đủ bao gồm cả board_rotations_json
 *
 * MÔ HÌNH ỐNG (khớp với BanDoGame.js và PvpMapService.php):
 *  - Hướng: 0=Trên, 1=Phải, 2=Dưới, 3=Trái
 *  - Loại ống và cổng mở mặc định (rotation=0):
 *      'Thang'   => [0, 2]       (thẳng đứng)
 *      'ChuL'    => [1, 2]       (góc L)
 *      'ChuThap' => [0, 1, 2, 3] (chữ thập, rotation không đổi cổng)
 *      'BatDau'  => [1]          (nguồn, cổng phải)
 *      'KetThuc' => [1]          (đích, cổng phải — khớp với start)
 *  - Khi xoay $steps bước (mỗi bước = 90°): port_mới = (port_gốc + $steps) % 4
 */
class PvpBoardStateService
{
    /** Cổng mở mặc định của từng loại ống khi rotation = 0 */
    private const DEFAULT_PORTS = [
        'Thang'   => [0, 2],
        'ChuL'    => [1, 2],
        'ChuThap' => [0, 1, 2, 3],
        'BatDau'  => [1],
        'KetThuc' => [1],
    ];

    /** Hướng ngược lại: dùng để kiểm tra kết nối tương hỗ hai chiều */
    private const OPPOSITE = [0 => 2, 1 => 3, 2 => 0, 3 => 1];

    /** Offset row/col theo 4 hướng */
    private const DR = [0 => -1, 1 => 0, 2 => 1, 3 => 0];
    private const DC = [0 => 0,  1 => 1, 2 => 0, 3 => -1];

    private PdoDatabaseService $dbService;
    private \PDO $conn;

    public function __construct(?PdoDatabaseService $dbService = null)
    {
        $this->dbService = $dbService ?? new PdoDatabaseService();
        $this->conn = $this->dbService->getConnection();
    }

    // ===========================================================================
    // PHẦN 1: LƯU & ĐỌC TRẠNG THÁI BÀN CỜ CÁ NHÂN
    // ===========================================================================

    /**
     * Khởi tạo bàn cờ riêng cho người chơi từ dữ liệu map ban đầu
     * Gọi khi trận bắt đầu (startMatch)
     */
    public function initBoardForPlayer(int $roomPlayerId, array $mapData): bool
    {
        $state = self::buildInitialBoardState($mapData);
        $json = json_encode($state, JSON_UNESCAPED_UNICODE);
        $stmt = $this->conn->prepare("
            UPDATE pvp_room_players
            SET board_rotations_json = ?, board_state_version = 0
            WHERE id = ?
        ");
        return $stmt->execute([$json, $roomPlayerId]);
    }

    public static function buildInitialBoardState(array $mapData): array
    {
        $rotations = [];
        $layout = [];
        foreach ($mapData['cells'] ?? [] as $cell) {
            $key = $cell['r'] . '-' . $cell['c'];
            $rotations[$key] = (int)($cell['initialRot'] ?? 0);
            $layout[$key] = [
                'shape'  => $cell['shape'] ?? '',
                'file'   => $cell['file'] ?? '',
                'pairId' => (int)($cell['pairId'] ?? 0),
                'role'   => $cell['role'] ?? '',
            ];
        }
        return ['v' => 2, 'rotations' => $rotations, 'layout' => $layout];
    }

    public static function decodeBoardState(?string $json): array
    {
        $decoded = $json ? json_decode($json, true) : [];
        if (!is_array($decoded)) {
            return ['v' => 2, 'rotations' => [], 'layout' => []];
        }
        if (isset($decoded['v']) && isset($decoded['rotations'])) {
            return [
                'v'         => 2,
                'rotations' => is_array($decoded['rotations']) ? $decoded['rotations'] : [],
                'layout'    => is_array($decoded['layout'] ?? null) ? $decoded['layout'] : [],
            ];
        }
        return ['v' => 2, 'rotations' => $decoded, 'layout' => []];
    }

    /**
     * Áp dụng một thao tác xoay ống từ người chơi (server-authoritative)
     * Trả về ['success', 'new_rotation', 'board_state_version', 'error']
     */
    public function applyRotation(
        int $roomPlayerId,
        int $row,
        int $col,
        int $degreesToRotate = 90,
        array $mapData = []
    ): array {
        if (!in_array($degreesToRotate, [90, 180, 270], true)) {
            return ['success' => false, 'error' => 'Góc xoay không hợp lệ. Chỉ chấp nhận 90, 180, 270 độ.'];
        }

        // Kiểm tra ô có phải ống xoay được không (OngXanh)
        $cellKey = "{$row}-{$col}";
        if (!empty($mapData['cells'])) {
            $cellInfo = null;
            foreach ($mapData['cells'] as $cell) {
                if ($cell['r'] == $row && $cell['c'] == $col) {
                    $cellInfo = $cell;
                    break;
                }
            }
            // OngDo và OngVang không xoay được (OngVang chỉ hoán đổi)
            if ($cellInfo && !empty($cellInfo['file'])) {
                $file = $cellInfo['file'];
                if (str_contains($file, 'OngDo') || str_contains($file, 'OngVang')
                    || str_contains($file, 'BatDau') || str_contains($file, 'KetThuc')) {
                    return ['success' => false, 'error' => 'Ô này không thể xoay.'];
                }
            }
        }

        $ownsTx = !$this->conn->inTransaction();
        if ($ownsTx) {
            $this->conn->beginTransaction();
        }
        try {
            $stmt = $this->conn->prepare("
                SELECT board_rotations_json, board_state_version
                FROM pvp_room_players WHERE id = ?
            ");
            $stmt->execute([$roomPlayerId]);
            $row_data = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row_data) {
                if ($ownsTx) {
                    $this->conn->rollBack();
                }
                return ['success' => false, 'error' => 'Không tìm thấy player.'];
            }

            $state = self::decodeBoardState($row_data['board_rotations_json'] ?? '{}');
            $currentRot = (int)($state['rotations'][$cellKey] ?? 0);
            $newRot = ($currentRot + $degreesToRotate) % 360;
            $state['rotations'][$cellKey] = $newRot;

            $newVersion = (int)$row_data['board_state_version'] + 1;
            $this->conn->prepare("
                UPDATE pvp_room_players
                SET board_rotations_json = ?, board_state_version = ?
                WHERE id = ?
            ")->execute([json_encode($state, JSON_UNESCAPED_UNICODE), $newVersion, $roomPlayerId]);

            if ($ownsTx) {
                $this->conn->commit();
            }
            // #region agent log
            PvpDebugLog::write('H2', 'PvpBoardStateService.php:applyRotation', 'rotation_applied', [
                'roomPlayerId' => $roomPlayerId,
                'cell'         => $cellKey,
                'newRot'       => $newRot,
                'version'      => $newVersion,
            ]);
            // #endregion
            return [
                'success'             => true,
                'cell_key'            => $cellKey,
                'new_rotation'        => $newRot,
                'board_state_version' => $newVersion,
                'board_state'         => $state,
            ];
        } catch (\Throwable $e) {
            if ($ownsTx && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'error' => 'Lỗi khi lưu xoay ống: ' . $e->getMessage()];
        }
    }

    /**
     * Áp dụng thao tác HOÁN ĐỔI 2 ô vàng liền kề (OngVang swap)
     * Trả về ['success', 'board_state_version', 'error']
     */
    public function applySwap(
        int $roomPlayerId,
        int $rowA, int $colA,
        int $rowB, int $colB,
        array $mapData = []
    ): array {
        // Kiểm tra khoảng cách Manhattan = 1
        $dist = abs($rowA - $rowB) + abs($colA - $colB);
        if ($dist !== 1) {
            return ['success' => false, 'error' => 'Chỉ có thể hoán đổi 2 ô liền kề nhau (cách nhau 1 ô).'];
        }

        // Kiểm tra cả 2 ô đều là OngVang
        $keyA = "{$rowA}-{$colA}";
        $keyB = "{$rowB}-{$colB}";
        $cellA = $cellB = null;
        foreach ($mapData['cells'] ?? [] as $cell) {
            if ($cell['r'] == $rowA && $cell['c'] == $colA) $cellA = $cell;
            if ($cell['r'] == $rowB && $cell['c'] == $colB) $cellB = $cell;
        }
        if (!$cellA || !$cellB) {
            return ['success' => false, 'error' => 'Không tìm thấy ô trong bản đồ.'];
        }
        $isVangA = !empty($cellA['file']) && str_contains($cellA['file'], 'OngVang');
        $isVangB = !empty($cellB['file']) && str_contains($cellB['file'], 'OngVang');
        if (!$isVangA || !$isVangB) {
            return ['success' => false, 'error' => 'Chỉ có thể hoán đổi 2 ô ống vàng liền kề.'];
        }

        $ownsTx = !$this->conn->inTransaction();
        if ($ownsTx) {
            $this->conn->beginTransaction();
        }
        try {
            $stmt = $this->conn->prepare("
                SELECT board_rotations_json, board_state_version
                FROM pvp_room_players WHERE id = ?
            ");
            $stmt->execute([$roomPlayerId]);
            $rowData = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$rowData) {
                if ($ownsTx) {
                    $this->conn->rollBack();
                }
                return ['success' => false, 'error' => 'Không tìm thấy player.'];
            }

            $state = self::decodeBoardState($rowData['board_rotations_json'] ?? '{}');
            $rotA = $state['rotations'][$keyA] ?? 0;
            $rotB = $state['rotations'][$keyB] ?? 0;
            $state['rotations'][$keyA] = $rotB;
            $state['rotations'][$keyB] = $rotA;

            $layoutA = $state['layout'][$keyA] ?? null;
            $layoutB = $state['layout'][$keyB] ?? null;
            if ($layoutA !== null || $layoutB !== null) {
                $state['layout'][$keyA] = $layoutB;
                $state['layout'][$keyB] = $layoutA;
            }

            $newVersion = (int)$rowData['board_state_version'] + 1;
            $this->conn->prepare("
                UPDATE pvp_room_players
                SET board_rotations_json = ?, board_state_version = ?
                WHERE id = ?
            ")->execute([json_encode($state, JSON_UNESCAPED_UNICODE), $newVersion, $roomPlayerId]);

            if ($ownsTx) {
                $this->conn->commit();
            }
            return [
                'success'             => true,
                'swapped'             => [$keyA, $keyB],
                'board_state_version' => $newVersion,
                'board_state'         => $state,
            ];
        } catch (\Throwable $e) {
            if ($ownsTx && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'error' => 'Lỗi hoán đổi: ' . $e->getMessage()];
        }
    }

    /**
     * Lấy snapshot bàn cờ đầy đủ cho một người chơi (phục vụ Reconnect)
     */
    public function getPlayerBoardSnapshot(int $roomId, int $userId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT rp.board_rotations_json, rp.board_state_version,
                   rp.current_score, rp.completed_pairs, rp.solve_status,
                   rp.finish_time_ms, rp.total_pairs,
                   r.map_config_json, r.status as room_status,
                   r.started_at_ts, r.time_limit_seconds
            FROM pvp_room_players rp
            INNER JOIN pvp_rooms r ON rp.room_id = r.id
            WHERE rp.room_id = ? AND rp.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$roomId, $userId]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$data) return null;

        $mapData = !empty($data['map_config_json']) ? json_decode($data['map_config_json'], true) : null;
        $boardState = self::decodeBoardState($data['board_rotations_json'] ?? null);

        return [
            'board_state'         => $boardState,
            'board_rotations'     => $boardState['rotations'],
            'board_layout'        => $boardState['layout'],
            'board_state_version' => (int)$data['board_state_version'],
            'current_score'       => (int)$data['current_score'],
            'completed_pairs'     => (int)$data['completed_pairs'],
            'total_pairs'         => (int)$data['total_pairs'],
            'solve_status'        => $data['solve_status'],
            'finish_time_ms'      => $data['finish_time_ms'] ? (int)$data['finish_time_ms'] : null,
            'room_status'         => $data['room_status'],
            'started_at_ts'       => $data['started_at_ts'] ? (int)$data['started_at_ts'] : null,
            'time_limit_seconds'  => (int)$data['time_limit_seconds'],
            'map_data'            => $mapData,
        ];
    }

    // ===========================================================================
    // PHẦN 2: BFS VALIDATOR — XÁC MINH KẾT NỐI ỐNG NƯỚC PHÍA SERVER
    // ===========================================================================

    /**
     * XÁC MINH TOÀN BỘ LỜI GIẢI bằng BFS phía server — CORE ANTI-CHEAT
     *
     * Thuật toán:
     *  1. Tái dựng ma trận bàn cờ từ mapData + board_rotations_json của người chơi
     *  2. Với mỗi cặp ống, chạy BFS từ ô Start → tìm ô End cùng pairId
     *  3. BFS chỉ đi qua các ô có kết nối tương hỗ hợp lệ (bidirectional port check)
     *  4. Tính điểm dựa trên độ dài đường đi thực tế vs optimal
     *
     * @param array $mapData        Dữ liệu bản đồ từ server (map_config_json)
     * @param array $boardRotations Trạng thái xoay hiện tại từ board_rotations_json
     * @return array [
     *   'is_all_completed' => bool,
     *   'pairs_result'     => [['pair_id', 'is_connected', 'path_length', 'optimal_length'], ...],
     *   'official_score'   => int,
     *   'completed_pairs'  => int,
     *   'total_pairs'      => int,
     * ]
     */
    public static function verifyBoardSolution(array $mapData, array $boardRotations): array
    {
        $numPairs = (int)($mapData['numPairs'] ?? 0);
        $pipesList = $mapData['pipes'] ?? [];
        $cellsList = $mapData['cells'] ?? [];

        if ($numPairs === 0 || empty($pipesList) || empty($cellsList)) {
            return [
                'is_all_completed' => false,
                'pairs_result'     => [],
                'official_score'   => 0,
                'completed_pairs'  => 0,
                'total_pairs'      => $numPairs,
                'error'            => 'Dữ liệu bản đồ không hợp lệ.',
            ];
        }

        // Bước 1: Tái dựng ma trận bàn cờ
        // $board[$r][$c] = ['shape' => string, 'rotation' => int, 'role' => string, 'pairId' => int]
        $normalizedState = [];
        if (isset($boardRotations['v']) && isset($boardRotations['rotations'])) {
            $normalizedState = $boardRotations;
        } else {
            $normalizedState = ['rotations' => $boardRotations, 'layout' => []];
        }
        $rotationMap = $normalizedState['rotations'] ?? [];
        $layoutMap = $normalizedState['layout'] ?? [];

        $board = [];
        foreach ($cellsList as $cell) {
            $r = (int)$cell['r'];
            $c = (int)$cell['c'];
            $key = "{$r}-{$c}";
            $layout = $layoutMap[$key] ?? [];

            $file = $layout['file'] ?? ($cell['file'] ?? '');
            $shape = $layout['shape'] ?? ($cell['shape'] ?? '');
            if (empty($shape)) {
                if (str_contains($file, 'BatDau') || str_contains($file, 'OngBatDau')) {
                    $shape = 'BatDau';
                } elseif (str_contains($file, 'KetThuc') || str_contains($file, 'OngKetThuc')) {
                    $shape = 'KetThuc';
                } elseif (str_contains($file, 'Thang')) {
                    $shape = 'Thang';
                } elseif (str_contains($file, 'ChuL')) {
                    $shape = 'ChuL';
                } elseif (str_contains($file, 'ChuThap')) {
                    $shape = 'ChuThap';
                } else {
                    $shape = 'ChuL';
                }
            }

            $rotation = array_key_exists($key, $rotationMap)
                ? (int)$rotationMap[$key]
                : (int)($cell['initialRot'] ?? 0);

            $board[$r][$c] = [
                'shape'    => $shape,
                'rotation' => $rotation,
                'pairId'   => (int)($layout['pairId'] ?? ($cell['pairId'] ?? 0)),
                'role'     => $layout['role'] ?? ($cell['role'] ?? ''),
            ];
        }

        // Bước 2: Với mỗi pair, tìm Start và End rồi chạy BFS
        $pairsResult = [];
        $completedPairs = 0;
        $totalPlayerLength = 0;
        $totalOptimalLength = (int)($mapData['optimalTotalLength'] ?? 1);

        foreach ($pipesList as $pipeInfo) {
            $pairId = (int)$pipeInfo['pairId'];
            $optimalLen = (int)$pipeInfo['shortestLength'];

            // Tìm ô Start và End của cặp này trong board
            $startCell = null;
            $endCell   = null;
            foreach ($board as $r => $cols) {
                foreach ($cols as $c => $cell) {
                    if ((int)$cell['pairId'] === $pairId) {
                        if ($cell['role'] === 'start') $startCell = [$r, $c];
                        if ($cell['role'] === 'end')   $endCell   = [$r, $c];
                    }
                }
            }

            if (!$startCell || !$endCell) {
                $pairsResult[] = [
                    'pair_id'       => $pairId,
                    'is_connected'  => false,
                    'path_length'   => 0,
                    'optimal_length'=> $optimalLen,
                    'error'         => 'Không tìm thấy ô Start hoặc End trong bản đồ.',
                ];
                continue;
            }

            // Chạy BFS từ Start → End
            $bfsResult = self::bfsConnect($board, $startCell, $endCell, $pairId);

            if ($bfsResult['connected']) {
                $completedPairs++;
                $totalPlayerLength += $bfsResult['path_length'];
            }

            $pairsResult[] = [
                'pair_id'        => $pairId,
                'is_connected'   => $bfsResult['connected'],
                'path_length'    => $bfsResult['path_length'],
                'optimal_length' => $optimalLen,
            ];
        }

        // Bước 3: Tính điểm chính thức theo công thức chuẩn của BanDoGame.js
        // Mỗi cặp có điểm tối đa = 100 / numPairs
        // Mỗi ô đi thừa so với độ dài tối ưu bị trừ 10 điểm (tối thiểu 10đ/cặp)
        $maxScorePerPipe = 100.0 / max(1, $numPairs);
        $totalScoreFloat = 0.0;

        foreach ($pairsResult as $pRes) {
            if (!empty($pRes['is_connected'])) {
                $extraCells = max(0, (int)$pRes['path_length'] - (int)$pRes['optimal_length']);
                $pipeScore = max(10, (int)round($maxScorePerPipe - ($extraCells * 10)));
                $totalScoreFloat += $pipeScore;
            }
        }

        $officialScore = (int)round($totalScoreFloat);

        // QUY TẮC CỐT LÕI: Chỉ công nhận hoàn thành khi 100% số cặp được kết nối VÀ đạt đủ 100 điểm (không có ô thừa)
        $isAllCompleted = ($completedPairs === $numPairs && $numPairs > 0 && $officialScore >= 100);

        return [
            'is_all_completed' => $isAllCompleted,
            'pairs_result'     => $pairsResult,
            'official_score'   => $officialScore,
            'completed_pairs'  => $completedPairs,
            'total_pairs'      => $numPairs,
            'player_length'    => $totalPlayerLength,
            'optimal_length'   => $totalOptimalLength,
        ];
    }

    /**
     * BFS từ ô Start đến ô End trong cùng một cặp pairId
     * Chỉ đi qua các ô có kết nối cổng tương hỗ hợp lệ (bidirectional)
     */
    private static function bfsConnect(array $board, array $start, array $end, int $pairId): array
    {
        [$startR, $startC] = $start;
        [$endR, $endC] = $end;

        $queue = [[$startR, $startC, 1]]; // [row, col, distance]
        $visited = [];
        $visited["{$startR}-{$startC}"] = true;

        while (!empty($queue)) {
            [$currR, $currC, $dist] = array_shift($queue);

            if ($currR === $endR && $currC === $endC) {
                return ['connected' => true, 'path_length' => $dist];
            }

            // Lấy cổng mở của ô hiện tại
            $currCell = $board[$currR][$currC] ?? null;
            if (!$currCell) continue;

            $currPorts = self::getOpenPorts($currCell['shape'], $currCell['rotation']);

            // Thử đi theo 4 hướng
            foreach ([0, 1, 2, 3] as $dir) {
                // Chỉ đi theo hướng $dir nếu ô hiện tại có cổng mở ở hướng đó
                if (!in_array($dir, $currPorts, true)) continue;

                $nextR = $currR + self::DR[$dir];
                $nextC = $currC + self::DC[$dir];
                $nextKey = "{$nextR}-{$nextC}";

                if (isset($visited[$nextKey])) continue;
                $nextCell = $board[$nextR][$nextC] ?? null;
                if (!$nextCell) continue;

                // Quy tắc: ô kế tiếp phải có cổng mở ở hướng NGƯỢC LẠI
                $oppDir = self::OPPOSITE[$dir];
                $nextPorts = self::getOpenPorts($nextCell['shape'], $nextCell['rotation']);
                if (!in_array($oppDir, $nextPorts, true)) continue;

                // Quy tắc BFS chặt chẽ:
                // - Ô Start chỉ có thể là điểm bắt đầu (không đi qua Start của cặp khác)
                // - Ô End phải đúng cặp pairId
                // - Ô thường chỉ cho qua nếu không phải Start/End của cặp khác
                $nextRole   = $nextCell['role'] ?? '';
                $nextPairId = (int)$nextCell['pairId'];

                if ($nextRole === 'end' && $nextPairId === $pairId) {
                    // Đây là đích đúng của cặp → kết nối thành công!
                    return ['connected' => true, 'path_length' => $dist + 1];
                }

                // Không đi qua Start/End của CẶP KHÁC
                if (($nextRole === 'start' || $nextRole === 'end') && $nextPairId !== $pairId) {
                    continue;
                }

                $visited[$nextKey] = true;
                $queue[] = [$nextR, $nextC, $dist + 1];
            }
        }

        return ['connected' => false, 'path_length' => 0];
    }

    /**
     * Tính các cổng mở thực tế của một ô dựa trên shape và rotation
     *
     * @param string $shape    Loại ống: 'Thang', 'ChuL', 'ChuThap', 'BatDau', 'KetThuc'
     * @param int    $rotation Góc xoay thực tế (0, 90, 180, 270)
     * @return int[] Mảng các hướng cổng mở (0=Trên, 1=Phải, 2=Dưới, 3=Trái)
     */
    public static function getOpenPorts(string $shape, int $rotation): array
    {
        $defaultPorts = self::DEFAULT_PORTS[$shape] ?? [0, 2];
        $steps = (int)(($rotation / 90)) % 4;

        if ($steps === 0) {
            return $defaultPorts;
        }

        return array_map(fn(int $port) => ($port + $steps) % 4, $defaultPorts);
    }
}
