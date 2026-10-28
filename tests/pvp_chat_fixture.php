<?php
// Isolated fixture only: no production DB constructor, no real sessions/accounts.
namespace App\Services {
    class PvpDebugLog { public static function write(...$args): void {} }
}
namespace {
require_once dirname(__DIR__) . '/vendor/autoload.php';
use App\Services\{PdoDatabaseService, PvpChatService, PvpMatchService, PvpRoomService, BroadcastingService};
class ChatFixtureBroadcast extends BroadcastingService {
    public function __construct() {}
    public function broadcast(\App\Events\ShouldBroadcast $event): bool { return true; }
}
$config = PHP_SAPI === 'cli' ? json_decode(base64_decode($argv[1]), true) : CHAT_FIXTURE;
$pdo = new PDO('sqlite:' . $config['db']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA busy_timeout=10000');
putenv('HUIT_SCORING_CACHE_DIR=' . dirname($config['db']) . '/cache');
$reflection = new ReflectionClass(PdoDatabaseService::class);
$db = $reflection->newInstanceWithoutConstructor();
$reflection->getProperty('conn')->setValue($db, $pdo);
$reflection->getProperty('driver')->setValue($db, 'sqlite');
$broadcast = new ChatFixtureBroadcast();
$rooms = new PvpRoomService($db, $broadcast);
$matches = new PvpMatchService($db, $broadcast, $rooms);
$chat = new PvpChatService($pdo);
if (PHP_SAPI === 'cli') {
    $action = $config['action'];
    if ($action === 'setup') {
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, name TEXT, email TEXT,
            pvp_rating INTEGER DEFAULT 1000, pvp_tier TEXT DEFAULT "Bạc", pvp_matches INTEGER DEFAULT 0,
            pvp_wins INTEGER DEFAULT 0, pvp_losses INTEGER DEFAULT 0, high_score INTEGER DEFAULT 0)');
        $pdo->exec('CREATE TABLE pvp_rooms (id INTEGER PRIMARY KEY, room_code TEXT, host_user_id INTEGER,
            room_type TEXT, max_players INTEGER, current_players INTEGER, status TEXT, map_size TEXT, difficulty TEXT, num_pairs INTEGER,
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
        foreach ([1,2,3] as $uid) $pdo->prepare('INSERT INTO users (id,username,name) VALUES (?,?,?)')->execute([$uid, 'fixture'.$uid, 'Người thử '.$uid]);
        $pdo->exec('INSERT INTO pvp_rooms (id,room_code,host_user_id,room_type,max_players,current_players,status,map_size,difficulty,num_pairs,time_limit_seconds)
            VALUES (1,"CHAT01",1,"custom",2,2,"waiting","3x8","easy",2,3600)');
        foreach ([1,2] as $uid) $pdo->prepare('INSERT INTO pvp_room_players (room_id,user_id,player_name,seat_number,is_host,is_ready,total_pairs)
            VALUES (1,?,?,?,?,1,2)')->execute([$uid, 'Người thử '.$uid, $uid, $uid===1?1:0]);
        $result = $matches->startMatch('CHAT01', 1);
        if (!$result['success']) throw new RuntimeException('Fixture match failed');
        echo json_encode(['map_data' => $result['map_data']]);
    } elseif ($action === 'session') {
        session_write_close(); session_id($config['sid']); session_start();
        $_SESSION = ['_token' => $config['csrf'], 'huit_auth_user' => ['id' => $config['uid'], 'username' => 'fixture'.$config['uid'], 'name' => 'Fixture', 'role' => 'user']];
        session_write_close(); echo '{}';
    } elseif ($action === 'destroy-session') {
        session_write_close(); session_id($config['sid']); session_start(); $_SESSION=[]; session_destroy(); echo '{}';
    } elseif ($action === 'age') {
        $pdo->exec('UPDATE pvp_chat_messages SET created_at_ms = 1'); echo '{}';
    } elseif ($action === 'finish') {
        $result=$matches->finalizeMatch('CHAT01');
        if (!$result['success']) throw new RuntimeException('Fixture finalize failed: '.$result['message']);
        echo '{}';
    } elseif ($action === 'expire') {
        $pdo->exec('UPDATE pvp_rooms SET started_at_ts = 1'); echo '{}';
    } elseif ($action === 'restart') {
        // Existing score history is unique by room/user. Reset only this private
        // test table between independent game scenarios, retaining all chat history.
        $pdo->exec('DELETE FROM pvp_match_history');
        $pdo->exec('UPDATE pvp_rooms SET status="waiting"');
        $result = $matches->startMatch('CHAT01',1);
        echo json_encode(['map_data' => $result['map_data']]);
    } elseif ($action === 'history') {
        $id = json_decode($pdo->query('SELECT map_config_json FROM pvp_rooms WHERE id=1')->fetchColumn(), true)['chat_match_id'];
        for ($i=0; $i<60; $i++) {
            $pdo->exec('UPDATE pvp_chat_messages SET created_at_ms=1');
            $chat->sendMessage(1,$id,'history '.$i,bin2hex(random_bytes(16)));
        }
        echo '{}';
    } elseif ($action === 'count') {
        echo json_encode(['count'=>(int)$pdo->query('SELECT COUNT(*) FROM pvp_chat_messages')->fetchColumn()]);
    }
    exit;
}
$path = $_SERVER['HTTP_X_CHAT_PATH'] ?? '';
if ($path === '/api/pvp/chat') {
    (new App\Http\Controllers\Api\PvpChatController($chat))->handle($_SERVER['REQUEST_METHOD']);
} elseif ($path === '/TrangGame.html') {
    echo HuitBladeEngine::render('game.play', ['currentUser'=>$_SESSION['huit_auth_user'] ?? null]);
} elseif (str_starts_with($path, '/api/pvp/')) {
    $r = new ReflectionClass(App\Http\Controllers\Api\PvpApiController::class);
    $controller = $r->newInstanceWithoutConstructor();
    foreach (['db'=>$db, 'roomService'=>$rooms, 'matchService'=>$matches, 'broadcaster'=>$broadcast] as $key=>$value) $r->getProperty($key)->setValue($controller,$value);
    $method = ['reconnect'=>'reconnect','match-status'=>'getMatchStatus','match-results'=>'getMatchResults','submit-move'=>'submitMove','submit-solution'=>'submitSolution'][basename($path)] ?? null;
    if ($method) $controller->$method(); else http_response_code(404);
} else http_response_code(404);
}
