<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'name',
        'sku',
        'price',
        'stock',
        'is_active',
        'description',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSearch($query, $keyword)
    {
        return $query->whereFullText(['name', 'sku', 'description'], $keyword)
            ->orWhere('name', 'like', "%{$keyword}%")
            ->orWhere('sku', 'like', "%{$keyword}%")
            ->orWhere('description', 'like', "%{$keyword}%");
    }
}
