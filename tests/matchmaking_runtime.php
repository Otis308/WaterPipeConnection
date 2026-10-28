<?php
// CLI-only metadata/cleanup companion for matchmaking_http_test.py. No service constructor.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/vendor/autoload.php';
$pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_PORT') ?: '3306',
    getenv('DB_DATABASE') ?: 'huit_water_pipe_db'), getenv('DB_USERNAME') ?: 'root',
    getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$action = $argv[1] ?? 'metadata';
if ($action === 'metadata') {
    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $columns = [];
    foreach (['users', 'pvp_rooms', 'pvp_room_players', 'pvp_matchmaking_queue', 'pvp_match_history'] as $table) {
        $columns[$table] = in_array($table, $tables, true)
            ? $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN) : null;
    }
    $baseline = [];
    foreach (array_diff($tables, ['pvp_rooms', 'pvp_room_players', 'pvp_matchmaking_queue', 'pvp_match_history']) as $table) {
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $serialized = array_map(static fn ($row) => json_encode($row), $rows);
        sort($serialized);
        $baseline[$table] = ['rows' => count($rows), 'sha256' => hash('sha256', implode("\n", $serialized))];
    }
    echo json_encode(['driver' => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
        'database' => $pdo->query('SELECT DATABASE()')->fetchColumn(), 'tables' => $tables, 'columns' => $columns,
        'baseline' => $baseline]);
    $pdo->rollBack();
} elseif ($action === 'cleanup') {
    $names = json_decode(base64_decode($argv[2] ?? ''), true, 512, JSON_THROW_ON_ERROR);
    if (count($names) !== 2) { throw new RuntimeException('Expected two test accounts'); }
    foreach ($names as $name) {
        if (!preg_match('/^mm_probe_[a-f0-9]{16}_[ab]$/D', $name)) { throw new RuntimeException('Invalid test account'); }
    }
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username IN (?, ?)');
    $stmt->execute($names);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        // Delete only rooms hosted by these freshly-created test users, never another user's room.
        $roomStmt = $pdo->prepare('SELECT id FROM pvp_rooms WHERE host_user_id = ?');
        if ($pdo->query("SHOW TABLES LIKE 'pvp_rooms'")->fetchColumn()) {
            $roomStmt->execute([$id]);
            foreach ($roomStmt->fetchAll(PDO::FETCH_COLUMN) as $roomId) {
                $pdo->prepare('DELETE FROM pvp_room_players WHERE room_id = ?')->execute([$roomId]);
                $pdo->prepare('DELETE FROM pvp_rooms WHERE id = ?')->execute([$roomId]);
            }
        }
        if ($pdo->query("SHOW TABLES LIKE 'pvp_matchmaking_queue'")->fetchColumn()) {
            $pdo->prepare('DELETE FROM pvp_matchmaking_queue WHERE user_id = ?')->execute([$id]);
        }
        $pdo->prepare('DELETE FROM user_profiles WHERE user_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
    $pdo->commit();
    echo json_encode(['cleaned_test_accounts' => count($ids)]);
} else { throw new RuntimeException('Unknown action'); }
