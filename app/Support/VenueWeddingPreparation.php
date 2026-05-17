<?php

namespace App\Support;

use App\Models\Booking;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One calendar day (Asia/Manila) of venue prep before check-in for wedding + venue bookings.
 * Used to align venue availability and conflict checks across API, admin, and detectors.
 */
final class VenueWeddingPreparation
{
    /**
     * Start of the venue "block" for interval overlap: prep day 00:00 (Manila) for wedding+venues, else the given check-in instant.
     */
    public static function effectiveVenueBlockStart(
        Carbon|DateTimeInterface|string $checkIn,
        ?string $venueEventType,
        bool $hasVenues,
    ): Carbon {
        $in = $checkIn instanceof Carbon ? $checkIn->copy() : Carbon::parse($checkIn);
        if (! $hasVenues) {
            return $in;
        }
        if (BookingPricing::normalizeVenueEventType($venueEventType) !== BookingPricing::VENUE_EVENT_WEDDING) {
            return $in;
        }
        $tz = Booking::timezoneManila();

        return $in->copy()->timezone($tz)->startOfDay()->subDay();
    }

    /**
     * SQL expression for: datetime at 00:00:00 of the calendar day before `bookings.check_in`, for use when the row is wedding+venue.
     *
     * @param  'bookings'|string  $tableAlias
     */
    public static function weddingBlockStartDateTimeSql(string $tableAlias = 'bookings'): string
    {
        $c = $tableAlias === '' ? 'check_in' : $tableAlias.'.check_in';
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "datetime(date({$c}, '-1 day') || ' 00:00:00')",
            'pgsql' => "date_trunc('day', {$c}::timestamp) - interval '1 day'",
            default => "TIMESTAMP(DATE_SUB(DATE({$c}), INTERVAL 1 DAY))", // mysql / others
        };
    }

    /**
     * Constrain a booking subquery to rows whose venue use overlaps a candidate's venue window
     * (candidate uses {@see effectiveVenueBlockStart} and check_out).
     *
     * @param  Builder<Booking>  $bookingQuery
     */
    public static function constrainToBookingsThatCollideWithVenueCandidateRange(
        Builder $bookingQuery,
        CarbonInterface $effClientStart,
        CarbonInterface $clientCheckOut,
        ?int $excludeBookingId = null,
    ): void {
        if ($excludeBookingId) {
            $bookingQuery->where('bookings.id', '!=', $excludeBookingId);
        }

        $checkOut = $clientCheckOut->format('Y-m-d H:i:s');
        $eff = $effClientStart->format('Y-m-d H:i:s');
        $expr = self::weddingBlockStartDateTimeSql();

        $bookingQuery->where(function ($w) use ($expr, $checkOut, $eff): void {
            $w->where(function ($n) use ($checkOut, $eff): void {
                $n->where(function ($x): void {
                    // SQL `NULL != 'wedding'` is unknown — treat null/missing type as non-wedding overlap.
                    $x->where(function ($typeQ): void {
                        $typeQ->whereNull('bookings.venue_event_type')
                            ->orWhere('bookings.venue_event_type', '!=', BookingPricing::VENUE_EVENT_WEDDING);
                    })->orWhereDoesntHave('venues');
                })
                    ->where('bookings.check_in', '<', $checkOut)
                    ->where('bookings.check_out', '>', $eff);
            })->orWhere(function ($r) use ($expr, $checkOut, $eff): void {
                $r->where('bookings.venue_event_type', BookingPricing::VENUE_EVENT_WEDDING)
                    ->whereHas('venues')
                    ->whereRaw("({$expr}) < ?", [$checkOut])
                    ->where('bookings.check_out', '>', $eff);
            });
        });
    }
}
