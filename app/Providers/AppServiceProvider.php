<?php

namespace App\Providers;

use App\Models\GameLevel;
use App\Models\User;
use App\Policies\GameLevelPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Class AppServiceProvider
 * Minh họa Bài 9 (Slide 24-25): Đăng ký Gate cho luật phân quyền chung (Admin / Staff) và Policy cho GameLevel.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Khai báo Gate bằng closure ngắn gọn (Slide 24 Bài 9)
        Gate::define('admin', fn(User $user) => $user->{'role'} === 'admin');
        Gate::define('staff-or-admin', fn(User $user) => in_array($user->{'role'}, ['admin', 'staff'], true));

        // Đăng ký Policy cho GameLevel
        Gate::policy(GameLevel::class, GameLevelPolicy::class);
    }
}
