<?php

use App\Http\Middleware\CheckAdmin;
use App\Http\Middleware\CheckRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/**
 * Cấu hình khởi tạo ứng dụng chuẩn Laravel 12 (Bài 9 - Slide 14):
 * Đăng ký Middleware alias ('admin', 'role') tập trung tại bootstrap/app.php bằng ->withMiddleware()
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => CheckAdmin::class,
            'role'  => CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
