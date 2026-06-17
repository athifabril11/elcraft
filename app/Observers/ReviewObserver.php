<?php

namespace App\Observers;

use App\Models\Review;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ReviewObserver
{
    /**
     * Handle the Review "created" event.
     */
    public function created(Review $review): void
    {
        \Illuminate\Support\Facades\Log::info('ReviewObserver created event triggered for review ID: ' . $review->id);
        $this->recalculateProductRating($review->product_id);
    }

    /**
     * Handle the Review "updated" event.
     */
    public function updated(Review $review): void
    {
        $this->recalculateProductRating($review->product_id);
        
        // If product_id was updated, recalculate for the old one too
        if ($review->wasChanged('product_id')) {
            $this->recalculateProductRating($review->getOriginal('product_id'));
        }
    }

    /**
     * Handle the Review "deleted" event.
     */
    public function deleted(Review $review): void
    {
        $this->recalculateProductRating($review->product_id);
    }

    /**
     * Recalculate average rating and review count for a product.
     */
    protected function recalculateProductRating(int $productId): void
    {
        $product = Product::find($productId);
        if ($product) {
            $stats = Review::where('product_id', $productId)
                ->where('is_approved', true)
                ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as count_reviews')
                ->first();

            $product->rating_avg   = round($stats->avg_rating ?? 0.0, 1);
            $product->rating_count = $stats->count_reviews ?? 0;
            $product->save();

            // Bust the fragment cache
            Cache::forget("product:{$productId}:rating");
        }
    }
}
