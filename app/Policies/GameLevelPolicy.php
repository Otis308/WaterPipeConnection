<?php

namespace App\Policies;

use App\Models\GameLevel;
use App\Models\User;

/**
 * Class GameLevelPolicy
 * Minh họa Bài 9 (Slide 25-27 - Authorization với Policy):
 * - viewAny / view: Mọi người dùng đều có thể xem danh sách và chi tiết màn chơi.
 * - create: Chỉ Admin và Staff được phép tạo màn chơi mới.
 * - update / delete: Admin có toàn quyền; Staff chỉ được sửa/xoá màn chơi do chính mình tạo (ownership check: $level->created_by === $user->id).
 */
class GameLevelPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, GameLevel $level): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'staff'], true);
    }

    /**
     * Chỉ tác giả (Staff tạo màn chơi) hoặc Admin mới được phép chỉnh sửa (Slide 26 Bài 9)
     */
    public function update(User $user, GameLevel $level): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        return $user->role === 'staff' && (int)$level->created_by === (int)$user->id;
    }

    /**
     * Chỉ tác giả (Staff tạo màn chơi) hoặc Admin mới được phép xoá
     */
    public function delete(User $user, GameLevel $level): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        return $user->role === 'staff' && (int)$level->created_by === (int)$user->id;
    }

    /**
     * Hàm kiểm tra tĩnh tiện lợi dùng chung cho cả mảng dữ liệu PDO và Eloquent Model
     */
    public static function canModify(?array $user, array $level): bool
    {
        if (!$user) {
            return false;
        }
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }
        return ($user['role'] ?? '') === 'staff' && (int)($level['created_by'] ?? 0) === (int)($user['id'] ?? -1);
    }
}
