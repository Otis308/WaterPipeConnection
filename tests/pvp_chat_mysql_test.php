<?php
// Uses TEMPORARY tables shadowing any production names in this connection only.
// No production schema/data mutation, no PdoDatabaseService constructor/fallback.
require dirname(__DIR__) . '/vendor/autoload.php';
class ChatTemporaryMysql extends PDO
{
    public function exec(string $statement): int|false
    {
        if (preg_match('/^CREATE TABLE IF NOT EXISTS pvp_chat_/', $statement)) {
            $statement = preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $statement);
        } elseif (!str_starts_with($statement, 'CREATE TEMPORARY TABLE ')) {
            throw new RuntimeException('Fixture only permits temporary DDL');
        }
        return parent::exec($statement);
    }
}
$value = fn($key,$fallback='') => getenv($key) !== false ? getenv($key) : $fallback;
$pdo = new ChatTemporaryMysql('mysql:host='.$value('DB_HOST','db').';port='.$value('DB_PORT','3306').';dbname='.$value('DB_DATABASE').';charset=utf8mb4',
    $value('DB_USERNAME'), $value('DB_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TEMPORARY TABLE users (id BIGINT PRIMARY KEY, username VARCHAR(255), name VARCHAR(255)) ENGINE=InnoDB');
$pdo->exec('CREATE TEMPORARY TABLE pvp_rooms (id BIGINT PRIMARY KEY, status VARCHAR(32), map_config_json TEXT, started_at_ts BIGINT, time_limit_seconds INT) ENGINE=InnoDB');
$pdo->exec('CREATE TEMPORARY TABLE pvp_room_players (room_id BIGINT, user_id BIGINT, PRIMARY KEY(room_id,user_id)) ENGINE=InnoDB');
$pdo->prepare('INSERT INTO users VALUES (1,?,?)')->execute(['fixture','Người thử 😀']);
$pdo->prepare('INSERT INTO pvp_rooms VALUES (1,?,?,?,3600)')->execute(['in_progress','{}',time()]);
$pdo->prepare('INSERT INTO pvp_room_players VALUES (1,1)')->execute();
$chat = new App\Services\PvpChatService($pdo);
$chat->ensureSchema(); $chat->ensureSchema();
$pdo->beginTransaction(); $id=$chat->createMatch(1,[['user_id'=>1]]); $pdo->commit();
$pdo->prepare('UPDATE pvp_rooms SET map_config_json=? WHERE id=1')->execute([json_encode(['chat_match_id'=>$id])]);
$checks=0;
$check = function($condition,$message) use (&$checks) { $checks++; if(!$condition)throw new RuntimeException($message); };
$message=$chat->sendMessage(1,$id,'Xin chào 😀',str_repeat('a',32));
$check($message['sender_name']==='Người thử 😀','MySQL utf8mb4 sender');
$check($chat->sendMessage(1,$id,'Xin chào 😀',str_repeat('a',32))['id']===$message['id'],'MySQL idempotency');
$check($chat->listMessages(1,$id,0)['messages'][0]['content']==='Xin chào 😀','MySQL ordered retrieval');
$check($chat->listMessages(1,$id,$message['id'])['messages']===[],'MySQL cursor');
for($i=0;$i<4;$i++)$chat->sendMessage(1,$id,'message '.$i,bin2hex(random_bytes(16)));
try { $chat->sendMessage(1,$id,'sixth',bin2hex(random_bytes(16))); $check(false,'Rate limit missing'); }
catch (RuntimeException $e) { $check($e->getCode()===429,'MySQL rate limit'); }
$indexes=$pdo->query('SHOW INDEX FROM pvp_chat_messages')->fetchAll();
$check(count(array_filter($indexes,fn($i)=>$i['Key_name']==='pvp_chat_cursor'))===2,'Cursor index exists');
$check(count(array_filter($indexes,fn($i)=>$i['Key_name']==='pvp_chat_rate'))===2,'Rate index exists');
$check((int)$pdo->query('SELECT COUNT(*) FROM pvp_chat_messages')->fetchColumn()===5,'No duplicate or rate-limited inserts');
echo "MySQL temporary tables: $checks assertions passed\n";
