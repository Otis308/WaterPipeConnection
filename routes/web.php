<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameLevelController;
use App\Http\Controllers\GamePlayController;
use App\Http\Controllers\PdoLabController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Học phần Lập trình mã nguồn mở (HUIT)
|--------------------------------------------------------------------------
| Minh họa đầy đủ Bài 6, Bài 7 & Bài 9:
| 1. Giữ nguyên giao diện Game gốc tại '/' và '/TrangGame.html'
| 2. Tham số động {page} với whereNumber('page')
| 3. Tham số tùy chọn {slug?} với ràng buộc Regex where('slug', '[a-z0-9-]+')
| 4. Named Routes ->name('...')
| 5. Resource Controller 7 actions: Route::resource('levels', GameLevelController::class)
| 6. Route Group với prefix('admin') & middleware(['web', 'auth', 'role:admin'])
*/

// 1. Giao diện Game Nối Ống Nước gốc (Giữ nguyên 100% UI/UX ban đầu)
Route::get('/', [GamePlayController::class, 'home'])->name('game.home');
Route::get('/index.html', [GamePlayController::class, 'home']);
Route::get('/TrangGame.html', [GamePlayController::class, 'play'])->name('game.play');
Route::get('/game/play', [GamePlayController::class, 'play']);

// 2. Cổng Quản trị Kiến trúc Đồ án HUIT (Admin Portal) - Chỉ Admin được truy cập
Route::get('/admin', [GamePlayController::class, 'portal'])->name('admin.dashboard')->middleware('admin');
Route::get('/oop-lab', [GamePlayController::class, 'oopLab'])->name('oop.lab')->middleware('admin');
Route::get('/pdo-lab', [PdoLabController::class, 'index'])->name('pdo.lab')->middleware('admin');

// 3. Routing nâng cao (Bài 7 - Slide 4 & 5): Tham số động whereNumber & Tham số tùy chọn Regex
Route::get('/levels/page/{page}', function (int $page) {
    $_GET['page'] = (int)$page;
    return app(GameLevelController::class)->index();
})->whereNumber('page')->name('levels.page');

Route::get('/levels/slug/{slug?}', function (string $slug = 'khoi-nguon-dong-chay-tan-phu') {
    return "Tra cứu màn chơi theo SEO Slug hợp lệ Regex [a-z0-9-]+: " . htmlspecialchars($slug);
})->where('slug', '[a-z0-9-]+')->name('levels.slug');

// 4. Resource Route chuẩn 7 actions RESTful (Bài 7 - Slide 8-12)
Route::resource('levels', GameLevelController::class);

// 5. Xác thực & Phân quyền RBAC (Admin, User - Bài 9)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');
Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/auth/quick-switch/{username}', [AuthController::class, 'quickSwitchRole'])->name('auth.quick_switch');

// 6. Route Group khu vực Quản trị (Bài 7 Slide 7 & Bài 9 Slide 15)
Route::prefix('admin')
    ->middleware(['web', 'auth', 'admin'])
    ->group(function () {
        Route::post('/users/{id}/role', [AuthController::class, 'updateRole'])->name('admin.users.role');
        Route::post('/users/{id}/toggle-active', [AuthController::class, 'toggleActive'])->name('admin.users.toggle_active');
    });
