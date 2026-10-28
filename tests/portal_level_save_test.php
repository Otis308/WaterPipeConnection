<?php
// Isolated controller + real SQL writer test; never run the production constructor.
require_once __DIR__ . '/../database/stubs.php';
spl_autoload_register(static function ($class) {
    if (str_starts_with($class, 'App\\')) require_once __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
function redirect($url) { return $url; }
function abort($code, $message = '') { throw new RuntimeException((string)$code); }
$result = App\Services\PortalMapGeneratorService::generateMap('3x8', 'hard', 2, 'PORTAL_DEMO');
if (in_array('--fixture', $argv, true)) { echo json_encode($result); exit; }
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE game_levels (id INTEGER PRIMARY KEY, category_id, created_by, title, slug, map_size, grid_rows, grid_cols, difficulty, time_limit_seconds, reward_stars, thumbnail, description, map_config_json, is_published)');
$db = (new ReflectionClass(App\Services\PdoDatabaseService::class))->newInstanceWithoutConstructor();
(new ReflectionProperty($db, 'conn'))->setValue($db, $pdo);
$controller = (new ReflectionClass(App\Http\Controllers\GameLevelController::class))->newInstanceWithoutConstructor();
(new ReflectionProperty($controller, 'db'))->setValue($controller, $db);
$_SESSION = ['huit_auth_user' => ['id' => 12, 'role' => 'admin'], 'portal_generated_maps' => ['fixture' => $result['map']]];
$_FILES = [];
$_POST = ['title' => 'Bản đồ mới', 'category_id' => 1, 'map_size' => '3x8', 'difficulty' => 'hard',
    'time_limit_seconds' => 180, 'reward_stars' => 3, 'description' => 'Bản đồ sinh cho màn chơi mới.', 'portal_generated_hash' => 'fixture'];
$checks = 0;
function check($value, $message) { global $checks; if (!$value) throw new RuntimeException($message); $checks++; }
check($controller->store() === '/admin', 'Wrong redirect');
$stored = json_decode($pdo->query('SELECT map_config_json FROM game_levels')->fetchColumn(), true);
$expected = $result['map']; $expected['thinkingTimeMs'] = 180000;
check($stored === $expected, 'Stored map differs from original');
check(!isset($stored['solution']), 'Solution leaked to level');
$_POST['portal_generated_hash'] = 'unknown';
check($controller->store() === '/levels/create', 'Unknown map accepted');
$_POST['portal_generated_hash'] = 'fixture'; $_POST['difficulty'] = 'easy';
check($controller->store() === '/levels/create', 'Mismatched difficulty accepted');
$_POST['difficulty'] = 'hard'; $_POST['time_limit_seconds'] = -1;
check($controller->store() === '/levels/create', 'Invalid time accepted');
$_POST['time_limit_seconds'] = 180;
$_POST['difficulty'] = 'hard'; $_SESSION['huit_auth_user']['role'] = 'staff';
check($controller->store() === '/levels/create', 'Staff accepted generated admin map');
check((int)$pdo->query('SELECT COUNT(*) FROM game_levels')->fetchColumn() === 1, 'Invalid submissions wrote data');
$_SESSION['huit_auth_user']['role'] = 'admin'; unset($_POST['portal_generated_hash']);
check($controller->store() === '/levels', 'Normal creation regression');
check((int)$pdo->query('SELECT COUNT(*) FROM game_levels')->fetchColumn() === 2, 'Normal level missing');
echo "$checks assertions passed (SQLite :memory:, production constructors bypassed).\n";
