<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Model User (Bài 8 & Bài 9)
 * - Khai báo $fillable chống MassAssignmentException
 * - Quan hệ 1-1: hasOne(UserProfile::class)
 * - Quan hệ 1-N: hasMany(GameLevel::class), hasMany(GameSession::class), hasMany(ShopOrder::class)
 * - Quan hệ N-N: belongsToMany(Achievement::class)
 * - Hỗ trợ RBAC 3 vai trò: admin, staff, user
 *
 * @method static \App\Models\User create(array $attributes = [])
 * @method static \App\Models\User updateOrCreate(array $attributes, array $values = [])
 * @method static \App\Models\User|null find(mixed $id)
 * @method static \App\Models\User findOrFail(mixed $id)
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null)
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
        'role',
        'high_score',
        'total_games',
        'total_wins',
        'coins',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    // Quan hệ 1-1: Mỗi User có đúng 1 UserProfile (Bài 8 - Slide 21-22)
    public function profile()
    {
        return $this->hasOne(UserProfile::class, 'user_id');
    }

    // Quan hệ 1-N: Một Admin/Staff có thể tạo nhiều màn chơi GameLevel
    public function createdLevels()
    {
        return $this->hasMany(GameLevel::class, 'created_by');
    }

    // Quan hệ 1-N: Một User có nhiều lượt đấu GameSession
    public function gameSessions()
    {
        return $this->hasMany(GameSession::class, 'user_id');
    }

    // Quan hệ 1-N: Một User có nhiều đơn mua vật phẩm ShopOrder
    public function shopOrders()
    {
        return $this->hasMany(ShopOrder::class, 'user_id');
    }

    // Quan hệ N-N: User <-> Achievement qua bảng trung gian achievement_user (Bài 8 - Slide 25-26)
    public function achievements()
    {
        return $this->belongsToMany(Achievement::class, 'achievement_user', 'user_id', 'achievement_id')
                    ->withPivot('unlocked_at');
    }

    // Kiểm tra phân quyền (Bài 9: Admin, Staff, User)
    public function isAdmin(): bool
    {
        return ($this->role ?? '') === 'admin';
    }

    public function isStaff(): bool
    {
        return in_array($this->role ?? '', ['admin', 'staff'], true);
    }

    public function getWinRateAttribute(): float
    {
        if (empty($this->total_games) || $this->total_games <= 0) {
            return 0.0;
        }
        return round(($this->total_wins / $this->total_games) * 100, 1);
    }
}
