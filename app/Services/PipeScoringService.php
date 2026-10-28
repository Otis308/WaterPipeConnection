<?php

namespace App\Services;

/** Pure, bounded proof search. Distances are edges, never number of visited cells.
 * A cross is one connected junction (the browser does not isolate its channels).
 * Adjacent swaps generate every permutation in a connected yellow component.
 */
class PipeScoringService
{
    public const VERSION = 'joint-paths-v1';
    private const DR = [-1, 0, 1, 0];
    private const DC = [0, 1, 0, -1];

    public static function shape(array $cell): string
    {
        // Swaps move the image, not the generator's old shape annotation.
        foreach (['BatDau', 'KetThuc', 'ChuThap', 'ChuL', 'Thang'] as $shape) {
            if (str_contains($cell['file'] ?? '', $shape)) return $shape;
        }
        return str_contains($cell['file'] ?? '', 'Tuong') ? '' : ($cell['shape'] ?? '');
    }

    public static function mask(string $shape, int $rotation): int
    {
        $ports = ['Thang' => [0, 2], 'ChuL' => [1, 2], 'ChuThap' => [0, 1, 2, 3],
            'BatDau' => [1], 'KetThuc' => [1]][$shape] ?? [];
        if ($rotation % 90 !== 0) return 0;
        $steps = (($rotation % 360) + 360) % 360 / 90;
        $mask = 0;
        foreach ($ports as $port) $mask |= 1 << (($port + (int)$steps) % 4);
        return $mask;
    }

    private static function model(array $map): array
    {
        $cells = $map['cells'] ?? [];
        $pipes = $map['pipes'] ?? [];
        if (!is_array($cells) || !is_array($pipes)) throw new \InvalidArgumentException('Cells/pipes must be arrays');
        if (count($cells) > 60 || !$cells || count($pipes) < 1 || count($pipes) > 4
            || count($pipes) !== ($map['numPairs'] ?? null)) throw new \InvalidArgumentException('Invalid map size/pairs');
        $nodes = [];
        foreach ($cells as $cell) {
            if (!is_array($cell) || !is_string($cell['file'] ?? '') || !is_string($cell['shape'] ?? '')
                || !is_string($cell['role'] ?? '')) throw new \InvalidArgumentException('Invalid cell data');
            $r = $cell['r'] ?? null; $c = $cell['c'] ?? null;
            $rot = $cell['initialRot'] ?? 0;
            if (!is_int($r) || !is_int($c) || $r < 1 || $r > 5 || $c < 1 || $c > 12
                || !is_int($rot) || $rot % 90 !== 0) throw new \InvalidArgumentException('Invalid cell');
            $key = "$r-$c";
            if (isset($nodes[$key])) throw new \InvalidArgumentException('Duplicate cell');
            $shape = self::shape($cell);
            $file = $cell['file'] ?? '';
            $role = $cell['role'] ?? '';
            if (!$role && $shape === 'BatDau') $role = 'start';
            if (!$role && $shape === 'KetThuc') $role = 'end';
            $nodes[$key] = ['r' => $r, 'c' => $c, 'file' => $file, 'shape' => $shape,
                'rotation' => $rot, 'role' => $role, 'pairId' => (int)($cell['pairId'] ?? 0),
                'yellow' => str_contains($file, 'OngVang'), 'masks' => []];
            $masks = [self::mask($shape, $rot)];
            if (!$role && str_contains($file, 'OngXanh')) {
                $masks = [];
                for ($angle = 0; $angle < 360; $angle += 90) $masks[] = self::mask($shape, $angle);
            }
            $nodes[$key]['masks'] = array_values(array_unique(array_filter($masks)));
        }
        ksort($nodes);
        $groups = []; $seen = [];
        foreach ($nodes as $key => $node) {
            if (!$node['yellow'] || isset($seen[$key])) continue;
            $queue = [$key]; $seen[$key] = true; $members = []; $tokens = [];
            for ($head = 0; $head < count($queue); $head++) {
                $at = $queue[$head]; $members[] = $at; $n = $nodes[$at];
                $tokens[] = self::mask($n['shape'], $n['rotation']);
                for ($d = 0; $d < 4; $d++) {
                    $next = ($n['r'] + self::DR[$d]) . '-' . ($n['c'] + self::DC[$d]);
                    if (!isset($seen[$next]) && !empty($nodes[$next]['yellow'])) {
                        $seen[$next] = true; $queue[] = $next;
                    }
                }
            }
            foreach ($members as $member) $nodes[$member]['masks'] = array_values(array_unique(array_filter($tokens)));
            $groups[] = ['members' => $members, 'tokens' => $tokens];
        }
        $pairs = []; $ids = [];
        foreach ($pipes as $pipe) {
            if (!is_array($pipe) || !is_int($pipe['pairId'] ?? null)
                || !is_array($pipe['start'] ?? null) || !is_array($pipe['end'] ?? null)) {
                throw new \InvalidArgumentException('Invalid pair data');
            }
            $id = (int)($pipe['pairId'] ?? 0);
            if ($id < 1 || isset($ids[$id])) throw new \InvalidArgumentException('Duplicate pair');
            $ids[$id] = true;
            $ends = [];
            foreach (['start', 'end'] as $role) {
                $pos = $pipe[$role] ?? [];
                $key = ($pos['r'] ?? 0) . '-' . ($pos['c'] ?? 0);
                $n = $nodes[$key] ?? [];
                if (($n['role'] ?? '') !== $role || ($n['pairId'] ?? 0) !== $id
                    || ($n['shape'] ?? '') !== ($role === 'start' ? 'BatDau' : 'KetThuc')) {
                    throw new \InvalidArgumentException('Invalid endpoint');
                }
                $ends[$role] = $key;
            }
            if ($ends['start'] === $ends['end']) throw new \InvalidArgumentException('Zero length pair');
            $pairs[] = ['id' => $id] + $ends;
        }
        return [$nodes, $pairs, $groups];
    }

