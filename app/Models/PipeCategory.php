<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model PipeCategory (Quan hệ 1-N: hasMany GameLevel & ShopItem - Bài 8 Slide 23-24)
 */
class PipeCategory extends Model
{
    use HasFactory;

    protected $table = 'pipe_categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'score_multiplier',
    ];

    public function gameLevels()
    {
        return $this->hasMany(GameLevel::class, 'category_id');
    }

    public function shopItems()
    {
        return $this->hasMany(ShopItem::class, 'category_id');
    }
}
