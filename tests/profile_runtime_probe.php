<?php
// Read-only capability probe: never constructs PdoDatabaseService.
require dirname(__DIR__) . '/vendor/autoload.php';
$avatarReady = (new App\Services\AvatarStorage())->ready();
echo json_encode([
    'avatar_ready' => $avatarReady,
    'public_disk_adapter' => $avatarReady ? get_class((new App\Services\AvatarStorage())->disk()) : null,
    'isolated_composer_autoload' => is_file(dirname(__DIR__) . '/bootstrap/avatar-storage/vendor/autoload.php'),
    'avatar_storage_bootstrap' => is_file(dirname(__DIR__) . '/bootstrap/avatar_storage.php'),
    'php_version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'storage_facade' => class_exists('Illuminate\\Support\\Facades\\Storage'),
    'facade_base' => class_exists('Illuminate\\Support\\Facades\\Facade'),
    'filesystem_manager' => class_exists('Illuminate\\Filesystem\\FilesystemManager'),
    'filesystem_adapter' => class_exists('Illuminate\\Filesystem\\FilesystemAdapter'),
    'flysystem' => interface_exists('League\\Flysystem\\FilesystemOperator'),
    'flysystem_local_adapter' => class_exists('League\\Flysystem\\Local\\LocalFilesystemAdapter'),
    'illuminate_container' => class_exists('Illuminate\\Container\\Container'),
    'config_repository' => class_exists('Illuminate\\Config\\Repository'),
    'composer_generated_autoload' => is_file(dirname(__DIR__) . '/vendor/composer/autoload_real.php'),
    'filesystem_config_file' => is_file(dirname(__DIR__) . '/config/filesystems.php'),
    'public_storage_link' => is_link(dirname(__DIR__) . '/public/storage'),
    'public_storage_target_exists' => is_dir(dirname(__DIR__) . '/storage/app/public'),
    'image_inspection' => [
        'fileinfo' => class_exists('finfo'),
        'getimagesize' => function_exists('getimagesize'),
        'gd_decode' => function_exists('imagecreatefromstring'),
        'imagick' => class_exists('Imagick'),
    ],
    'uploads' => [
        'enabled' => filter_var(ini_get('file_uploads'), FILTER_VALIDATE_BOOLEAN),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
    ],
    'pdo_drivers' => PDO::getAvailableDrivers(),
], JSON_PRETTY_PRINT), PHP_EOL;
