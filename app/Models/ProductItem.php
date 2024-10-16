<?php

namespace App\Models;

use App\Observers\ProductItemObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([ProductItemObserver::class])]
class ProductItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'item',
        'is_sold',
        'transaction_id',
    ];

    protected $casts = [
        'is_sold' => 'boolean',
    ];

    /**
     * Scope a query to only include sold items.
     */
    public function scopeSold($query)
    {
        return $query->where('is_sold', true);
    }

    /**
     * Scope a query to only include available items.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_sold', false);
    }

    /**
     * Get the product that owns the item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the transaction that owns the item.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
