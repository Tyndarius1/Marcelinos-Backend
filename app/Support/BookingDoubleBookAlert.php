<?php

namespace App\Support;

use App\Models\Booking;
use App\Notifications\Slack\DoubleBookingSlackNotification;
use Illuminate\Support\Facades\Cache;

final class BookingDoubleBookAlert
{
    /**
     * Defer overlap checks until after the HTTP response so Filament (and similar) can finish
     * attaching rooms and venues. Uses a short cache fingerprint to limit repeated Slack noise.
     */
    public static function scheduleCheckAfterSave(Booking $booking): void
    {
        $id = (int) $booking->getKey();
        if ($id === 0) {
            return;
        }

        dispatch(static function () use ($id): void {
            $booking = Booking::query()
                ->with(['guest', 'rooms', 'venues'])
                ->find($id);

            if ($booking === null) {
                return;
            }

            if ($booking->trashed() || $booking->booking_status === Booking::BOOKING_STATUS_CANCELLED) {
                return;
            }

            $conflicts = BookingDoubleBookDetector::overlappingBookings($booking);
            if ($conflicts->isEmpty()) {
                return;
            }

            $conflicts->loadMissing('guest');

            $fingerprint = $conflicts->pluck('id')->sort()->values()->implode(',');
            $cacheKey = 'slack:double_book:'.sha1($id.'|'.$fingerprint);

            if (Cache::has($cacheKey)) {
                return;
            }

            Cache::put($cacheKey, true, now()->addMinutes(45));

            $payload = $conflicts->map(static function (Booking $b): array {
                return [
                    'id' => (int) $b->id,
                    'reference_number' => (string) $b->reference_number,
                    'guest_name' => $b->displayGuestName(),
                    'check_in' => $b->check_in?->timezone(Booking::timezoneManila())->format('M j, Y g:i A') ?? '—',
                    'check_out' => $b->check_out?->timezone(Booking::timezoneManila())->format('M j, Y g:i A') ?? '—',
                    'booking_status' => (string) $b->booking_status,
                    'payment_status' => (string) $b->payment_status,
                ];
            })->values()->all();

            SlackBookingAlerts::notify(new DoubleBookingSlackNotification($booking, $payload));
        })->afterResponse();
    }
}
