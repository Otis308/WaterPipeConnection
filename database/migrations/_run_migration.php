<?php
// Script kiểm tra schema và chạy migration
// Load dotenv manually since we're running standalone
$envFile = '/var/www/html/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v, " \t\n\r\0\x0B\"'"));
    }
}

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST') ?: 'db',
    getenv('DB_PORT') ?: '3306',
    getenv('DB_DATABASE') ?: 'huit_water_pipe_db'
);
$conn = new PDO($dsn, getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: 'rootpassword', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);


// Kiểm tra cột board_rotations_json
$r = $conn->query("SHOW COLUMNS FROM pvp_room_players LIKE 'board_rotations_json'");
$exists_rotations = $r->fetch(PDO::FETCH_ASSOC);

$r2 = $conn->query("SHOW COLUMNS FROM pvp_room_players LIKE 'board_state_version'");
$exists_version = $r2->fetch(PDO::FETCH_ASSOC);

$r3 = $conn->query("SHOW COLUMNS FROM pvp_match_history LIKE 'win_status'");
$exists_win = $r3->fetch(PDO::FETCH_ASSOC);

$r4 = $conn->query("SHOW COLUMNS FROM pvp_match_history LIKE 'k_factor'");
$exists_kfactor = $r4->fetch(PDO::FETCH_ASSOC);

echo "board_rotations_json: " . ($exists_rotations ? "EXISTS" : "MISSING") . "\n";
echo "board_state_version: " . ($exists_version ? "EXISTS" : "MISSING") . "\n";
echo "win_status: " . ($exists_win ? "EXISTS" : "MISSING") . "\n";
echo "k_factor: " . ($exists_kfactor ? "EXISTS" : "MISSING") . "\n";

// Chạy migration nếu thiếu
if (!$exists_rotations) {
    $conn->exec("ALTER TABLE pvp_room_players ADD COLUMN board_rotations_json MEDIUMTEXT NULL DEFAULT NULL COMMENT 'JSON: {r-c: rotation_degrees} — trạng thái xoay bàn cờ cá nhân'");
    echo "Added board_rotations_json\n";
}

if (!$exists_version) {
    $conn->exec("ALTER TABLE pvp_room_players ADD COLUMN board_state_version INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Phiên bản trạng thái bàn cờ'");
    echo "Added board_state_version\n";
}

if (!$exists_win) {
    $conn->exec("ALTER TABLE pvp_match_history ADD COLUMN win_status ENUM('win','loss','draw','abandoned') NOT NULL DEFAULT 'loss' COMMENT 'Kết quả cá nhân'");
    echo "Added win_status\n";
}

if (!$exists_kfactor) {
    $conn->exec("ALTER TABLE pvp_match_history ADD COLUMN k_factor TINYINT UNSIGNED NOT NULL DEFAULT 32 COMMENT 'K-factor dùng trong trận'");
    echo "Added k_factor\n";
}

echo "Migration complete!\n";
