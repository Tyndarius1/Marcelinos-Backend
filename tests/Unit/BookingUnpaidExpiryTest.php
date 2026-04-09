<?php

namespace Tests\Unit;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingUnpaidExpiryTest extends TestCase
{
    private static function manila(string $datetime): Carbon
    {
        return Carbon::parse($datetime, Booking::timezoneManila());
    }

    #[Test]
    public function future_check_in_is_not_expired_three_days_after_booking(): void
    {
        $booking = new Booking([
            'status' => Booking::STATUS_UNPAID,
        ]);
        $booking->created_at = self::manila('2026-04-01 10:00:00');
        $booking->check_in = self::manila('2026-04-20 14:00:00');

        $at = self::manila('2026-04-04 13:00:00');

        $this->assertTrue($booking->isCheckInStrictlyAfterTodayManila($at));
        $this->assertFalse($booking->isExpiredUnpaid($at));
    }

    #[Test]
    public function future_check_in_expires_at_or_after_check_in_day_noon_manila(): void
    {
        $booking = new Booking([
            'status' => Booking::STATUS_UNPAID,
        ]);
        $booking->created_at = self::manila('2026-04-01 10:00:00');
        $booking->check_in = self::manila('2026-04-20 14:00:00');

        $beforeNoon = self::manila('2026-04-20 11:59:00');
        $this->assertFalse($booking->isExpiredUnpaid($beforeNoon));

        $afterNoon = self::manila('2026-04-20 12:00:00');
        $this->assertTrue($booking->isExpiredUnpaid($afterNoon));
    }

    /**
     * Short-lead booking (created the day before check-in): still expires after check-in day noon.
     * The cancel-unpaid command must evaluate all unpaid rows so this is not skipped by a created_at cutoff.
     */
    #[Test]
    public function check_in_day_expires_after_noon_when_booking_created_one_day_before_check_in(): void
    {
        $booking = new Booking([
            'status' => Booking::STATUS_UNPAID,
        ]);
        $booking->created_at = self::manila('2026-04-14 10:00:00');
        $booking->check_in = self::manila('2026-04-15 14:00:00');

        $this->assertFalse($booking->isExpiredUnpaid(self::manila('2026-04-15 11:00:00')));
        $this->assertTrue($booking->isExpiredUnpaid(self::manila('2026-04-15 13:00:00')));
    }

    #[Test]
    public function messenger_path_omits_unpaid_expires_at_for_receipt(): void
    {
        $booking = new Booking([
            'status' => Booking::STATUS_UNPAID,
        ]);
        $booking->created_at = self::manila('2026-04-10 10:00:00');
        $booking->check_in = self::manila('2026-04-16 14:00:00');

        Carbon::setTestNow(self::manila('2026-04-10 12:00:00'));

        try {
            $this->assertTrue($booking->useMessengerDepositInstructions());
            $this->assertNull($booking->unpaidExpiresAt());
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function legacy_same_calendar_day_booking_uses_three_day_rule_before_noon_on_check_in_day(): void
    {
        $booking = new Booking([
            'status' => Booking::STATUS_UNPAID,
        ]);
        $booking->created_at = self::manila('2026-04-10 09:00:00');
        $booking->check_in = self::manila('2026-04-10 15:00:00');

        $morning = self::manila('2026-04-10 11:00:00');
        $this->assertFalse($booking->isExpiredUnpaid($morning));

        $afterCheckInNoon = self::manila('2026-04-10 12:30:00');
        $this->assertTrue($booking->isExpiredUnpaid($afterCheckInNoon));
    }

    #[Test]
    public function past_check_in_day_is_expired_when_unpaid(): void
    {
        $booking = new Booking([
            'status' => Booking::STATUS_UNPAID,
        ]);
        $booking->created_at = self::manila('2026-04-01 10:00:00');
        $booking->check_in = self::manila('2026-04-05 14:00:00');

        $at = self::manila('2026-04-06 10:00:00');
        $this->assertTrue($booking->isExpiredUnpaid($at));
    }

    #[Test]
    public function legacy_unpaid_expires_at_is_present_when_not_messenger_path(): void
    {
        $booking = new Booking([
            'status' => Booking::STATUS_UNPAID,
        ]);
        $booking->created_at = self::manila('2026-04-10 09:00:00');
        $booking->check_in = self::manila('2026-04-10 15:00:00');

        Carbon::setTestNow(self::manila('2026-04-10 11:00:00'));

        try {
            $this->assertFalse($booking->useMessengerDepositInstructions());
            $expires = $booking->unpaidExpiresAt();
            $this->assertNotNull($expires);
            $this->assertSame('2026-04-13', $expires->timezone(Booking::timezoneManila())->format('Y-m-d'));
        } finally {
            Carbon::setTestNow();
        }
    }
}
