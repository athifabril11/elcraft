<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;
use App\Models\OrderItem;

class ReviewPolicy
{
    /**
     * Determine whether the user can write a review for a specific order item.
     */
    public function writeReview(User $user, OrderItem $orderItem): bool
    {
        // Check if the order is delivered, belongs to the user, and has not been reviewed yet
        $isDeliveredAndOwned = $orderItem->order &&
                               $orderItem->order->user_id === $user->id &&
                               $orderItem->order->status === 'selesai';

        if (!$isDeliveredAndOwned) {
            return false;
        }

        $alreadyReviewed = Review::where('user_id', $user->id)
                                 ->where('order_item_id', $orderItem->id)
                                 ->exists();

        return !$alreadyReviewed;
    }

    /**
     * Determine whether the user can view any reviews.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the review.
     */
    public function view(User $user, Review $review): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create reviews.
     */
    public function create(User $user): bool
    {
        return true;
    }
}