    public static function configKey(array $map): string
    {
        [$nodes, $pairs] = self::model($map);
        return hash('sha256', json_encode([self::VERSION, $nodes, $pairs]));
    }

    /** Only lengths are retained. No paths, target rotations or generator solutions. */
    public static function solve(array $map, int $maxNodes = 150000, int $maxMs = 250): array
    {
        $started = hrtime(true); $visits = 0; $vectors = []; $total = null; $status = 'unproven';
        try { [$nodes, $pairs, $groups] = self::model($map); $key = self::configKey($map); }
        catch (\InvalidArgumentException $e) {
            return ['version' => self::VERSION, 'status' => 'invalid', 'error' => $e->getMessage(), 'vectors' => [], 'total' => null];
        }
        $fits = static function (string $at, int $need) use ($nodes): bool {
            foreach ($nodes[$at]['masks'] as $mask) if (($mask & $need) === $need) return true;
            return false;
        };
        $yellowFits = static function (array $needs) use ($groups): bool {
            foreach ($groups as $group) {
                $owners = [];
                $augment = function ($at, &$seen) use (&$augment, &$owners, $group, $needs): bool {
                    foreach ($group['tokens'] as $token => $mask) {
                        if (isset($seen[$token]) || (($mask & $needs[$at]) !== $needs[$at])) continue;
                        $seen[$token] = true;
                        if (!isset($owners[$token]) || $augment($owners[$token], $seen)) {
                            $owners[$token] = $at; return true;
                        }
                    }
                    return false;
                };
                foreach ($group['members'] as $at) {
                    if (empty($needs[$at])) continue;
                    $seen = [];
                    if (!$augment($at, $seen)) return false;
                }
            }
            return true;
        };
        // Independent relaxed BFS respects turns and fixed endpoint ports. It supplies
        // lower bounds only; joint DFS and yellow matching prove attainability.
        $lower = [];
        foreach ($pairs as $i => $pair) {
            $queue = [[$pair['start'], 0, 0]]; $seen = []; $distance = null;
            for ($head = 0; $head < count($queue); $head++) {
                [$at, $incoming, $dist] = $queue[$head];
                if ($at === $pair['end']) { $distance = $dist; break; }
                $n = $nodes[$at];
                for ($d = 0; $d < 4; $d++) {
                    if (!$fits($at, $incoming | (1 << $d))) continue;
                    $next = ($n['r'] + self::DR[$d]) . '-' . ($n['c'] + self::DC[$d]);
                    $entry = 1 << (($d + 2) % 4); $state = "$next:$entry";
                    if (!isset($nodes[$next]) || isset($seen[$state]) || !$fits($next, $entry)) continue;
                    if ($nodes[$next]['role'] && $next !== $pair['end']) continue;
                    $seen[$state] = true; $queue[] = [$next, $entry, $dist + 1];
                }
            }
            if ($distance === null) {
                return ['version' => self::VERSION, 'key' => $key, 'status' => 'unsatisfiable',
                    'vectors' => [], 'total' => null, 'nodes' => $visits, 'elapsed_ms' => (hrtime(true) - $started) / 1e6];
            }
            $lower[$i] = $distance;
        }
        $needs = []; $lengths = [];
        $searchPair = null;
        $searchPair = function (int $i, int $remaining) use (&$searchPair, &$needs, &$lengths, &$vectors,
            &$visits, $maxNodes, $maxMs, $started, $nodes, $pairs, $lower, $fits, $yellowFits): void {
            if ($i === count($pairs)) {
                if ($yellowFits($needs)) $vectors[implode(',', $lengths)] = $lengths;
                return;
            }
            $pair = $pairs[$i]; $end = $nodes[$pair['end']];
            $reserve = array_sum(array_slice($lower, $i + 1));
            $seen = [$pair['start'] => true];
            $walk = function (string $at, int $incoming, int $dist) use (&$walk, &$seen, &$needs, &$lengths,
                &$visits, $maxNodes, $maxMs, $started, $nodes, $pair, $end, $remaining, $reserve, $fits, $i, &$searchPair): void {
                if (++$visits > $maxNodes || (($visits & 255) === 0 && (hrtime(true) - $started) / 1e6 >= $maxMs)) {
                    throw new \RuntimeException('budget');
                }
                $n = $nodes[$at];
                if ($dist + abs($n['r'] - $end['r']) + abs($n['c'] - $end['c']) + $reserve > $remaining) return;
                $old = $needs[$at] ?? 0;
                if ($at === $pair['end']) {
                    if (!$fits($at, $old | $incoming)) return;
                    $needs[$at] = $old | $incoming; $lengths[$i] = $dist;
                    $searchPair($i + 1, $remaining - $dist);
                    $needs[$at] = $old; return;
                }
                for ($d = 0; $d < 4; $d++) {
                    $need = $old | $incoming | (1 << $d);
                    if (!$fits($at, $need)) continue;
                    $next = ($n['r'] + self::DR[$d]) . '-' . ($n['c'] + self::DC[$d]);
                    if (!isset($nodes[$next]) || isset($seen[$next])) continue;
                    if ($nodes[$next]['role'] && $next !== $pair['end']) continue;
                    $entry = 1 << (($d + 2) % 4);
                    if (!$fits($next, ($needs[$next] ?? 0) | $entry)) continue;
                    $needs[$at] = $need; $seen[$next] = true;
                    $walk($next, $entry, $dist + 1);
                    unset($seen[$next]); $needs[$at] = $old;
                }
            };
            $walk($pair['start'], 0, 0);
        };
        try {
            $upper = count($pairs) * (count($nodes) - 1);
            for ($bound = array_sum($lower); $bound <= $upper; $bound += 2) {
                $searchPair(0, $bound);
                if ($vectors) { $status = 'proven'; $total = $bound; break; }
            }
            if (!$vectors) $status = 'unsatisfiable';
        } catch (\RuntimeException $e) {
            // Even a found solution is unproven until every equal-cost vector was enumerated.
            $vectors = [];
        }
        return ['version' => self::VERSION, 'key' => $key, 'status' => $status, 'total' => $total,
            'vectors' => array_values($vectors), 'nodes' => $visits, 'elapsed_ms' => (hrtime(true) - $started) / 1e6];
    }

