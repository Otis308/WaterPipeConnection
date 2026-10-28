<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    use HasFactory;

    protected $table = 'achievements';

    protected $fillable = [
        'code',
        'title',
        'description',
        'min_score',
        'badge_icon',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'achievement_user', 'achievement_id', 'user_id')
                    ->withPivot('unlocked_at');
    }
}
