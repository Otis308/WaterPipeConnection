<?php

// Loaded only by AvatarStorage, after the project's own autoloader/stubs.
// No framework boot, service-provider discovery, Request or Eloquent is involved.
$autoload = __DIR__ . '/avatar-storage/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Avatar Storage dependencies are not installed.');
}
require_once $autoload;

$app = new Illuminate\Container\Container();
$app->instance('config', new Illuminate\Config\Repository([
    'filesystems' => [
        'default' => 'public',
        'disks' => [
            'public' => [
                'driver' => 'local',
                'root' => dirname(__DIR__) . '/storage/app/public',
                // Explicit URL also avoids Str::replaceFirst(), absent in the existing stub.
                'url' => '/storage',
                'visibility' => 'public',
                'directory_visibility' => 'public',
                'throw' => true,
            ],
        ],
    ],
]));
$manager = new Illuminate\Filesystem\FilesystemManager($app);
$app->instance('filesystem', $manager);
Illuminate\Support\Facades\Storage::setFacadeApplication($app);
Illuminate\Support\Facades\Storage::clearResolvedInstance('filesystem');
return Illuminate\Support\Facades\Storage::disk('public');
