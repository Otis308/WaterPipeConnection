<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopItem extends Model
{
    use HasFactory;

    protected $table = 'shop_items';

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'price',
        'stock',
        'pipe_skin_folder',
        'description',
    ];

    public function category()
    {
        return $this->belongsTo(PipeCategory::class, 'category_id');
    }
}
