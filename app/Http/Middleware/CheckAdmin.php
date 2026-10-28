<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Middleware CheckAdmin — Bảo vệ toàn bộ khu vực /admin.
 * Chỉ cho phép tài khoản đã đăng nhập qua Laravel Auth VÀ có role = 'admin'.
 * Nếu không thỏa mãn → redirect về trang chủ, KHÔNG thể bypass bằng URL thủ công.
 */
class CheckAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // Chưa đăng nhập → về trang login
        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login')->with('flash_error', 'Vui lòng đăng nhập để tiếp tục.');
        }

        // Đã đăng nhập nhưng không phải Admin → về trang chủ, không cho vào /admin
        if ($user->role !== 'admin') {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
            }
            return redirect('/')->with('flash_error', 'Bạn không có quyền truy cập khu vực Quản trị. Chỉ tài khoản Admin mới được phép.');
        }

        return $next($request);
    }
}
