<?php

namespace App\Support;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for whether staff can check a booking in (→ status occupied).
 */
final class BookingCheckInEligibility
{
    public const REASON_OK = 'ok';

    public const REASON_TRASHED = 'trashed';

    public const REASON_INVALID_STATUS = 'invalid_status';

    public const REASON_OUTSIDE_CHECK_IN_DAY = 'outside_check_in_day';

    public const REASON_ASSIGNMENTS = 'assignments';

    /**
     * @return array{allowed: bool, reason: string, message: ?string}
     */
    public static function assess(Booking $booking): array
    {
        if ($booking->trashed()) {
            return ['allowed' => false, 'reason' => self::REASON_TRASHED, 'message' => null];
        }

        if ($booking->booking_status !== Booking::BOOKING_STATUS_RESERVED) {
            return [
                'allowed' => false,
                'reason' => self::REASON_INVALID_STATUS,
                'message' => __('Booking must be Reserved before check-in.'),
            ];
        }

        if (! $booking->isCheckInTodayManila()) {
            return [
                'allowed' => false,
                'reason' => self::REASON_OUTSIDE_CHECK_IN_DAY,
                'message' => __('Booking can only be checked in on the check-in date.'),
            ];
        }

        return self::finishAssessAfterPaymentAndAssignments($booking);
    }

    /**
     * Calendar day modal: the selected grid date must match the booking check-in calendar day (Manila),
     * and the real-world date must be on or after check-in (prevents early check-in while browsing future months).
     *
     * @return array{allowed: bool, reason: string, message: ?string}
     */
    public static function assessForCalendarModalDay(Booking $booking, string $modalDateYmd): array
    {
        if ($booking->trashed()) {
            return ['allowed' => false, 'reason' => self::REASON_TRASHED, 'message' => null];
        }

        if ($booking->booking_status !== Booking::BOOKING_STATUS_RESERVED) {
            return [
                'allowed' => false,
                'reason' => self::REASON_INVALID_STATUS,
                'message' => __('Booking must be Reserved before check-in.'),
            ];
        }

        if (! $booking->check_in) {
            return [
                'allowed' => false,
                'reason' => self::REASON_OUTSIDE_CHECK_IN_DAY,
                'message' => __('Booking has no check-in time.'),
            ];
        }

        $tz = Booking::timezoneManila();
        $modalDay = Carbon::parse($modalDateYmd, $tz)->startOfDay();
        $checkInDay = $booking->check_in->copy()->timezone($tz)->startOfDay();

        if (! $modalDay->equalTo($checkInDay)) {
            return [
                'allowed' => false,
                'reason' => self::REASON_OUTSIDE_CHECK_IN_DAY,
                'message' => __('Open the calendar day that matches the booking check-in date to check in.'),
            ];
        }

        $today = now()->timezone($tz)->startOfDay();
        if ($today->lt($checkInDay)) {
            return [
                'allowed' => false,
                'reason' => self::REASON_OUTSIDE_CHECK_IN_DAY,
                'message' => __('Check-in unlocks on the check-in date.'),
            ];
        }

        return self::finishAssessAfterPaymentAndAssignments($booking);
    }

    /**
     * @return array{allowed: bool, reason: string, message: ?string}
     */
    private static function finishAssessAfterPaymentAndAssignments(Booking $booking): array
    {
        if ($booking->payment_status !== Booking::PAYMENT_STATUS_PAID) {
            return [
                'allowed' => false,
                'reason' => self::REASON_INVALID_STATUS,
                'message' => __('Booking must be fully paid before check-in.'),
            ];
        }

        $booking->loadMissing(['roomLines', 'venues', 'rooms.bedSpecifications']);

        try {
            $booking->assertAssignmentsSatisfiedForOccupied();
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? $e->getMessage();

            return ['allowed' => false, 'reason' => self::REASON_ASSIGNMENTS, 'message' => $message];
        }

        return ['allowed' => true, 'reason' => self::REASON_OK, 'message' => null];
    }
}
