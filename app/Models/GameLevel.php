<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model GameLevel
 * - belongsTo(PipeCategory::class)
 * - belongsTo(User::class, 'created_by')
 * - belongsToMany(Tag::class, 'game_level_tag')
 * - hasMany(GameSession::class)
 */
class GameLevel extends Model
{
    use HasFactory;

    protected $table = 'game_levels';

    protected $fillable = [
        'category_id',
        'created_by',
        'title',
        'slug',
        'map_size',
        'grid_rows',
        'grid_cols',
        'difficulty',
        'time_limit_seconds',
        'reward_stars',
        'thumbnail',
        'description',
        'map_config_json',
        'is_published',
    ];

    protected $casts = [
        'map_config_json' => 'array',
        'is_published' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(PipeCategory::class, 'category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'game_level_tag', 'game_level_id', 'tag_id');
    }

    public function sessions()
    {
        return $this->hasMany(GameSession::class, 'game_level_id');
    }
}
