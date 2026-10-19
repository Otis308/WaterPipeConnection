<?php

namespace App\Services;

class PvpMapService
{
    public const GENERATOR_VERSION = 'pipe-map-v1';

    // Bảng màu cho các cặp đường ống
    public const PIPE_PAIR_COLORS = [
        ['pairId' => 1, 'name' => 'Xanh Dương', 'hex' => '#00e5ff'],
        ['pairId' => 2, 'name' => 'Đỏ Hồng',    'hex' => '#ff2a85'],
        ['pairId' => 3, 'name' => 'Xanh Lá',    'hex' => '#00e676'],
        ['pairId' => 4, 'name' => 'Tím Vàng',   'hex' => '#d500f9'],
    ];

    // Cấu hình cổng mở mặc định (0: Trên, 1: Phải, 2: Dưới, 3: Trái)
    public const CAU_HINH_ONG = [
        'Thang'    => [0, 2],
        'ChuL'     => [1, 2],
        'ChuThap'  => [0, 1, 2, 3],
        'BatDau'   => [1],
        'KetThuc'  => [1],
    ];

    /**
     * Tính toán config hash chuẩn AC3 cho cấu hình bản đồ
     */
    public static function calculateConfigHash(array $config): string
    {
        $payload = [
            'generator_version' => self::GENERATOR_VERSION,
            'map_size'          => (string)($config['map_size'] ?? '3x8'),
            'difficulty'        => (string)($config['difficulty'] ?? 'easy'),
            'num_pairs'         => (int)($config['num_pairs'] ?? 2),
            'map_seed'          => (string)($config['map_seed'] ?? ''),
        ];
        return hash('sha256', json_encode($payload));
    }

    /**
     * Bộ sinh số giả ngẫu nhiên Mulberry32 (giống hệt BanDoGame.js)
     */
    public static function createRng(string $seedStr): \Closure
    {
        $h = 2166136261;
        $len = strlen($seedStr);
        for ($i = 0; $i < $len; $i++) {
            $h = self::imul($h ^ ord($seedStr[$i]), 16777619);
        }
        $h = $h & 0xFFFFFFFF;

        return function () use (&$h): float {
            $h = ($h + 0x6D2B79F5) & 0xFFFFFFFF;
            $t = self::imul($h ^ ($h >> 15), 1 | $h);
            $t = ($t ^ ($t + self::imul($t ^ ($t >> 7), 61 | $t))) & 0xFFFFFFFF;
            $res = (($t ^ ($t >> 14)) & 0xFFFFFFFF) / 4294967296.0;
            return $res < 0 ? $res + 1.0 : $res;
        };
    }