    public static function baseline(array $map, bool $recheck = false): array
    {
        try { $key = self::configKey($map); }
        catch (\InvalidArgumentException $e) { return self::solve($map); }
        static $memory = [];
        $directory = getenv('HUIT_SCORING_CACHE_DIR') ?: dirname(__DIR__, 2) . '/storage/app/scoring-cache';
        $path = $directory . '/' . $key . '.json';
        $cached = $memory[$key] ?? (is_file($path) ? json_decode(file_get_contents($path), true) : null);
        if (is_array($cached) && ($cached['key'] ?? '') === $key
            && (!$recheck || !empty($cached['rechecked']))) return $memory[$key] = $cached;
        $stored = $map['_scoring'] ?? [];
        if (!$recheck && ($stored['key'] ?? '') === $key && ($stored['version'] ?? '') === self::VERSION) return $stored;
        $result = self::solve($map);
        if ($recheck) $result['rechecked'] = true;
        if (!is_dir($directory)) @mkdir($directory, 0775, true);
        // Readers never see a partial certificate, including across PHP workers.
        $temp = @tempnam($directory, 'proof-');
        if ($temp !== false) {
            file_put_contents($temp, json_encode($result));
            if (!@rename($temp, $path)) @unlink($temp);
        }
        return $memory[$key] = $result;
    }

