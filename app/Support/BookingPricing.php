<?php

namespace App\Support;

use App\Models\Room;
use App\Models\Venue;

class BookingPricing
{
    public const VENUE_EVENT_WEDDING = 'wedding';

    public const VENUE_EVENT_BIRTHDAY = 'birthday';

    public const VENUE_EVENT_SEMINAR = 'seminar';

    /** @return array<string, string> */
    public static function venueEventTypeOptions(): array
    {
        return [
            self::VENUE_EVENT_WEDDING => 'Wedding (full price)',
            self::VENUE_EVENT_BIRTHDAY => 'Birthday (full price)',
            self::VENUE_EVENT_SEMINAR => 'Seminar (seminar rate)',
        ];
    }

    public static function venueUnitPrice(Venue $venue, ?string $venueEventType): float
    {
        $t = $venueEventType ?? self::VENUE_EVENT_WEDDING;

        if ($t === self::VENUE_EVENT_SEMINAR) {
            return (float) $venue->seminar_price;
        }

        return (float) $venue->price;
    }

    /**
     * @param  iterable<Venue>  $venues
     */
    public static function sumVenueLine(iterable $venues, ?string $venueEventType): float
    {
        $sum = 0.0;
        foreach ($venues as $venue) {
            $sum += self::venueUnitPrice($venue, $venueEventType);
        }

        return $sum;
    }

    /**
     * @param  iterable<Room>  $rooms
     * @param  iterable<Venue>  $venues
     */
    public static function expectedTotal(int $days, iterable $rooms, iterable $venues, ?string $venueEventType): float
    {
        $days = max(1, $days);
        $roomsTotal = 0.0;
        foreach ($rooms as $room) {
            $roomsTotal += (float) $room->price;
        }
        $venuesTotal = self::sumVenueLine($venues, $venueEventType);

        return ($roomsTotal + $venuesTotal) * $days;
    }

    /**
     * @param  iterable<array{quantity: int, unit_price_per_night: float|string}|object>  $roomLines
     * @param  iterable<Venue>  $venues
     */
    public static function expectedTotalFromRoomLines(int $days, iterable $roomLines, iterable $venues, ?string $venueEventType): float
    {
        $days = max(1, $days);
        $roomsTotal = 0.0;
        foreach ($roomLines as $line) {
            $qty = is_array($line) ? (int) ($line['quantity'] ?? 0) : (int) ($line->quantity ?? 0);
            $unit = is_array($line)
                ? (float) ($line['unit_price_per_night'] ?? $line['unit_price'] ?? 0)
                : (float) ($line->unit_price_per_night ?? 0);
            $roomsTotal += $qty * $unit;
        }
        $venuesTotal = self::sumVenueLine($venues, $venueEventType);

        return ($roomsTotal + $venuesTotal) * $days;
    }

    public static function totalsMatch(float $expected, float $submitted, float $epsilon = 0.02): bool
    {
        return abs($expected - $submitted) <= $epsilon;
    }
}
