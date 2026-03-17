<?php

namespace App\Observers;

use App\Events\ReviewsUpdated;
use App\Models\Review;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Log;

/**
 * Broadcasts when a review/testimonial is created, updated, or deleted
 * so frontend (landing) refetches in real time.
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

        $this->safeBroadcast($review, 'saved');
    }

    public function deleted(Review $review): void
    {
        $this->safeBroadcast($review, 'deleted');
    }

    private function safeBroadcast(Review $review, string $action): void
    {
        try {
            ReviewsUpdated::dispatch();
        } catch (\Throwable $exception) {
            $message = trim($exception->getMessage());

            // Prevent logging huge HTML error pages
            if (str_contains($message, '<!DOCTYPE html>')) {
                $message = 'Received HTML response instead of broadcast server response (likely wrong Reverb endpoint).';
            }

            Log::warning('ReviewsUpdated broadcast failed', [
                'review_id' => $review->id,
                'action' => $action,
                'error' => $message,
                'exception' => get_class($exception),
            ]);
        }
    }
}