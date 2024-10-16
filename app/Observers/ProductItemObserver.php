<?php

namespace App\Observers;

use App\Models\ProductItem;

class ProductItemObserver
{
    /**
     * Handle the ProductItem "created" event.
     */
    public function created(ProductItem $productItem): void
    {
        //
    }

    /**
     * Handle the ProductItem "updated" event.
     */
    public function updated(ProductItem $productItem): void
    {
        //
    }

    /**
     * Handle the ProductItem "deleted" event.
     */
    public function deleted(ProductItem $productItem): void
    {
        // update product stock
        $product = $productItem->product;
        $product->stock -= 1;
        $product->save();
    }

    /**
     * Handle the ProductItem "restored" event.
     */
    public function restored(ProductItem $productItem): void
    {
        //
    }

    /**
     * Handle the ProductItem "force deleted" event.
     */
    public function forceDeleted(ProductItem $productItem): void
    {
        //
    }
}