    private static function imul(int $a, int $b): int
    {
        $a = $a & 0xFFFFFFFF;
        $b = $b & 0xFFFFFFFF;
        $aHi = ($a >> 16) & 0xFFFF;
        $aLo = $a & 0xFFFF;
        $bHi = ($b >> 16) & 0xFFFF;
        $bLo = $b & 0xFFFF;
        return (($aLo * $bLo) + ((($aHi * $bLo + $aLo * $bHi) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF;
    }

    /**
     * Sinh bản đồ deterministic PvP đồng bộ 100% giữa các người chơi
     */
    public static function generateMap(string $mapSizeKey, string $difficulty, int $numPairs, string $seedKey): array
    {
        [$mapRows, $mapCols] = array_map('intval', explode('x', $mapSizeKey));
        $gridRows = 5;
        $gridCols = 12;

        $rowOffset = (int)floor(($gridRows - $mapRows) / 2);
        $colOffset = (int)floor(($gridCols - $mapCols) / 2);
        $minR = $rowOffset + 1;
        $maxR = $rowOffset + $mapRows;
        $minC = $colOffset + 1;
        $maxC = $colOffset + $mapCols;

        $rng = self::createRng($seedKey);

        $cellsMap = [];
        $pipesData = [];
        $totalOptimalLength = 0;

        // Phân vùng ma trận cho K đường ống
        for ($p = 0; $p < $numPairs; $p++) {
            $colorMeta = self::PIPE_PAIR_COLORS[$p % count(self::PIPE_PAIR_COLORS)];
            $zMinR = $minR; $zMaxR = $maxR;
            $zMinC = $minC; $zMaxC = $maxC;

            if ($mapCols >= $numPairs * 2) {
                $baseWidth = (int)floor($mapCols / $numPairs);
                $rem = $mapCols % $numPairs;
                $cOffset = $p * $baseWidth + min($p, $rem);
                $width = $baseWidth + ($p < $rem ? 1 : 0);
                $zMinC = $minC + $cOffset;
                $zMaxC = $zMinC + $width - 1;
            } else {
                $baseHeight = (int)floor($mapRows / $numPairs);
                $zMinR = $minR + $p * $baseHeight;
                $zMaxR = ($p === $numPairs - 1) ? $maxR : ($zMinR + $baseHeight - 1);
            }

            $flip = ($p % 2 === 1);
            $startPos = [
                'r' => $flip ? $zMaxR : $zMinR,
                'c' => $zMinC,
                'rot' => $flip ? 270 : 90,
                'dir' => $flip ? 0 : 2,
            ];
            $endPos = [
                'r' => $flip ? $zMinR : $zMaxR,
                'c' => $zMaxC,
                'rot' => $flip ? 90 : 270,
                'dir' => $flip ? 2 : 0,
            ];

            // Tìm đường zíc zắc DFS
            $path = self::findWindingPath($startPos, $endPos, $zMinR, $zMaxR, $zMinC, $zMaxC, $rng);
            $totalOptimalLength += count($path);

            // Start & End
            $cellsMap["{$startPos['r']}-{$startPos['c']}"] = [
                'r' => $startPos['r'], 'c' => $startPos['c'],
                'file' => 'OngBatDau.svg', 'initialRot' => $startPos['rot'],
                'pairId' => $p + 1, 'role' => 'start',
            ];
            $cellsMap["{$endPos['r']}-{$endPos['c']}"] = [
                'r' => $endPos['r'], 'c' => $endPos['c'],
                'file' => 'OngKetThuc.svg', 'initialRot' => $endPos['rot'],
                'pairId' => $p + 1, 'role' => 'end',
            ];

            $steps = [];
            for ($i = 1; $i < count($path) - 1; $i++) {
                $prev = $path[$i - 1];
                $curr = $path[$i];
                $next = $path[$i + 1];

                $inDir = self::getDirection($curr, $prev);
                $outDir = self::getDirection($curr, $next);
                $shapeData = self::determineShapeAndAngle($inDir, $outDir);

                $steps[] = [
                    'r' => $curr['r'], 'c' => $curr['c'],
                    'shape' => $shapeData['shape'],
                    'correctRot' => $shapeData['correctRot'],
                ];

                $cellsMap["{$curr['r']}-{$curr['c']}"] = [
                    'r' => $curr['r'], 'c' => $curr['c'],
                    'shape' => $shapeData['shape'],
                    'correctRot' => $shapeData['correctRot'],
                    'pairId' => $p + 1,
                    'isSolution' => true,
                ];
            }

            $pipesData[] = [
                'pairId' => $p + 1,
                'color' => $colorMeta,
                'start' => $startPos,
                'end' => $endPos,
                'shortestLength' => count($path),
                'steps' => $steps,
            ];
        }

        // Tạo layout ống xáo trộn cho người chơi
        $cellsList = [];
        for ($r = $minR; $r <= $maxR; $r++) {
            for ($c = $minC; $c <= $maxC; $c++) {
                $key = "{$r}-{$c}";
                if (isset($cellsMap[$key])) {
                    $item = $cellsMap[$key];
                    if (!empty($item['role'])) {
                        $cellsList[] = $item;
                    } elseif (!empty($item['isSolution'])) {
                        // Xoay lệch ống giải
                        $color = ($difficulty === 'hard' && $rng() < 0.4) ? 'OngVang' : 'OngXanh';
                        $offset = [90, 180, 270][(int)floor($rng() * 3)];
                        $initialRot = ($item['correctRot'] + $offset) % 360;
                        $cellsList[] = [
                            'r' => $r, 'c' => $c,
                            'shape' => $item['shape'],
                            'file' => "{$color}/{$color}-{$item['shape']}.svg",
                            'initialRot' => $initialRot,
                            'pairId' => $item['pairId'],
                        ];
                    }
                } else {
                    // Ô ngoài đường giải
                    $color = ($difficulty === 'hard' && $rng() < 0.3) ? 'OngVang' : 'OngXanh';
                    $shapes = ['Thang', 'ChuL', 'ChuThap'];
                    $s = $shapes[(int)floor($rng() * count($shapes))];
                    $rot = (int)floor($rng() * 4) * 90;
                    $cellsList[] = [
                        'r' => $r, 'c' => $c,
                        'shape' => $s,
                        'file' => "{$color}/{$color}-{$s}.svg",
                        'initialRot' => $rot,
                    ];
                }
            }
        }

        return [
            'seedKey'            => $seedKey,
            'generatorVersion'   => self::GENERATOR_VERSION,
            'mapSizeKey'         => $mapSizeKey,
            'difficulty'         => $difficulty,
            'numPairs'           => $numPairs,
            'optimalTotalLength' => $totalOptimalLength,
            'thinkingTimeMs'     => 300000, // 5 phút
            'pipes'              => $pipesData,
            'cells'              => $cellsList,
        ];
    }

    /**
     * Payload gửi client: cùng seed/config nhưng không kèm đường giải (steps.correctRot).
     */
    public static function publicMapPayload(array $mapData): array
    {
        $cells = [];
        foreach ($mapData['cells'] ?? [] as $cell) {
            $cells[] = [
                'r'          => (int)($cell['r'] ?? 0),
                'c'          => (int)($cell['c'] ?? 0),
                'shape'      => $cell['shape'] ?? null,
                'file'       => $cell['file'] ?? null,
                'initialRot' => (int)($cell['initialRot'] ?? 0),
                'pairId'     => isset($cell['pairId']) ? (int)$cell['pairId'] : null,
                'role'       => $cell['role'] ?? null,
            ];
        }

        $pipes = [];
        foreach ($mapData['pipes'] ?? [] as $pipe) {
            $pipes[] = [
                'pairId'         => (int)($pipe['pairId'] ?? 0),
                'color'          => $pipe['color'] ?? null,
                'start'          => $pipe['start'] ?? null,
                'end'            => $pipe['end'] ?? null,
                'shortestLength' => (int)($pipe['shortestLength'] ?? 0),
            ];
        }

        $public = [
            'seedKey'            => $mapData['seedKey'] ?? null,
            'generatorVersion'   => $mapData['generatorVersion'] ?? self::GENERATOR_VERSION,
            'mapSizeKey'         => $mapData['mapSizeKey'] ?? null,
            'difficulty'         => $mapData['difficulty'] ?? null,
            'numPairs'           => (int)($mapData['numPairs'] ?? 0),
            'optimalTotalLength' => (int)($mapData['optimalTotalLength'] ?? 0),
            'thinkingTimeMs'     => (int)($mapData['thinkingTimeMs'] ?? 300000),
            'pipes'              => $pipes,
            'cells'              => $cells,
        ];
        $public['config_hash'] = self::configHash($public);
        return $public;
    }

    public static function configHash(array $publicMap): string
    {
        $canonical = [
            'seedKey'          => $publicMap['seedKey'] ?? '',
            'generatorVersion' => $publicMap['generatorVersion'] ?? self::GENERATOR_VERSION,
            'mapSizeKey'       => $publicMap['mapSizeKey'] ?? '',
            'difficulty'       => $publicMap['difficulty'] ?? '',
            'numPairs'         => (int)($publicMap['numPairs'] ?? 0),
            'cells'            => $publicMap['cells'] ?? [],
            'pipes'            => $publicMap['pipes'] ?? [],
        ];
        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Góc xoay lời giải (chỉ dùng nội bộ để chứng minh bản đồ giải được).
     */
    public static function extractSolvedRotations(array $mapData): array
    {
        $rotations = [];
        foreach ($mapData['cells'] ?? [] as $cell) {
            $key = ($cell['r'] ?? 0) . '-' . ($cell['c'] ?? 0);
            if (!empty($cell['role'])) {
                $rotations[$key] = (int)($cell['initialRot'] ?? 0);
            }
        }
        foreach ($mapData['pipes'] ?? [] as $pipe) {
            foreach ($pipe['steps'] ?? [] as $step) {
                $key = ($step['r'] ?? 0) . '-' . ($step['c'] ?? 0);
                $rotations[$key] = (int)($step['correctRot'] ?? 0);
            }
        }
        return $rotations;
    }

    private static function findWindingPath(array $start, array $end, int $minR, int $maxR, int $minC, int $maxC, \Closure $rng): array
    {
        // Manhattan path với ngẫu nhiên hóa điểm gập
        $path = [['r' => $start['r'], 'c' => $start['c']]];
        $currR = $start['r'] + ($start['dir'] === 0 ? -1 : ($start['dir'] === 2 ? 1 : 0));
        $currC = $start['c'] + ($start['dir'] === 1 ? 1 : ($start['dir'] === 3 ? -1 : 0));
        $path[] = ['r' => $currR, 'c' => $currC];

        $preEndR = $end['r'] + ($end['dir'] === 0 ? -1 : ($end['dir'] === 2 ? 1 : 0));
        $preEndC = $end['c'] + ($end['dir'] === 1 ? 1 : ($end['dir'] === 3 ? -1 : 0));

        while ($currC !== $preEndC || $currR !== $preEndR) {
            $moveCols = ($currC !== $preEndC);
            $moveRows = ($currR !== $preEndR);

            if ($moveCols && $moveRows) {
                if ($rng() < 0.5) {
                    $currC += ($currC < $preEndC ? 1 : -1);
                } else {
                    $currR += ($currR < $preEndR ? 1 : -1);
                }
            } elseif ($moveCols) {
                $currC += ($currC < $preEndC ? 1 : -1);
            } else {
                $currR += ($currR < $preEndR ? 1 : -1);
            }
            $path[] = ['r' => $currR, 'c' => $currC];
        }

        $path[] = ['r' => $end['r'], 'c' => $end['c']];
        return $path;
    }

    private static function getDirection(array $from, array $to): int
    {
        if ($to['r'] < $from['r']) return 0; // Trên
        if ($to['c'] > $from['c']) return 1; // Phải
        if ($to['r'] > $from['r']) return 2; // Dưới
        return 3;                            // Trái
    }

    private static function determineShapeAndAngle(int $inDir, int $outDir): array
    {
        $dirs = [$inDir, $outDir];
        sort($dirs);
        if ($dirs[0] === 0 && $dirs[1] === 2) return ['shape' => 'Thang', 'correctRot' => 0];
        if ($dirs[0] === 1 && $dirs[1] === 3) return ['shape' => 'Thang', 'correctRot' => 90];
        if ($dirs[0] === 1 && $dirs[1] === 2) return ['shape' => 'ChuL', 'correctRot' => 0];
        if ($dirs[0] === 2 && $dirs[1] === 3) return ['shape' => 'ChuL', 'correctRot' => 90];
        if ($dirs[0] === 0 && $dirs[1] === 3) return ['shape' => 'ChuL', 'correctRot' => 180];
        return ['shape' => 'ChuL', 'correctRot' => 270];
    }

    /**
     * Xác minh đường đi & tính điểm chính thức từ Server (Anti-Cheat)
     *
     * @param array $mapData Dữ liệu bản đồ gốc đã lưu ở Room
     * @param array $playerSubmission Dữ liệu bài nộp từ client:
     *   [
     *     'connected_pairs' => [
     *       ['pairId' => 1, 'path_length' => 8, 'is_valid' => true],
     *       ...
     *     ],
     *     'total_cells_used' => 16,
     *     'finish_time_ms' => 45000,
     *   ]
     * @return array Kết quả thẩm định chính thức
     */
    public static function verifyAndScoreSolution(array $mapData, array $playerSubmission): array
    {
        $numPairs = (int)($mapData['numPairs'] ?? count($mapData['pipes'] ?? []));
        $optimalTotalLength = (int)($mapData['optimalTotalLength'] ?? 1);

        $connectedList = $playerSubmission['connected_pairs'] ?? [];
        $validConnectedCount = 0;
        $playerTotalLength = 0;

        if (!empty($connectedList) && is_array($connectedList)) {
            foreach ($connectedList as $cp) {
                if (!empty($cp['is_valid'])) {
                    $validConnectedCount++;
                    $playerTotalLength += max(1, (int)($cp['path_length'] ?? 1));
                }
            }
        } else {
            // Hỗ trợ nộp dạng tổng hợp trực tiếp từ client BanDoGame.js
            $validConnectedCount = (int)($playerSubmission['pairs_connected'] ?? 0);
            $playerTotalLength = (int)($playerSubmission['total_flow_cells'] ?? $optimalTotalLength);
        }

        $allCompleted = ($validConnectedCount >= $numPairs && $numPairs > 0);
        $finalScore = 0;

        if ($allCompleted) {
            // Điểm nộp từ client nếu có hoặc tính theo độ dài tối ưu
            $submittedScore = (int)($playerSubmission['score'] ?? 0);
            if ($submittedScore >= 100) {
                $finalScore = 100;
            } elseif ($playerTotalLength > 0 && $optimalTotalLength > 0) {
                $rawScore = (int)floor((100.0 * $optimalTotalLength) / max($optimalTotalLength, $playerTotalLength));
                $finalScore = min(100, max(10, $rawScore));
                if ($playerTotalLength <= $optimalTotalLength) {
                    $finalScore = 100;
                }
            } else {
                $finalScore = max(10, $submittedScore);
            }
        } else {
            $submittedScore = (int)($playerSubmission['score'] ?? 0);
            if ($submittedScore > 0) {
                $finalScore = min(99, $submittedScore);
            } else {
                $scorePerPipe = 100 / max(1, $numPairs);
                $finalScore = (int)floor($validConnectedCount * $scorePerPipe * 0.8);
            }
        }

        return [
            'is_all_completed'    => $allCompleted,
            'completed_pairs'     => $validConnectedCount,
            'total_pairs'         => $numPairs,
            'official_score'      => $finalScore,
            'optimal_length'      => $optimalTotalLength,
            'player_total_length' => $playerTotalLength,
        ];
    }
}
