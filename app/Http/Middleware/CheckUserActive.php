<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Chỉ kiểm tra trạng thái tài khoản người chơi
        if (
            $user
            && $user->role === 'user'
            && (int) $user->is_active !== 1
        ) {
            // Đăng xuất người chơi đã bị khóa
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tài khoản đã bị khóa.',
                ], 403);
            }

            return redirect()->route('login')->with(
                'flash_error',
                'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.'
            );
        }

        return $next($request);
    }
}