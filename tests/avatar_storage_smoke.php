<?php
// Real disk integration smoke test, no DB and no other user's files.
require dirname(__DIR__) . '/vendor/autoload.php';
$symbols = ['Illuminate\\Support\\Str', 'Illuminate\\Support\\Facades\\Auth',
    'Illuminate\\Support\\Facades\\Hash', 'Illuminate\\Http\\Request', 'Illuminate\\Database\\Eloquent\\Model'];
$origins = [];
foreach ($symbols as $symbol) $origins[$symbol] = (new ReflectionClass($symbol))->getFileName();
$avatars = new App\Services\AvatarStorage();
if (!$avatars->ready()) throw new RuntimeException('Avatar runtime is not ready.');
$disk = $avatars->disk();
$path = 'avatars/.storage-smoke-' . bin2hex(random_bytes(12)) . '.txt';
try {
    if (!$disk->put($path, 'real-storage-smoke') || $disk->get($path) !== 'real-storage-smoke') {
        throw new RuntimeException('Real public disk write/read failed.');
    }
    if ($disk->url($path) !== '/storage/' . $path) throw new RuntimeException('Unexpected disk URL.');
    foreach ($symbols as $symbol) {
        if ((new ReflectionClass($symbol))->getFileName() !== $origins[$symbol]) throw new RuntimeException('Stub changed: ' . $symbol);
    }
    if (!Illuminate\Support\Facades\Storage::getFacadeRoot() instanceof Illuminate\Filesystem\FilesystemManager) {
        throw new RuntimeException('Facade is not backed by the real manager.');
    }
    echo "Real Laravel Storage facade/public disk: write, read, URL and existing-stub compatibility passed.\n";
    echo 'Adapter: ', get_class($disk), '; GD JPEG/PNG/WebP ready.', PHP_EOL;
} finally {
    $disk->delete($path);
}
if ($disk->exists($path)) throw new RuntimeException('Smoke cleanup failed.');
