<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Class CheckRole
 * Hỗ trợ phân quyền 3 cấp độ: Admin, Staff, User (Yêu cầu số 7 của đề bài).
 * Cách gọi trên Route: ->middleware('role:admin,staff')
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role ?? '', $roles, true)) {
            abort(403, 'Bạn không đủ quyền hạn (' . implode(' hoặc ', $roles) . ') để thực hiện chức năng này.');
        }
        return $next($request);
    }
}
