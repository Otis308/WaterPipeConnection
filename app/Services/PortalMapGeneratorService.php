<?php

namespace App\Services;

/**
 * Standalone portal generator: no database, HTTP, session or cache writes.
 * DFS adapted from BanDoGame::timDuongUonLuonZicZacDFS; colour rules from
 * taoDuLieuMotBanDo. Existing generators and browser algorithms stay intact.
 */
final class PortalMapGeneratorService
{
    public const VERSION = 'portal-map-v1';
    private const DR = [-1, 0, 1, 0];
    private const DC = [0, 1, 0, -1];

    /**
     * Returns map (safe preview), verification, and a PRIVATE solution witness.
     * Never send solution to a player endpoint. solve() proves optimality separately;
     * unproven means budget exhausted, not that the generated map is unsolvable.
     */
    public static function generateMap(string $mapSizeKey, string $difficulty, int $numPairs, string $seedKey): array
    {
        if (!preg_match('/^([1-5])x([1-9]|1[0-2])$/D', $mapSizeKey, $match)
            || !in_array($difficulty, ['easy', 'medium', 'hard'], true)
            || $numPairs < 1 || $numPairs > 4 || trim($seedKey) === '' || strlen($seedKey) > 256) {
            throw new \InvalidArgumentException('Invalid size, difficulty, pair count or seed (1–256 bytes).');
        }
        $rows = (int)$match[1]; $cols = (int)$match[2];
        // Each independent region needs at least 2x2 for endpoints and yellow pairs.
        $vertical = $cols >= 2 * $numPairs && $rows >= 2;
        if (!$vertical && !($rows >= 2 * $numPairs && $cols >= 2)) {
            throw new \InvalidArgumentException('Not enough space: each pair needs a region at least 2x2.');
        }
        // Reuse the pure public seeded RNG; no PDO service is instantiated.
        $rng = PvpMapService::createRng(self::VERSION . ':' . $seedKey);
        $minR = intdiv(5 - $rows, 2) + 1; $minC = intdiv(12 - $cols, 2) + 1;
        $cells = []; $pipes = []; $paths = [];
        for ($p = 0; $p < $numPairs; $p++) {
            $span = $vertical ? $cols : $rows;
            $base = intdiv($span, $numPairs); $extra = $span % $numPairs;
            $offset = $p * $base + min($p, $extra); $width = $base + ($p < $extra ? 1 : 0);
            $r0 = $minR + ($vertical ? 0 : $offset);
            $c0 = $minC + ($vertical ? $offset : 0);
            $r1 = $vertical ? $minR + $rows - 1 : $r0 + $width - 1;
            $c1 = $vertical ? $c0 + $width - 1 : $minC + $cols - 1;
            if ($difficulty === 'hard' && ($r1 - $r0 + 1) * ($c1 - $c0 + 1) < 6) {
                throw new \InvalidArgumentException('Hard needs at least 6 cells per region for two yellow pipes and a red pipe.');
            }
            $path = self::findPath($r0, $c0, $r1, $c1, $difficulty, $rng);
            $paths[] = $path;
            $last = count($path) - 1;
            foreach ($path as $i => $pos) {
                $role = $i === 0 ? 'start' : ($i === $last ? 'end' : '');
                if ($role !== '') {
                    $direction = self::direction($pos, $path[$i === 0 ? 1 : $last - 1]);
                    $shape = $role === 'start' ? 'BatDau' : 'KetThuc';
                    $rot = (($direction + 3) % 4) * 90;
                    $file = 'Ong' . $shape . '.svg';
                } else {
                    [$shape, $rot] = self::shapeAndAngle(self::direction($pos, $path[$i - 1]), self::direction($pos, $path[$i + 1]));
                    // Consecutive yellow cells guarantee a legal swap partner.
                    $color = $difficulty !== 'easy' && $i <= 2 ? 'OngVang' : 'OngXanh';
                    if ($difficulty === 'hard' && $i > 2 && ($i === 3 || $rng() < 0.22)) $color = 'OngDo';
                    $file = "$color/$color-$shape.svg";
                }
                $cells[self::key($pos)] = $pos + ['shape' => $shape, 'file' => $file,
                    'initialRot' => $rot, 'pairId' => $p + 1, 'role' => $role];
            }
            $pipes[] = ['pairId' => $p + 1, 'color' => PvpMapService::PIPE_PAIR_COLORS[$p],
                'start' => $path[0] + ['rot' => $cells[self::key($path[0])]['initialRot']],
                'end' => $path[$last] + ['rot' => $cells[self::key($path[$last])]['initialRot']]];
        }
        // Fill unused cells without disturbing the constructed solution.
        for ($r = $minR; $r < $minR + $rows; $r++) {
            for ($c = $minC; $c < $minC + $cols; $c++) {
                $key = "$r-$c";
                if (isset($cells[$key])) continue;
                $wall = $difficulty === 'hard' && $rng() < 0.15;
                $shape = ['Thang', 'ChuL', 'ChuThap'][(int)floor($rng() * 3)];
                $color = $difficulty === 'hard' && $rng() < 0.22 ? 'OngDo' : 'OngXanh';
                $cells[$key] = ['r' => $r, 'c' => $c, 'shape' => $wall ? '' : $shape,
                    'file' => $wall ? 'Tuong.png' : "$color/$color-$shape.svg",
                    'initialRot' => (int)floor($rng() * 4) * 90, 'role' => ''];
            }
        }
        ksort($cells);
        $solved = $cells; $undo = [];
        foreach ($cells as $key => &$cell) {
            if (str_contains($cell['file'], 'OngXanh/')) {
                $degrees = [90, 180, 270][(int)floor($rng() * 3)];
                $cell['initialRot'] = ($cell['initialRot'] + $degrees) % 360;
                $undo[] = ['action' => 'rotate', 'at' => $key, 'degrees' => 360 - $degrees];
            }
        }
        unset($cell);
        // Legal adjacent swaps only. Swap image, shape and rotation as one token;
        // coordinates and pair metadata remain properties of their board positions.
        foreach ($paths as $path) {
            for ($i = 1; $i < count($path) - 2; $i++) {
                $a = self::key($path[$i]); $b = self::key($path[$i + 1]);
                if (str_contains($cells[$a]['file'], 'OngVang/') && str_contains($cells[$b]['file'], 'OngVang/')) {
                    self::swap($cells, $a, $b);
                    $undo[] = ['action' => 'swap', 'a' => $a, 'b' => $b];
                }
            }
        }
        $undo = array_reverse($undo);
        $restored = $cells;
        foreach ($undo as $move) {
            if ($move['action'] === 'swap') self::swap($restored, $move['a'], $move['b']);
            else $restored[$move['at']]['initialRot'] = ($restored[$move['at']]['initialRot'] + $move['degrees']) % 360;
        }
        if ($restored !== $solved || !self::pathsConnected($restored, $paths)) {
            throw new \LogicException('Generated solution witness failed verification.');
        }
        $map = ['generatorVersion' => self::VERSION, 'seedKey' => $seedKey, 'mapSizeKey' => $mapSizeKey,
            'sizeKey' => $mapSizeKey, 'difficulty' => $difficulty, 'numPairs' => $numPairs,
            'thinkingTimeMs' => 300000, 'pipes' => $pipes, 'cells' => array_values($cells)];
        $proof = PipeScoringService::solve($map);
        if (in_array($proof['status'], ['invalid', 'unsatisfiable'], true)) {
            throw new \LogicException('Independent solver rejected generated map: ' . $proof['status']);
        }
        $counts = ['green' => 0, 'yellow' => 0, 'red' => 0, 'wall' => 0];
        foreach ($cells as $cell) {
            foreach (['green' => 'OngXanh/', 'yellow' => 'OngVang/', 'red' => 'OngDo/', 'wall' => 'Tuong'] as $name => $needle) {
                if (str_contains($cell['file'], $needle)) $counts[$name]++;
            }
        }
        return ['map' => $map, 'verification' => ['solvable' => true, 'method' => 'legal_inverse_moves_and_port_check',
            'optimality' => $proof, 'counts' => $counts],
            'solution' => ['paths' => $paths, 'moves' => $undo]];
    }

