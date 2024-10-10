<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'stock',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Scope a query to only include active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive products.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Get the category that owns the product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get all of the items for the product.
     * @return HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProductItem::class);
    }

    /**
     * Get available Items for the Product.
     */
    public function availableItems(): HasMany
    {
        return $this->hasMany(ProductItem::class)->where('is_sold', false);
    }

    /**
     * Get sold Items for the Product.
     */
    public function soldItems(): HasMany
    {
        return $this->hasMany(ProductItem::class)->where('is_sold', true);
    }
}
