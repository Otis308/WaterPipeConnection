<?php
// Invoked via docker exec -i, with fixture JSON in argv[1]. Never opens the shared DB.
$fixture = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
session_id($fixture['sid']);
require dirname(__DIR__) . '/vendor/autoload.php';
if ($fixture['action'] === 'seed') {
    $_SESSION = ['_token' => $fixture['csrf'], 'huit_auth_user' => [
        'id' => $fixture['uid'], 'username' => 'avatar-fixture', 'name' => 'Avatar fixture', 'role' => 'user',
    ]];
}
$result = ['cookie_name' => session_name(), 'notice' => $_SESSION['profile_notice'] ?? null];
if ($fixture['action'] === 'images') {
    $image = imagecreatetruecolor(12, 12);
    imagefill($image, 0, 0, imagecolorallocate($image, 18, 140, 220));
    foreach (['png' => 'imagepng', 'jpg' => 'imagejpeg', 'webp' => 'imagewebp'] as $extension => $encoder) {
        ob_start();
        $encoder($image);
        $result['images'][$extension] = base64_encode(ob_get_clean());
    }
    imagedestroy($image);
}
if ($fixture['action'] === 'destroy') {
    $_SESSION = [];
    session_destroy();
} else {
    session_write_close();
}
echo json_encode($result, JSON_THROW_ON_ERROR);
