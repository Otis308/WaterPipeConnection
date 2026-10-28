<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model UserProfile (Quan hệ 1-1: belongsTo User - Bài 8 Slide 22)
 *
 * @method static \App\Models\UserProfile create(array $attributes = [])
 * @method static \App\Models\UserProfile updateOrCreate(array $attributes, array $values = [])
 */
class UserProfile extends Model
{
    use HasFactory;

    protected $table = 'user_profiles';

    protected $fillable = [
        'user_id',
        'birthday',
        'avatar',
        'preferred_mode',
        'sound_enabled',
        'music_enabled',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