    /** Bounded randomized DFS/backtracking, adapted from the existing JS DFS. */
    private static function findPath(int $r0, int $c0, int $r1, int $c1, string $difficulty, \Closure $rng): array
    {
        $start = ['r' => $r0, 'c' => $c0]; $end = ['r' => $r1, 'c' => $c1];
        $best = []; $bestScore = -1; $visits = 0; $seen = []; $stack = [];
        // Adjacent endpoints on 2x2 allow two interior yellow cells.
        if ($difficulty !== 'easy' && $r1 === $r0 + 1 && $c1 === $c0 + 1) $end = ['r' => $r0, 'c' => $c1];
        $walk = function (array $at, int $turns, ?int $previous) use (&$walk, &$best, &$bestScore, &$visits, &$seen, &$stack, $r0, $c0, $r1, $c1, $end, $difficulty, $rng): void {
            if (++$visits > 6000) return;
            $key = self::key($at); $seen[$key] = true; $stack[] = $at;
            if ($at === $end) {
                $score = $difficulty === 'easy' ? -count($stack) : $turns * 10 + count($stack);
                if ($score > $bestScore || !$best) { $best = $stack; $bestScore = $score; }
            } else {
                $directions = [0, 1, 2, 3];
                for ($i = 3; $i > 0; $i--) { $j = (int)floor($rng() * ($i + 1)); [$directions[$i], $directions[$j]] = [$directions[$j], $directions[$i]]; }
                foreach ($directions as $d) {
                    $next = ['r' => $at['r'] + self::DR[$d], 'c' => $at['c'] + self::DC[$d]];
                    if ($next['r'] < $r0 || $next['r'] > $r1 || $next['c'] < $c0 || $next['c'] > $c1 || isset($seen[self::key($next)])) continue;
                    $walk($next, $turns + (int)($previous !== null && $previous !== $d), $d);
                }
            }
            array_pop($stack); unset($seen[$key]);
        };
        $walk($start, 0, null);
        if (!$best) throw new \RuntimeException('DFS budget exhausted before finding a path.');
        return $best;
    }

