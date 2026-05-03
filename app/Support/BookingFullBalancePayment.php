<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for the admin "Pay balance" / full cash settlement flow.
 *
 * Eligibility (Option B): venue-only bookings (venues linked, no room lines) may be marked paid
 * without assigned physical rooms; lodging (room lines) or non-venue bookings still require rooms.
 */
final class BookingFullBalancePayment
{
    public const REASON_OK = 'ok';

    public const REASON_TRASHED = 'trashed';

    public const REASON_INVALID_STATUS = 'invalid_status';

    public const REASON_NO_BALANCE = 'no_balance';

    public const REASON_NEEDS_ROOM_ASSIGNMENT = 'needs_room_assignment';

    /**
     * Whether this booking must have at least one assigned physical room before full payment.
     */
    public static function requiresAssignedPhysicalRooms(Booking $booking): bool
    {
        $booking->loadMissing(['roomLines', 'venues']);

        if ($booking->roomLines->isNotEmpty()) {
            return true;
        }

        if ($booking->venues->isNotEmpty()) {
            return false;
        }

        return true;
    }

    /**
     * @return array{allowed: bool, reason: string, message: ?string}
     */
    public static function assess(Booking $booking): array
    {
        if ($booking->trashed()) {
            return ['allowed' => false, 'reason' => self::REASON_TRASHED, 'message' => null];
        }

        if (in_array($booking->booking_status, [
            Booking::BOOKING_STATUS_CANCELLED,
            Booking::BOOKING_STATUS_COMPLETED,
            Booking::BOOKING_STATUS_FLAGGED,
        ], true) || $booking->payment_status === Booking::PAYMENT_STATUS_PAID) {
            return ['allowed' => false, 'reason' => self::REASON_INVALID_STATUS, 'message' => null];
        }

        $booking->loadMissing(['payments', 'roomLines', 'venues']);

        if ((float) $booking->balance <= 0.009) {
            return ['allowed' => false, 'reason' => self::REASON_NO_BALANCE, 'message' => null];
        }

        if (self::requiresAssignedPhysicalRooms($booking) && ! $booking->rooms()->exists()) {
            return [
                'allowed' => false,
                'reason' => self::REASON_NEEDS_ROOM_ASSIGNMENT,
                'message' => 'Assign at least one room before recording full balance payment.',
            ];
        }

        return ['allowed' => true, 'reason' => self::REASON_OK, 'message' => null];
    }

    /**
     * Records one payment row for the remaining balance and sets status to paid.
     *
     * @throws \InvalidArgumentException When {@see assess()} would not allow recording.
     */
    public static function record(Booking $booking): void
    {
        $assessment = self::assess($booking);

        if (! $assessment['allowed']) {
            throw new \InvalidArgumentException($assessment['message'] ?? 'Cannot record full balance payment.');
        }

        DB::transaction(function () use ($booking): void {
            $booking->refresh();

            if ((float) $booking->balance <= 0.009) {
                throw new \InvalidArgumentException('No remaining balance.');
            }

            $booking->payments()->create([
                'total_amount' => $booking->total_price,
                'partial_amount' => $booking->balance,
                'is_fullypaid' => true,
            ]);
            $booking->update(['payment_status' => Booking::PAYMENT_STATUS_PAID]);
        });
    }
}
