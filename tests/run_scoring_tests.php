<?php
// No vendor/bootstrap, no production service constructor, no production data.
spl_autoload_register(function ($class) {
    if (str_starts_with($class, 'App\\')) require_once dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
require __DIR__ . '/scoring_fixtures.php';
use App\Services\PipeScoringService as Score;
use App\Services\PvpBoardStateService as Board;
use App\Services\PvpMapService as Maps;
$checks = 0;
function check($condition, string $message): void {
    global $checks; $checks++;
    if (!$condition) throw new RuntimeException($message);
}
function certified(array $map): array { $map['_scoring'] = Score::solve($map); return $map; }
$temp = sys_get_temp_dir() . '/pipe-score-tests-' . bin2hex(random_bytes(6));
mkdir($temp); putenv('HUIT_SCORING_CACHE_DIR=' . $temp);
try {
    $map = scoringGrid(3, 5, [[2, 1, 0, 2, 5, 180]]);
    $map = certified(replaceScoringCell($map, 2, 3, 'OngXanh/OngXanh-Thang.svg', 0));
    check($map['_scoring']['status'] === 'proven' && $map['_scoring']['total'] === 4, 'Rotatable shape optimum');
    $state = Board::buildInitialBoardState($map);
    $detour = Board::verifyBoardSolution($map, $state);
    check($detour['is_all_completed'] && $detour['pairs_result'][0]['path_length'] === 6, 'Legal detour completes with edge distance');
    check($detour['official_score'] === 44 && abs($detour['raw_score'] - 100 * (4/6)**2) < 1e-9 && !$detour['is_optimal'], 'Squared ratio, one rounding');
    $state['rotations']['2-3'] = 90;
    $optimal = Board::verifyBoardSolution($map, $state);
    check($optimal['quality_score'] === 100 && $optimal['is_optimal'], 'Optimal path ignores extra flowing branches');
    $blocked = $state; $blocked['rotations']['2-1'] = 180;
    check(!Board::verifyBoardSolution($map, $blocked)['is_all_completed'], 'Disconnected / wrong endpoint ports');
    $wrong = $map; $wrong['cells'][9]['pairId'] = 2;
    check(!Board::verifyBoardSolution($wrong, $state)['is_all_completed'], 'Wrong destination pair');
    foreach ([2, 3, 4] as $n) {
        $ends = [];
        for ($r=1; $r <= $n; $r++) $ends[] = [$r,1,0,$r,5,180];
        $multi = certified(scoringGrid($n, 5, $ends));
        $res = Board::verifyBoardSolution($multi, Board::buildInitialBoardState($multi));
        check($res['is_all_completed'] && $res['official_score'] === 100 && $res['is_optimal'], "$n pairs never round to 99");
    }
    $cross = scoringGrid(5, 5, [[3,1,0,3,5,180], [1,3,90,5,3,270]]);
    $cross = certified(replaceScoringCell($cross, 3,3,'OngXanh/OngXanh-Thang.svg',0));
    $vectors = $cross['_scoring']['vectors']; sort($vectors);
    check($cross['_scoring']['status'] === 'proven' && $cross['_scoring']['total'] === 10, 'Joint conflicts prevent invalid independent sum 8');
    check($vectors === [[4,6],[6,4]], 'Every equivalent minimum vector retained');
    foreach ([0,90] as $rot) {
        $state = Board::buildInitialBoardState($cross); $state['rotations']['3-3'] = $rot;
        $res = Board::verifyBoardSolution($cross, $state);
        check($res['is_all_completed'] && $res['is_optimal'] && $res['official_score'] === 100, 'Different equivalent optimal paths both score 100');
    }
    $unknown = Score::score([3,10], ['status'=>'proven','vectors'=>[[5,6]],'total'=>11]);
    check($unknown['quality_score'] === null && $unknown['score_status'] === 'incomparable_lengths', 'Incomparable lengths explicitly unknown');
    check(Score::score([0], ['status'=>'proven','vectors'=>[[4]]])['score_status'] === 'invalid_lengths', 'Zero length invalid');
    check(Score::score([null], $map['_scoring'])['official_score'] === 0, 'Unconnected pair scores zero');
    check(Score::score([null], ['status'=>'unproven'])['quality_score'] === 0, 'All unconnected scores zero even without a baseline');
    $near = Score::score([1001], ['status'=>'proven','vectors'=>[[1000]]]);
    check($near['quality_score'] === 100 && !$near['is_optimal'], 'Rounded 100 does not imply optimal');
    check(Score::solve($cross,0,0)['status'] === 'unproven', 'Budget exhaustion never certifies optimum');
    check(Score::solve(['numPairs'=>1,'pipes'=>[],'cells'=>[]])['status'] === 'invalid', 'Invalid map is explicit');
    check(Score::solve(['numPairs'=>1,'pipes'=>[],'cells'=>'invalid'])['status'] === 'invalid', 'Malformed arrays do not throw');
    $malformed=$map; $malformed['cells'][0]['file']=[];
    check(!Board::verifyBoardSolution($malformed,[])['is_all_completed'], 'Malformed map cell is rejected explicitly');
    $changed = $map; $changed['cells'][0]['initialRot'] = 90;
    check(Score::configKey($changed) !== Score::configKey($map), 'Initial orientations are part of cache key');
    $changed = replaceScoringCell($map,1,1,'Tuong.png',0);
    check(Score::configKey($changed) !== Score::configKey($map), 'Walls and topology are part of cache key');
    $cached = Score::baseline($changed); $again = Score::baseline($changed);
    check($again === $cached && count(glob($temp . '/*.json')) > 0, 'Configuration cache reused');
    $stale = $map; $stale['_scoring']['vectors'] = [[8]];
    $state = Board::buildInitialBoardState($stale); $state['rotations']['2-3']=90;
    check(Board::verifyBoardSolution($stale,$state)['quality_score'] === 100, 'A shorter valid path triggers certificate recheck, no clamping');
    // Exact enumeration oracle on tiny boards, independent of the path search:
    // all reachable green rotations and all yellow permutations are evaluated by BFS.
    for ($seed = 0; $seed < 8; $seed++) {
        $tiny = scoringGrid(2,3,[[1,1,90,2,3,180]]);
        $variables=[];
        foreach ($tiny['cells'] as $i => &$cell) if (empty($cell['role'])) {
            $shape = (($i + $seed) % 3 === 0) ? 'Thang' : 'ChuL';
            $cell['file'] = "OngXanh/OngXanh-$shape.svg";
            $variables[] = $cell['r'].'-'.$cell['c'];
        }
        unset($cell);
        $proof = Score::solve($tiny); $tiny['_scoring'] = $proof;
        $minimum = null;
        for ($assignment=0; $assignment < 4**count($variables); $assignment++) {
            $state=Board::buildInitialBoardState($tiny); $code=$assignment;
            foreach ($variables as $at) { $state['rotations'][$at]=($code%4)*90; $code=intdiv($code,4); }
            $res=Board::verifyBoardSolution($tiny,$state);
            if ($res['is_all_completed']) $minimum=min($minimum ?? PHP_INT_MAX,$res['player_length']);
        }
        check(($minimum === null && $proof['status']==='unsatisfiable') || ($minimum === $proof['total'] && $proof['status']==='proven'), 'Rotation enumeration oracle '.$seed);
    }
    $yellow = scoringGrid(1,4,[[1,1,0,1,4,180]]);
    $yellow = replaceScoringCell($yellow,1,2,'OngVang/OngVang-Thang.svg',90);
    $yellow = replaceScoringCell($yellow,1,3,'OngVang/OngVang-Thang.svg',0);
    check(Score::solve($yellow)['status']==='unsatisfiable', 'Yellow matching cannot reuse the single horizontal token for both cells');
    $yellow = replaceScoringCell($yellow,1,3,'OngVang/OngVang-Thang.svg',90);
    check(Score::solve($yellow)['total']===3, 'Yellow fixed orientations are feasible');
    $red = replaceScoringCell($yellow,1,2,'OngDo/OngDo-Thang.svg',0);
    check(Score::solve($red)['status']==='unsatisfiable', 'Locked red cannot rotate');
    $swappable = scoringGrid(2,3,[[1,1,90,2,3,180]]);
    $swappable = replaceScoringCell($swappable,1,2,'Tuong.png',0);
    $swappable = replaceScoringCell($swappable,1,3,'Tuong.png',0);
    $swappable = replaceScoringCell($swappable,2,1,'OngVang/OngVang-Thang.svg',90);
    $swappable = replaceScoringCell($swappable,2,2,'OngVang/OngVang-ChuL.svg',270);
    $swappable=certified($swappable);
    check($swappable['_scoring']['total']===3,'Proof can move yellow pieces instead of rotating them');
    $state=Board::buildInitialBoardState($swappable);
    check(!Board::verifyBoardSolution($swappable,$state)['is_all_completed'],'Unswapped yellow layout is not yet connected');
    foreach(['rotations','layout'] as $field) {
        $value=$state[$field]['2-1']; $state[$field]['2-1']=$state[$field]['2-2']; $state[$field]['2-2']=$value;
    }
    check(Board::verifyBoardSolution($swappable,$state)['is_optimal'],'Reachable adjacent yellow swap realizes proved optimum');
    $public = Maps::publicMapPayload($map + ['private'=>'never']);
    $json=json_encode($public);
    check(!str_contains($json,'correctRot') && !str_contains($json,'steps') && !str_contains($json,'vectors') && !str_contains($json,'_scoring'), 'PvP public payload excludes solutions and cache');
    $players = [
        ['user_id'=>8,'solve_status'=>'playing','current_score'=>100],
        ['user_id'=>4,'solve_status'=>'completed','current_score'=>44,'finish_time_ms'=>5000],
        ['user_id'=>3,'solve_status'=>'completed','current_score'=>44,'finish_time_ms'=>3000],
        ['user_id'=>2,'solve_status'=>'completed','current_score'=>44,'finish_time_ms'=>3000],
        ['user_id'=>1,'solve_status'=>'completed','current_score'=>80,'finish_time_ms'=>9000],
    ];
    usort($players,[Score::class,'comparePlayers']);
    check(array_column($players,'user_id')===[1,2,3,4,8], 'Completed, score, completion time, stable user ID ranking');
    echo "Pure scoring: $checks assertions passed\n";
    if (in_array('--benchmark', $argv, true)) {
        foreach (['3x8','5x8','5x12'] as $size) foreach (['easy','medium','hard'] as $difficulty) foreach ([2,3,4] as $pairs) {
            $generated=Maps::generateMap($size,$difficulty,$pairs,"SCORING_BENCH_{$size}_{$difficulty}_$pairs");
            $proof=Score::solve($generated);
            printf("PvP %s %s P%d: %s %.2f ms %d nodes\n",$size,$difficulty,$pairs,$proof['status'],$proof['elapsed_ms'],$proof['nodes']);
        }
    }
    if (in_array('--solo', $argv, true)) {
        $maps=json_decode(preg_replace('/^\xEF\xBB\xBF/', '', stream_get_contents(STDIN)),true,512,JSON_THROW_ON_ERROR);
        foreach($maps as $fixture) {
            $proof=Score::solve($fixture['map']);
            printf("%s: %s %.2f ms %d nodes\n",$fixture['label'],$proof['status'],$proof['elapsed_ms']??0,$proof['nodes']??0);
        }
    }
} finally {
    foreach (glob($temp.'/*') as $file) unlink($file);
    rmdir($temp);
}
