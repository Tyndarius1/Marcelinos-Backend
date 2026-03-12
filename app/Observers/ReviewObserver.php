<?php

namespace App\Observers;

use App\Events\ReviewsUpdated;
use App\Models\Review;
use App\Support\ActivityLogger;

/**
 * Broadcasts when a review/testimonial is created, updated, or deleted so frontend (landing) refetches in real time.
 */
class ReviewObserver
{
    public function saved(Review $review): void
    {
        if ($review->wasChanged('is_approved')) {
            ActivityLogger::log(
                category: 'review',
                event: 'review.approval_changed',
                description: $review->is_approved ? 'approved a review.' : 'unapproved a review.',
                subject: $review,
                meta: [
                    'review_id' => $review->id,
                    'booking_id' => $review->booking_id,
                    'guest_id' => $review->guest_id,
                    'is_approved' => (bool) $review->is_approved,
                ],
            );
        }

        ReviewsUpdated::dispatch();
    }

    public function deleted(Review $review): void
    {
        ReviewsUpdated::dispatch();
    }
}