    public static function score(array $lengths, array $baseline): array
    {
        $connected = count(array_filter($lengths, static fn($n) => is_int($n) && $n > 0));
        $base = ['quality_score' => null, 'official_score' => 0, 'raw_score' => null,
            'is_optimal' => false, 'score_status' => $baseline['status'] ?? 'unproven'];
        if ($lengths && !array_filter($lengths, static fn($n) => $n !== null)
            && ($baseline['status'] ?? '') !== 'invalid') {
            return array_replace($base, ['quality_score' => 0, 'raw_score' => 0.0]);
        }
        if (!$lengths || ($baseline['status'] ?? '') !== 'proven') return $base;
        $best = null; $selected = null;
        foreach ($baseline['vectors'] ?? [] as $vector) {
            if (count($vector) !== count($lengths)) continue;
            $sum = 0.0;
            foreach ($lengths as $i => $length) {
                if (!is_int($vector[$i]) || $vector[$i] <= 0) continue 2;
                if ($length === null) continue;
                if (!is_int($length) || $length <= 0) return array_replace($base, ['score_status' => 'invalid_lengths']);
                // Never disguise a shorter path by clamping the ratio.
                if ($length < $vector[$i]) continue 2;
                $sum += 100.0 * ($vector[$i] / $length) ** 2;
            }
            $raw = $sum / count($lengths);
            if ($best === null || $raw > $best) { $best = $raw; $selected = $vector; }
        }
        if ($best === null) return array_replace($base, ['score_status' => 'incomparable_lengths']);
        return ['quality_score' => (int)round($best), 'official_score' => (int)round($best),
            'raw_score' => $best, 'is_optimal' => $connected === count($lengths) && $lengths === $selected,
            'score_status' => 'proven'];
    }

    public static function comparePlayers(array $a, array $b): int
    {
        $doneA = ($a['solve_status'] ?? '') === 'completed';
        $doneB = ($b['solve_status'] ?? '') === 'completed';
        if ($doneA !== $doneB) return $doneB <=> $doneA;
        $score = (int)$b['current_score'] <=> (int)$a['current_score'];
        if ($score) return $score;
        if ($doneA) {
            $time = (int)($a['finish_time_ms'] ?? PHP_INT_MAX) <=> (int)($b['finish_time_ms'] ?? PHP_INT_MAX);
            if ($time) return $time;
        }
        return (int)$a['user_id'] <=> (int)$b['user_id'];
    }
}