    private static function key(array $cell): string { return $cell['r'] . '-' . $cell['c']; }

    /** Adapted from PvpMapService::getDirection/determineShapeAndAngle. */
    private static function direction(array $from, array $to): int
    {
        if ($to['r'] < $from['r']) return 0;
        if ($to['c'] > $from['c']) return 1;
        return $to['r'] > $from['r'] ? 2 : 3;
    }

    private static function shapeAndAngle(int $a, int $b): array
    {
        $dirs = [$a, $b]; sort($dirs);
        return match (implode(',', $dirs)) {
            '0,2' => ['Thang', 0], '1,3' => ['Thang', 90],
            '1,2' => ['ChuL', 0], '2,3' => ['ChuL', 90],
            '0,3' => ['ChuL', 180], default => ['ChuL', 270],
        };
    }

    private static function swap(array &$cells, string $a, string $b): void
    {
        $one = $cells[$a]; $two = $cells[$b];
        if (!str_contains($one['file'], 'OngVang/') || !str_contains($two['file'], 'OngVang/')
            || abs($one['r'] - $two['r']) + abs($one['c'] - $two['c']) !== 1) {
            throw new \LogicException('Illegal yellow swap.');
        }
        foreach (['file', 'shape', 'initialRot'] as $field) {
            [$cells[$a][$field], $cells[$b][$field]] = [$cells[$b][$field], $cells[$a][$field]];
        }
    }

    private static function pathsConnected(array $cells, array $paths): bool
    {
        foreach ($paths as $path) {
            for ($i = 0; $i < count($path) - 1; $i++) {
                $a = $cells[self::key($path[$i])]; $b = $cells[self::key($path[$i + 1])];
                $d = self::direction($a, $b);
                if (!(PipeScoringService::mask($a['shape'], $a['initialRot']) & (1 << $d))
                    || !(PipeScoringService::mask($b['shape'], $b['initialRot']) & (1 << (($d + 2) % 4)))) return false;
            }
        }
        return true;
    }
}
