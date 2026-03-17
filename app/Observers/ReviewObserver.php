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

        $this->safeBroadcast();
    }

    public function deleted(Review $review): void
    {
        $this->safeBroadcast();
    }

    private function safeBroadcast(): void
    {
        try {
            ReviewsUpdated::dispatch();
        } catch (\Throwable $exception) {
            file_put_contents(
                storage_path('logs/laravel.log'),
                now()->toDateTimeString() . ' ReviewsUpdated broadcast failed: ' . $exception->getMessage() . "\n",
                FILE_APPEND
            );
        }
    }
}
