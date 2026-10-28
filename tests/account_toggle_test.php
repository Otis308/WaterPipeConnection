<?php
// No application bootstrap or production PDO constructor; isolated SQLite only.
spl_autoload_register(static function ($class) {
    if (str_starts_with($class, 'App\\')) require_once __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
function abort($code, $message = '') { throw new RuntimeException((string)$code); }
function redirect($url) { return $url; }
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, is_active INTEGER);
    INSERT INTO users VALUES (1,'owner','admin',1),(2,'player','user',1),(3,'designer','staff',1)");
$db = (new ReflectionClass(App\Services\PdoDatabaseService::class))->newInstanceWithoutConstructor();
(new ReflectionProperty($db, 'conn'))->setValue($db, $pdo);
$controller = new App\Http\Controllers\AuthController();
$checks = 0;
function check($ok, $message) { global $checks; if (!$ok) throw new RuntimeException($message); $checks++; }
foreach ([null, ['role'=>'user'], ['role'=>'staff']] as $actor) {
    $_SESSION = ['huit_auth_user' => $actor];
    try { $controller->toggleActive(2, $db); throw new RuntimeException('Unauthorized toggle'); }
    catch (RuntimeException $e) { check($e->getMessage() === '403', 'Wrong guard response'); }
}
check((int)$pdo->query('SELECT is_active FROM users WHERE id=2')->fetchColumn() === 1, 'Guard wrote data');
$_SESSION = ['huit_auth_user'=>['id'=>1,'role'=>'admin']];
check($controller->toggleActive(2,$db) === '/admin', 'Wrong redirect');
check((int)$pdo->query('SELECT is_active FROM users WHERE id=2')->fetchColumn() === 0, 'Lock failed');
check(isset($_SESSION['flash_success']), 'Missing success flash');
$controller->toggleActive(2,$db);
check((int)$pdo->query('SELECT is_active FROM users WHERE id=2')->fetchColumn() === 1, 'Unlock failed');
foreach ([1,3,999] as $id) {
    unset($_SESSION['flash_error']); $controller->toggleActive($id,$db);
    check(isset($_SESSION['flash_error']), 'Protected/missing user accepted');
}
check((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role != 'user' AND is_active=1")->fetchColumn() === 2,'Protected users changed');
// Exercise the real route helper in isolation without executing kernel startup.
$kernel = file_get_contents(__DIR__.'/../bootstrap/huit_hybrid_kernel.php');
$a = strpos($kernel, "if (!function_exists('route'))");
$b = strpos($kernel, 'class HuitRedirectResponse', $a);
eval(substr($kernel,$a,$b-$a));
check(route('admin.users.toggle_active',['id'=>2]) === '/admin/users/2/toggle-active','Wrong form action');
echo "$checks assertions passed; SQLite :memory:, no real accounts changed.\n";
