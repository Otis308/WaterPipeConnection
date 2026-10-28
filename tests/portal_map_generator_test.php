<?php
// Pure checks only: deliberately bypass application bootstrap and PDO constructors.
require_once __DIR__ . '/../app/Services/PvpMapService.php';
require_once __DIR__ . '/../app/Services/PipeScoringService.php';
require_once __DIR__ . '/../app/Services/PortalMapGeneratorService.php';

use App\Services\PortalMapGeneratorService as Generator;
use App\Services\PipeScoringService as Scoring;

$checks = 0;
function check(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
foreach (['2x4' => 2, '3x8' => 4, '5x12' => 4, '4x2' => 2] as $size => $pairs) {
    foreach (['easy', 'medium', 'hard'] as $difficulty) {
        if ($difficulty === 'hard' && in_array($size, ['2x4', '4x2'])) continue;
        for ($seed = 0; $seed < 6; $seed++) {
            $result = Generator::generateMap($size, $difficulty, $pairs, "TEST_$seed");
            $map = $result['map']; $cells = [];
            foreach ($map['cells'] as $cell) $cells[$cell['r'] . '-' . $cell['c']] = $cell;
            [$rows, $cols] = array_map('intval', explode('x', $size));
            check(count($cells) === $rows * $cols, 'Missing/duplicate cells');
            check(count(array_filter($cells, fn($c) => $c['role'] === 'start')) === $pairs, 'Start count');
            check(count(array_filter($cells, fn($c) => $c['role'] === 'end')) === $pairs, 'End count');
            check($result['verification']['solvable'], 'Witness rejected');
            $counts = $result['verification']['counts'];
            check($difficulty === 'easy' ? $counts['yellow'] === 0 && $counts['red'] === 0 : $counts['yellow'] >= 2 * $pairs, 'Difficulty colours');
            check($difficulty !== 'hard' || $counts['red'] >= $pairs, 'Hard missing red');
            foreach ($result['solution']['moves'] as $move) {
                if ($move['action'] === 'rotate') {
                    check(str_contains($cells[$move['at']]['file'], 'OngXanh/'), 'Rotating fixed pipe');
                    $cells[$move['at']]['initialRot'] = ($cells[$move['at']]['initialRot'] + $move['degrees']) % 360;
                } else {
                    $a = $move['a']; $b = $move['b'];
                    check(str_contains($cells[$a]['file'], 'OngVang/') && str_contains($cells[$b]['file'], 'OngVang/'), 'Swapping non-yellow');
                    check(abs($cells[$a]['r'] - $cells[$b]['r']) + abs($cells[$a]['c'] - $cells[$b]['c']) === 1, 'Non-adjacent swap');
                    foreach (['file', 'shape', 'initialRot'] as $field) [$cells[$a][$field], $cells[$b][$field]] = [$cells[$b][$field], $cells[$a][$field]];
                }
            }
            // Independent BFS on the replayed board, not the generator's stored paths.
            foreach ($map['pipes'] as $pipe) {
                $start = $pipe['start']['r'] . '-' . $pipe['start']['c'];
                $end = $pipe['end']['r'] . '-' . $pipe['end']['c'];
                $queue = [$start]; $seen = [$start => true];
                for ($head = 0; $head < count($queue); $head++) {
                    $at = $cells[$queue[$head]];
                    foreach ([[-1,0], [0,1], [1,0], [0,-1]] as $d => [$dr,$dc]) {
                        $next = ($at['r'] + $dr) . '-' . ($at['c'] + $dc);
                        if (isset($seen[$next]) || !isset($cells[$next])) continue;
                        $other = $cells[$next];
                        if ($other['role'] && $next !== $end) continue;
                        if ((Scoring::mask($at['shape'], $at['initialRot']) & (1 << $d))
                            && (Scoring::mask($other['shape'], $other['initialRot']) & (1 << (($d + 2) % 4)))) {
                            $seen[$next] = true; $queue[] = $next;
                        }
                    }
                }
                check(isset($seen[$end]), 'Replay BFS failed');
            }
            if ($seed === 0) {
                $repeat = Generator::generateMap($size, $difficulty, $pairs, 'TEST_0');
                check($repeat['map'] === $map && $repeat['solution'] === $result['solution'], 'Non-deterministic map');
            }
        }
    }
}
foreach ([['1x1','easy',1,'x'], ['5x13','easy',1,'x'], ['3x8','other',1,'x'],
    ['3x8','hard',0,'x'], ['3x8','easy',1,''], ['2x4','hard',2,'x']] as $args) {
    try { Generator::generateMap(...$args); throw new RuntimeException('Invalid input accepted'); }
    catch (InvalidArgumentException $e) { check(true, 'Rejected invalid input'); }
}
echo "$checks assertions passed; no database/bootstrap/cache used.\n";
