<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Support\BookingCheckInEligibility;
use App\Support\BookingLifecycleActions;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingCheckInEligibilityTest extends TestCase
{
    private static function manila(string $datetime): Carbon
    {
        return Carbon::parse($datetime, Booking::timezoneManila());
    }

    #[Test]
    public function check_in_is_blocked_when_check_in_date_is_not_today(): void
    {
        $booking = new Booking([
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_PAID,
        ]);
        $booking->check_in = self::manila('2026-04-22 14:00:00');

        Carbon::setTestNow(self::manila('2026-04-21 10:00:00'));

        try {
            $assessment = BookingCheckInEligibility::assess($booking);

            $this->assertFalse($assessment['allowed']);
            $this->assertSame(BookingCheckInEligibility::REASON_OUTSIDE_CHECK_IN_DAY, $assessment['reason']);
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function check_in_is_blocked_when_booking_is_already_occupied(): void
    {
        $booking = new Booking([
            'booking_status' => Booking::BOOKING_STATUS_OCCUPIED,
            'payment_status' => Booking::PAYMENT_STATUS_PAID,
        ]);
        $booking->check_in = self::manila('2026-04-21 14:00:00');

        Carbon::setTestNow(self::manila('2026-04-21 10:00:00'));

        try {
            $assessment = BookingCheckInEligibility::assess($booking);

            $this->assertFalse($assessment['allowed']);
            $this->assertSame(BookingCheckInEligibility::REASON_INVALID_STATUS, $assessment['reason']);
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function check_in_day_predicate_returns_true_when_check_in_date_is_today(): void
    {
        $booking = new Booking([
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_PAID,
        ]);
        $booking->check_in = self::manila('2026-04-21 14:00:00');

        Carbon::setTestNow(self::manila('2026-04-21 10:00:00'));

        try {
            $this->assertTrue($booking->isCheckInTodayManila());
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function complete_is_blocked_when_check_out_date_is_not_today(): void
    {
        $booking = new Booking([
            'booking_status' => Booking::BOOKING_STATUS_OCCUPIED,
            'payment_status' => Booking::PAYMENT_STATUS_PAID,
        ]);
        $booking->check_in = self::manila('2026-04-22 14:00:00');
        $booking->check_out = self::manila('2026-04-25 10:00:00');

        Carbon::setTestNow(self::manila('2026-04-21 10:00:00'));

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Cannot complete an unsaved booking.');

            BookingLifecycleActions::complete($booking);
        } finally {
            Carbon::setTestNow();
        }
    }
}
