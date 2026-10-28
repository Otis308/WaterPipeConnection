<?php
namespace App\Services {
    // Tests do not append to the existing project's debug log.
    class PvpDebugLog { public static function write(...$args): void {} }
}
namespace {
spl_autoload_register(function ($class) {
    if (str_starts_with($class, 'App\\')) require_once dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
require __DIR__ . '/scoring_fixtures.php';
use App\Services\{PdoDatabaseService, BroadcastingService, PvpRoomService, PvpMatchService, PvpBoardStateService as Board, PipeScoringService as Score};
class ScoringTestBroadcast extends BroadcastingService {
    public array $events = [];
    public function __construct() {}
    public function broadcast(\App\Events\ShouldBroadcast $event): bool { $this->events[]=$event->broadcastWith(); return true; }
}
$checks=0;
function check($condition, $message): void { global $checks; $checks++; if (!$condition) throw new \RuntimeException($message); }
$temp=sys_get_temp_dir().'/pipe-integration-'.bin2hex(random_bytes(6)); mkdir($temp);
putenv('HUIT_SCORING_CACHE_DIR='.$temp);
try {
    $pdo=new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, name TEXT, email TEXT,
        pvp_rating INTEGER DEFAULT 1000, pvp_tier TEXT, pvp_matches INTEGER DEFAULT 0,
        pvp_wins INTEGER DEFAULT 0, pvp_losses INTEGER DEFAULT 0, high_score INTEGER DEFAULT 0,
        total_games INTEGER DEFAULT 0, total_wins INTEGER DEFAULT 0, coins INTEGER DEFAULT 0)');
    $pdo->exec('CREATE TABLE pvp_rooms (id INTEGER PRIMARY KEY, room_code TEXT, host_user_id INTEGER,
        room_type TEXT, max_players INTEGER, status TEXT, map_size TEXT, difficulty TEXT, num_pairs INTEGER,
        map_seed TEXT, map_config_json TEXT, started_at_ts INTEGER, ended_at_ts INTEGER, time_limit_seconds INTEGER)');
    $pdo->exec('CREATE TABLE pvp_room_players (id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER,
        user_id INTEGER, player_name TEXT, seat_number INTEGER, is_host INTEGER, is_ready INTEGER,
        initial_rating INTEGER DEFAULT 1000, connection_status TEXT DEFAULT "connected",
        current_score INTEGER DEFAULT 0, completed_pairs INTEGER DEFAULT 0, total_pairs INTEGER,
        solve_status TEXT DEFAULT "playing", finish_time_ms INTEGER, final_rank INTEGER,
        rating_change INTEGER DEFAULT 0, board_rotations_json TEXT, board_state_version INTEGER DEFAULT 0, last_heartbeat_ts INTEGER)');
    $pdo->exec('CREATE TABLE pvp_match_history (id INTEGER PRIMARY KEY, room_id INTEGER, user_id INTEGER,
        final_rank INTEGER, score INTEGER, duration_ms INTEGER, rating_before INTEGER, rating_after INTEGER,
        rating_delta INTEGER, win_status TEXT, k_factor INTEGER, UNIQUE(room_id,user_id))');
    $pdo->exec('CREATE TABLE game_levels (id INTEGER PRIMARY KEY, title TEXT)');
    $pdo->exec('CREATE TABLE user_profiles (user_id INTEGER, phone TEXT, address TEXT, birthday TEXT, preferred_mode TEXT)');
    $pdo->exec('CREATE TABLE game_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER,
        game_level_id INTEGER, player_name TEXT, game_mode TEXT, map_option TEXT, map_size TEXT, difficulty TEXT,
        result TEXT, score_earned INTEGER, connected_pipes INTEGER, steps_count INTEGER, ai_retries INTEGER,
        duration_ms INTEGER, telemetry_nosql TEXT)');
    $reflection=new ReflectionClass(PdoDatabaseService::class);
    $db=$reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('conn')->setValue($db,$pdo); $reflection->getProperty('driver')->setValue($db,'sqlite');
    $broadcast=new ScoringTestBroadcast(); $rooms=new PvpRoomService($db,$broadcast); $matches=new PvpMatchService($db,$broadcast,$rooms);
    foreach ([1,2] as $id) $pdo->prepare('INSERT INTO users(id,username,name,email,pvp_tier) VALUES(?,?,?,?,?)')
        ->execute([$id,'test'.$id,'Test '.$id,'test'.$id.'@invalid.test','Test']);
    $map=replaceScoringCell(scoringGrid(3,5,[[2,1,0,2,5,180]]),2,3,'OngXanh/OngXanh-Thang.svg',0);
    $map['_scoring']=Score::solve($map);
    function roomFixture($pdo,$map,$id,$code,$status='in_progress'): void {
        $pdo->prepare('INSERT INTO pvp_rooms(id,room_code,host_user_id,room_type,max_players,status,map_size,difficulty,num_pairs,
            map_seed,map_config_json,started_at_ts,time_limit_seconds) VALUES(?,?,1,"custom",2,?,"3x5","easy",1,"test",?,?,300)')
            ->execute([$id,$code,$status,json_encode($map),time()-5]);
        foreach([1,2] as $uid) $pdo->prepare('INSERT INTO pvp_room_players(room_id,user_id,player_name,seat_number,is_host,is_ready,total_pairs,board_rotations_json)
            VALUES(?,?,?,?,?,?,1,?)')->execute([$id,$uid,'Test '.$uid,$uid,$uid===1?1:0,1,json_encode(Board::buildInitialBoardState($map))]);
    }
    roomFixture($pdo,$map,1,'TEST01');
    $forged=$matches->submitMoveOrProgress(1,'TEST01',['score'=>999,'tentative_score'=>999,'is_winner'=>true,'completed_pairs'=>999]);
    check($forged['success'] && $forged['current_score']===44 && !$forged['is_completed'],'Client scores/flags ignored; real board scored');
    $submitted=$matches->submitSolution(2,'TEST01',['expected_version'=>0,'score'=>100,'finish_time_ms'=>1]);
    check($submitted['success'] && $submitted['is_completed'] && $submitted['official_score']===44,'Legal detour submission accepted at 44');
    $row=$pdo->query('SELECT * FROM pvp_room_players WHERE room_id=1 AND user_id=2')->fetch(PDO::FETCH_ASSOC);
    check($row['current_score']==44 && $row['finish_time_ms']>=5000,'Server score and server clock persisted');
    $command=['action'=>'rotate','row'=>2,'col'=>3,'degrees'=>90,'expected_version'=>0,'action_id'=>'rotation-1'];
    $progress=$matches->submitMoveOrProgress(1,'TEST01',$command);
    check($progress['success'] && $progress['current_score']===100 && !$progress['is_completed'],'100 allowed while playing');
    check($progress['leaderboard'][0]['user_id']===2,'Completed 44 ranks above playing 100');
    $retry=$matches->submitMoveOrProgress(1,'TEST01',$command);
    check($retry['success'] && $retry['board_state_version']===1,'Lost-response retry is idempotent');
    $bad=$matches->submitMoveOrProgress(1,'TEST01',array_replace($command,['col'=>99,'expected_version'=>1,'action_id'=>'bad-cell']));
    check(!$bad['success'],'Cannot rotate nonexistent cell');
    $early=$matches->submitSolution(1,'TEST01',['expected_version'=>0]);
    check(!$early['success'],'Stale / out-of-order submission rejected');
    $done=$matches->submitSolution(1,'TEST01',['expected_version'=>1,'score'=>0,'is_completed'=>false]);
    check($done['success'] && $done['official_score']===100,'Final action recorded before submission');
    $history=$pdo->query('SELECT user_id,final_rank,score FROM pvp_match_history ORDER BY final_rank')->fetchAll(PDO::FETCH_ASSOC);
    check(count($history)===2 && $history[0]['user_id']==1 && $history[0]['score']==100 && $history[1]['score']==44,'Final ranking and history retain actual quality');
    check($matches->submitSolution(1,'TEST01',['expected_version'=>1])['is_completed'], 'Lost final response can be retried after match finalization');
    $matches->finalizeMatch('TEST01');
    check($pdo->query('SELECT COUNT(*) FROM pvp_match_history')->fetchColumn()==2,'Finalization cannot duplicate history/rating');
    $snapshot=$matches->getPlayerReconnectSnapshot(1,'TEST01');
    $public=json_encode([$snapshot['room']['map_data'],$snapshot['player_snapshot']['map_data']]);
    check(!str_contains($public,'correctRot') && !str_contains($public,'steps') && !str_contains($public,'_scoring'),'Room and reconnect never leak solver data');
    roomFixture($pdo,$map,2,'TIME01');
    $pdo->exec('UPDATE pvp_rooms SET started_at_ts=1 WHERE id=2');
    $late=$matches->submitMoveOrProgress(1,'TIME01',$command);
    check(!$late['success'] && $late['match_ended'],'Late moves trigger server deadline finalization');
    check($pdo->query('SELECT COUNT(*) FROM pvp_match_history WHERE room_id=2 AND score=44')->fetchColumn()==2,'Timeout scores last accepted boards');
    $status=$pdo->query('SELECT solve_status,finish_time_ms FROM pvp_room_players WHERE room_id=2 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    check($status['solve_status']==='completed' && $status['finish_time_ms']==300000,'Timeout recognizes legal completed detours');
    roomFixture($pdo,$map,3,'START1','waiting');
    $start=$matches->startMatch('START1',1);
    check($start['success'],'Start initializes all player boards within the room transaction');
    check(!str_contains(json_encode($start['map_data']),'correctRot') && !str_contains(json_encode($broadcast->events),'correctRot'),'Start response and broadcasts filtered');
    $yellow=scoringGrid(2,3,[[1,1,90,2,3,180]]);
    foreach([[1,2,'Tuong.png',0],[1,3,'Tuong.png',0],
        [2,1,'OngVang/OngVang-Thang.svg',90],[2,2,'OngVang/OngVang-ChuL.svg',270]] as [$r,$c,$file,$rot]) {
        $yellow=replaceScoringCell($yellow,$r,$c,$file,$rot);
    }
    $yellow['_scoring']=Score::solve($yellow); roomFixture($pdo,$yellow,4,'SWAP01');
    check(!$matches->submitMoveOrProgress(1,'SWAP01',['action'=>'rotate','row'=>2,'col'=>1,'degrees'=>90,
        'expected_version'=>0,'action_id'=>'forbidden-yellow'])['success'],'Server forbids rotating yellow');
    check(!$matches->submitMoveOrProgress(1,'SWAP01',['action'=>'swap','rowA'=>2,'colA'=>1,'rowB'=>2,'colB'=>3,
        'expected_version'=>0,'action_id'=>'invalid-swap'])['success'],'Server rejects nonadjacent/endpoint swaps');
    $swapped=$matches->submitMoveOrProgress(1,'SWAP01',['action'=>'swap','rowA'=>2,'colA'=>1,'rowB'=>2,'colB'=>2,
        'expected_version'=>0,'action_id'=>'valid-swap']);
    check($swapped['success'] && $swapped['board_state_version']===1 && $swapped['current_score']===100
        && !$swapped['is_completed'],'Actual server swap moves both layout and rotation before scoring');
    $unknown=$map; $unknown['cells'][0]['initialRot']=360;
    $unknown['_scoring']=['version'=>Score::VERSION,'key'=>Score::configKey($unknown),'status'=>'unproven','vectors'=>[],'total'=>null];
    roomFixture($pdo,$unknown,5,'UNKN01');
    $unknownResult=$matches->submitSolution(1,'UNKN01',['expected_version'=>0,'score'=>100]);
    check($unknownResult['is_completed'] && $unknownResult['evaluation']['quality_score']===null
        && !$unknownResult['evaluation']['is_optimal'],'Unproven baseline still permits connectivity completion, with explicit unknown quality');
    $db->recordGameSession(['map_option'=>'custom','difficulty'=>'easy','result'=>'win','username'=>'test1','game_mode'=>'player','scoring_version'=>Score::VERSION,
        'quality_score'=>44,'score_earned'=>300,'total_score'=>100,'telemetry_nosql'=>['reward_points'=>100]]);
    $db->recordGameSession(['map_option'=>'custom','difficulty'=>'easy','result'=>'win','username'=>'test1','game_mode'=>'player','scoring_version'=>Score::VERSION,
        'quality_score'=>null,'score_earned'=>300,'total_score'=>200]);
    $db->recordGameSession(['map_option'=>'custom','difficulty'=>'easy','result'=>'win','username'=>'test1','game_mode'=>'player','score_earned'=>300]);
    $analytics=$db->getGameModeTelemetryAnalytics();
    check((float)$analytics[0]['avg_score']===44.0 && (float)$analytics[0]['max_score']===44.0,'Statistics exclude unknown quality and old reward scores');
    $recent=$db->getRecentSessions();
    check($recent[0]['score_label']==='300 (điểm cũ)' && $recent[1]['score_label']==='Chưa xác định' && $recent[2]['score_label']==='44/100','Session quality and legacy scores labelled separately');
    $stored=json_decode($recent[2]['telemetry_nosql'],true);
    check($recent[2]['score_earned']==44 && $stored['reward_points']===100,'Board quality and accumulated reward stored separately');
    echo "Isolated SQLite integration: $checks assertions passed\n";
} finally {
    foreach(glob($temp.'/*') as $file) unlink($file);
    rmdir($temp);
}
}
