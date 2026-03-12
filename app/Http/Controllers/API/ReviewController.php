<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    public function index(): JsonResponse
    {
        $reviews = Review::with('guest')
            ->where('is_approved', true)
            ->latest('reviewed_at')
            ->get()
            ->map(fn ($review) => [
                'guest_name' => $review->guest
                    ? $review->guest->first_name . ' ' . $review->guest->last_name
                    : null,
                'rating' => $review->rating,
                'title' => $review->title,
                'comment' => $review->comment,
                'date' => optional($review->reviewed_at)->toDateString(),
            ])
            ->values()
            ->all();

        return ApiResponse::success($reviews);
    }
    /**
     * Store a testimonial/site review for a completed booking (by reference number).
     * Used by the client testimonial form linked from the post-stay email.
     */
    public function storeByBookingReference(Request $request, string $reference): JsonResponse
    {
        try {
            $booking = Booking::with('guest')
                ->where('reference_number', $reference)
                ->first();

            if (!$booking) {
                return response()->json(['message' => 'Booking not found.'], 404);
            }

            if ($booking->status !== Booking::STATUS_COMPLETED) {
                return response()->json(
                    ['message' => 'Reviews can only be submitted for completed stays.'],
                    422
                );
            }

            $existing = Review::where('booking_id', $booking->id)
                ->exists();

            if ($existing) {
                return response()->json(
                    ['message' => 'A review has already been submitted for this booking.'],
                    422
                );
            }

            $validator = Validator::make($request->all(), [
                'rating' => 'required|integer|min:1|max:5',
                'title' => 'nullable|string|max:255',
                'comment' => 'nullable|string|max:5000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();

            $review = Review::create([
                'guest_id' => $booking->guest_id,
                'booking_id' => $booking->id,
                'reviewable_type' => null,
                'reviewable_id' => null,
                'rating' => (int) $data['rating'],
                'title' => $data['title'] ?? null,
                'comment' => $data['comment'] ?? null,
                'is_approved' => false,
                'reviewed_at' => now(),
            ]);

            return response()->json([
                'message' => 'Thank you! Your review has been submitted.',
                'review' => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'title' => $review->title,
                    'reviewed_at' => $review->reviewed_at?->toIso8601String(),
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Review submission error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Could not submit review. Please try again later.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
