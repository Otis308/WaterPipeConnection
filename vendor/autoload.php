<?php
/**
 * PSR-4 Autoloader chuẩn (Chương 2 - Slide 16: Autoloading và PSR-4 với spl_autoload_register)
 * Ánh xạ tự động:
 * - Namespace `App\` -> thư mục `app/`
 * - Namespace `Database\Factories\` -> thư mục `database/factories/`
 * - Namespace `Database\Seeders\` -> thư mục `database/seeders/`
 */

spl_autoload_register(function (string $class): void {
    $prefixes = [
        'App\\' => dirname(__DIR__) . '/app/',
        'Database\\Factories\\' => dirname(__DIR__) . '/database/factories/',
        'Database\\Seeders\\' => dirname(__DIR__) . '/database/seeders/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Nạp bộ nhân Hybrid MVC & Blade Engine giúp dự án chạy ngay lập tức ở cả chế độ Offline Laragon lẫn Docker
require_once dirname(__DIR__) . '/bootstrap/huit_hybrid_kernel.php';
require_once dirname(__DIR__) . '/database/stubs.php';
