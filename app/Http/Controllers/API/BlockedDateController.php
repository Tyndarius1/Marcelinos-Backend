<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class BlockedDateController extends Controller
{
    /**
     * Return all blocked dates as JSON.
     * Includes:
     * - Manually blocked dates from BlockedDate table
     * - Dates where all rooms or all venues are fully booked
     */
    public function index(): JsonResponse
    {
        try {
            $today = Carbon::today()->toDateString();
            $blockedDates = collect();

            // 1. Get manually blocked dates
            $manualBlockedDates = BlockedDate::select('date', 'reason')
                ->whereDate('date', '>=', $today)
                ->get()
                ->map(fn($d) => [
                    'date' => $d->date,
                    'reason' => $d->reason,
                ]);
            $blockedDates = $blockedDates->merge($manualBlockedDates);

            // 2. Get dates blocked by fully booked rooms/venues
            $bookingBlockedDates = $this->getBookingBlockedDates();
            $blockedDates = $blockedDates->merge($bookingBlockedDates);

            // Unique and sort by date
            $blockedDates = $blockedDates
                ->filter(fn($d) => isset($d['date']) && $d['date'] >= $today)
                ->unique(fn($d) => $d['date'])
                ->sortBy('date')
                ->values();

            return response()->json(['blocked_dates' => $blockedDates]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving blocked dates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all dates blocked by confirmed or occupied bookings
     * where all rooms and/or all venues are booked.
     */
    private function getBookingBlockedDates(): array
    {
        $blockedDates = [];

        $totalRooms = Room::count();
        $totalVenues = Venue::count();

        $bookings = Booking::with(['rooms', 'venues'])
            ->whereIn('status', [
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_OCCUPIED,
            ])
            ->get();

        if ($bookings->isEmpty()) {
            return $blockedDates;
        }

        $roomCountPerDate = [];
        $venueCountPerDate = [];

        foreach ($bookings as $booking) {
            $checkIn = Carbon::parse($booking->check_in);
            $checkOut = Carbon::parse($booking->check_out);

            $dates = $this->getDateRange($checkIn, $checkOut);

            $bookedRoomCount = $booking->rooms->count();
            $bookedVenueCount = $booking->venues->count();

            foreach ($dates as $date) {
                $roomCountPerDate[$date] = ($roomCountPerDate[$date] ?? 0) + $bookedRoomCount;
                $venueCountPerDate[$date] = ($venueCountPerDate[$date] ?? 0) + $bookedVenueCount;
            }
        }

        // Add dates where all rooms are fully booked
        foreach ($roomCountPerDate as $date => $count) {
            if ($count >= $totalRooms) {
                $blockedDates[] = [
                    'date' => $date,
                    'reason' => 'Fully booked',
                ];
            }
        }

        // Add dates where all venues are fully booked
        foreach ($venueCountPerDate as $date => $count) {
            if ($count >= $totalVenues) {
                $blockedDates[] = [
                    'date' => $date,
                    'reason' => 'Fully booked',
                ];
            }
        }

        return $blockedDates;
    }

    /**
     * Get all dates between check_in (inclusive) and check_out (exclusive).
     * Returns array of date strings in Y-m-d format.
     */
    private function getDateRange(Carbon $checkIn, Carbon $checkOut): array
    {
        $dates = [];
        $current = $checkIn->copy();

        while ($current->lt($checkOut)) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }

        return $dates;
    }
}