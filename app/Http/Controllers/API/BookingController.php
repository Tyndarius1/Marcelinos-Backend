<?php

namespace App\Http\Controllers\API;

use App\Events\BookingCancelled;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Venue;
use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BookingController extends Controller
{
    //
    /**
     * Display all bookings (paginated).
     */
    public function index(Request $request)
    {
        try {
            $perPage = min((int) $request->query('per_page', 15), 50);
            $bookings = Booking::with(['guest', 'rooms', 'venues'])
                ->orderByDesc('created_at')
                ->paginate($perPage);

            return response()->json($bookings, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving bookings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showByReference(string $reference)
    {
        try {
            $booking = Booking::with(['guest', 'rooms', 'venues'])
                ->where('reference_number', $reference)
                ->first();

            if (!$booking) {
                return response()->json(['message' => 'Receipt not found'], 404);
            }

            // Convert check_in/check_out to Carbon safely
            $check_in = Carbon::parse($booking->check_in);
            $check_out = Carbon::parse($booking->check_out);
            $issued_on = Carbon::parse($booking->created_at);

            return response()->json([
                'reference_number' => $booking->reference_number,
                'created_at' => $booking->created_at->format('M d, Y'),
                'booking_status' => $booking->status,
                'check_in' => $check_in->format('M d, Y'),
                'check_out' => $check_out->format('M d, Y'),
                'issued_on' => $issued_on->format('M d, Y'),
                'nights' => $booking->no_of_days,
                'guest_name' => $booking->guest->last_name . ' ' . $booking->guest->first_name,
                'guest_email' => $booking->guest->email,
                'guest_contact' => $booking->guest->contact_num,
                'guest_address' => implode(', ', array_filter([
                    $booking->guest->barangay,
                    $booking->guest->municipality,
                    $booking->guest->province,
                    $booking->guest->region,
                    $booking->guest->country,
                ])),
                'rooms' => $booking->rooms->map(fn($room) => [
                    'name' => $room->name,
                    'type' => $room->type,
                    'capacity' => $room->capacity,
                    'price' => $room->price,
                ])->all(),
                'venues' => $booking->venues->map(fn($venue) => [
                    'name' => $venue->name,
                    'capacity' => $venue->capacity,
                    'price' => $venue->price,
                ])->all(),
                'subtotal' => $booking->total_price,
                'grand_total' => $booking->total_price,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load receipt',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a booking by reference number (frontend QR lookup)
     */
    public function showByReferenceNumber(string $reference)
    {
        try {
            $booking = Booking::with(['guest', 'rooms', 'venues'])
                ->where('reference_number', $reference)
                ->first();

            if (!$booking) {
                return response()->json(['message' => 'Booking not found'], 404);
            }

            $hasTestimonial = $booking->reviews()->exists();

            $filename = $booking->qr_code
                ? basename($booking->qr_code)
                : null;

            return response()->json([
                'booking' => $booking,
                'qr_code_url' => $filename
                    ? url("/qr-image/{$filename}")
                    : null,
                'has_testimonial' => $hasTestimonial,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function store(StoreBookingRequest $request)
    {
        $validated = $request->validated();

        // At least either rooms or venues must be provided AND not both empty
        $hasRooms = isset($validated['rooms']) && is_array($validated['rooms']) && count($validated['rooms']) > 0;
        $hasVenues = isset($validated['venues']) && is_array($validated['venues']) && count($validated['venues']) > 0;

        if (!$hasRooms && !$hasVenues) {
            return response()->json([
                'message' => 'Must select at least one room or one venue.',
                'error' => 'accommodation_required',
            ], 422);
        }

        try {
            $checkIn = Carbon::createFromFormat('M d, Y', $validated['check_in'])->startOfDay();
            $checkOut = Carbon::createFromFormat('M d, Y', $validated['check_out'])->endOfDay();

            // Logical date validation
            if ($checkOut->lt($checkIn)) {
                return response()->json([
                    'message' => 'Invalid date range',
                    'error' => 'Check-out cannot be before check-in',
                ], 422);
            }

            // Store Guest first
            $guest = Guest::store($request);

            $roomIds = $hasRooms
                ? collect($validated['rooms'])
                    ->map(function ($room) {
                        if (is_array($room)) {
                            return $room['id'] ?? ($room[0] ?? null);
                        }
                        return $room;
                    })
                    ->filter()
                    ->map(fn($id) => (int) $id)
                    ->values()
                    ->all()
                : [];

            $venueIds = $hasVenues
                ? collect($validated['venues'])
                    ->map(fn($id) => (int) $id)
                    ->filter()
                    ->values()
                    ->all()
                : [];

            // Fail early if any provided room does not actually exist
            if ($hasRooms) {
                $existingRoomIds = Room::whereIn('id', $roomIds)->pluck('id')->all();
                if (count($existingRoomIds) !== count($roomIds)) {
                    return response()->json([
                        'message' => 'One or more selected rooms do not exist',
                    ], 422);
                }

                /*
                |--------------------------------------------------------------------------
                | Prevent booking conflict: no double-booking within date range
                |--------------------------------------------------------------------------
                */
                $availableRoomIds = Room::whereIn('id', $roomIds)
                    ->availableBetween($checkIn, $checkOut)
                    ->pluck('id')
                    ->all();
                $conflictingRoomIds = array_values(array_diff($roomIds, $availableRoomIds));

                if (!empty($conflictingRoomIds)) {
                    $conflictingRooms = Room::whereIn('id', $conflictingRoomIds)->get(['id', 'name']);
                    return response()->json([
                        'message' => 'Booking conflict: one or more rooms are already booked for the selected dates.',
                        'error' => 'date_range_conflict',
                        'conflicts' => [
                            'rooms' => $conflictingRooms->map(fn($r) => ['id' => $r->id, 'name' => $r->name])->values()->all(),
                        ],
                    ], 422);
                }
            }

            if ($hasVenues) {
                $availableVenueIds = Venue::whereIn('id', $venueIds)
                    ->availableBetween($checkIn, $checkOut)
                    ->pluck('id')
                    ->all();
                $conflictingVenueIds = array_values(array_diff($venueIds, $availableVenueIds));

                if (!empty($conflictingVenueIds)) {
                    $conflictingVenues = Venue::whereIn('id', $conflictingVenueIds)->get(['id', 'name']);
                    return response()->json([
                        'message' => 'Booking conflict: one or more venues are already booked for the selected dates.',
                        'error' => 'date_range_conflict',
                        'conflicts' => [
                            'venues' => $conflictingVenues->map(fn($v) => ['id' => $v->id, 'name' => $v->name])->values()->all(),
                        ],
                    ], 422);
                }
            }

            // Single booking row; attach multiple rooms and venues
            $booking = Booking::create([
                'guest_id' => $guest->id,
                'reference_number' => $validated['reference_number'] ?? null, // model auto-generates if null
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'no_of_days' => $validated['days'],
                'total_price' => $validated['total_price'],
                'status' => Booking::STATUS_UNPAID,
            ]);

            if (!empty($roomIds)) {
                $booking->rooms()->attach($roomIds);
            }
            if (!empty($venueIds)) {
                $booking->venues()->attach($venueIds);
            }

            $booking->load(['guest', 'rooms', 'venues']);

            return response()->json([
                'message' => 'Booking created successfully',
                'guest' => $guest,
                'booking' => $booking,
                'total_price' => $validated['total_price'],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a specific booking
     */
    public function show($id)
    {
        try {
            $booking = Booking::with(['guest', 'rooms', 'venues'])->find($id);

            if (!$booking) {
                return response()->json(['message' => 'Booking not found'], 404);
            }

            return response()->json($booking, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $booking = Booking::find($id);

            if (!$booking) {
                return response()->json(['message' => 'Booking not found'], 404);
            }

            $booking->delete();

            return response()->json(['message' => 'Booking deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cancel(Request $request, Booking $booking)
    {
        try {
            if (!in_array($booking->status, [Booking::STATUS_UNPAID, Booking::STATUS_CONFIRMED])) {
                return response()->json([
                    'message' => 'Booking cannot be cancelled in its current state.'
                ], 422);
            }

            $booking->update([
                'status' => Booking::STATUS_CANCELLED
            ]);

            broadcast(new BookingCancelled($booking))->toOthers();

            return response()->json([
                'message' => 'Booking cancelled successfully.',
                'booking' => $booking
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error cancelling booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}