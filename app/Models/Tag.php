<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Tag (Quan hệ N-N với GameLevel qua bảng trung gian game_level_tag)
 */
class Tag extends Model
{
    use HasFactory;

    protected $table = 'tags';

    protected $fillable = [
        'name',
        'slug',
        'color_hex',
    ];

    public function gameLevels()
    {
        return $this->belongsToMany(GameLevel::class, 'game_level_tag', 'tag_id', 'game_level_id');
    }
}
