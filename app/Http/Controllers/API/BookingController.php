<?php

namespace App\Http\Controllers\API;

use App\Events\BookingCancelled;
use App\Events\BookingRescheduled;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\Venue;
use App\Services\BookingActionOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BookingController extends Controller
{
    public function __construct(
        private BookingActionOtpService $bookingActionOtpService,
    ) {}

    /**
     * Send SMS OTP for cancel or reschedule (Semaphore).
     */
    public function sendBookingOtp(Request $request, Booking $booking)
    {
        $request->validate([
            'purpose' => 'required|in:cancel,reschedule',
        ]);

        $purpose = (string) $request->input('purpose');

        if ($purpose === BookingActionOtpService::PURPOSE_CANCEL) {
            $allowedStatuses = [
                Booking::STATUS_UNPAID,
                Booking::STATUS_CONFIRMED,
            ];

            if (defined(Booking::class.'::STATUS_RESCHEDULED')) {
                $allowedStatuses[] = Booking::STATUS_RESCHEDULED;
            }

            if (! in_array($booking->status, $allowedStatuses, true)) {
                return response()->json([
                    'message' => 'Booking cannot be cancelled in its current state.',
                ], 422);
            }
        } else {
            if (in_array($booking->status, [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED], true)) {
                return response()->json([
                    'message' => 'Cannot reschedule this booking.',
                ], 422);
            }
        }

        $this->bookingActionOtpService->send($booking, $purpose);

        return response()->json([
            'message' => 'Verification code sent.',
        ]);
    }

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
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error retrieving bookings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a booking by reference number (frontend QR lookup).
     */
    public function showByReferenceNumber(string $reference)
    {
        try {
            $booking = Booking::with(['guest', 'rooms', 'venues'])
                ->where('reference_number', $reference)
                ->first();

            if (! $booking) {
                return response()->json([
                    'message' => 'Booking not found',
                ], 404);
            }

            $hasTestimonial = $booking->reviews()->exists();

            $this->ensureBookingQrExists($booking);

            $filename = $booking->qr_code ? basename($booking->qr_code) : null;

            return response()->json([
                'booking' => $booking->fresh(['guest', 'rooms', 'venues']),
                'qr_code_url' => $filename ? url("/qr-image/{$filename}") : null,
                'has_testimonial' => $hasTestimonial,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error retrieving booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StoreBookingRequest $request)
    {
        $validated = $request->validated();

        $hasRooms = isset($validated['rooms']) && is_array($validated['rooms']) && count($validated['rooms']) > 0;
        $hasVenues = isset($validated['venues']) && is_array($validated['venues']) && count($validated['venues']) > 0;

        if (! $hasRooms && ! $hasVenues) {
            return response()->json([
                'message' => 'Must select at least one room or one venue.',
                'error' => 'accommodation_required',
            ], 422);
        }

        try {
            $checkIn = Carbon::createFromFormat('M d, Y', $validated['check_in'])->startOfDay();
            $checkOut = Carbon::createFromFormat('M d, Y', $validated['check_out'])->endOfDay();

            if ($checkOut->lt($checkIn)) {
                return response()->json([
                    'message' => 'Invalid date range',
                    'error' => 'Check-out cannot be before check-in',
                ], 422);
            }

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
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all()
                : [];

            $venueIds = $hasVenues
                ? collect($validated['venues'])
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->values()
                    ->all()
                : [];

            if ($hasRooms) {
                $existingRoomIds = Room::whereIn('id', $roomIds)->pluck('id')->all();

                if (count($existingRoomIds) !== count($roomIds)) {
                    return response()->json([
                        'message' => 'One or more selected rooms do not exist',
                    ], 422);
                }

                $availableRoomIds = Room::whereIn('id', $roomIds)
                    ->availableBetween($checkIn, $checkOut)
                    ->pluck('id')
                    ->all();

                $conflictingRoomIds = array_values(array_diff($roomIds, $availableRoomIds));

                if (! empty($conflictingRoomIds)) {
                    $conflictingRooms = Room::whereIn('id', $conflictingRoomIds)
                        ->get(['id', 'name']);

                    return response()->json([
                        'message' => 'Booking conflict: one or more rooms are not available for the selected dates (already booked or blocked).',
                        'error' => 'date_range_conflict',
                        'conflicts' => [
                            'rooms' => $conflictingRooms
                                ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])
                                ->values()
                                ->all(),
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

                if (! empty($conflictingVenueIds)) {
                    $conflictingVenues = Venue::whereIn('id', $conflictingVenueIds)
                        ->get(['id', 'name']);

                    return response()->json([
                        'message' => 'Booking conflict: one or more venues are already booked for the selected dates.',
                        'error' => 'date_range_conflict',
                        'conflicts' => [
                            'venues' => $conflictingVenues
                                ->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])
                                ->values()
                                ->all(),
                        ],
                    ], 422);
                }
            }

            $booking = DB::transaction(function () use (
                $guest,
                $validated,
                $checkIn,
                $checkOut,
                $roomIds,
                $venueIds
            ) {
                $booking = Booking::create([
                    'guest_id' => $guest->id,
                    'reference_number' => $validated['reference_number'] ?? null,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'no_of_days' => $validated['days'],
                    'total_price' => $validated['total_price'],
                    'status' => Booking::STATUS_UNPAID,
                ]);

                $this->generateBookingQr($booking);

                if (! empty($roomIds)) {
                    $booking->rooms()->attach($roomIds);
                }

                if (! empty($venueIds)) {
                    $booking->venues()->attach($venueIds);
                }

                return $booking->fresh(['guest', 'rooms', 'venues']);
            });

            return response()->json([
                'message' => 'Booking created successfully',
                'guest' => $guest,
                'booking' => $booking,
                'total_price' => $validated['total_price'],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to create booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a specific booking.
     */
    public function show($id)
    {
        try {
            $booking = Booking::with(['guest', 'rooms', 'venues'])->find($id);

            if (! $booking) {
                return response()->json([
                    'message' => 'Booking not found',
                ], 404);
            }

            return response()->json($booking, 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error retrieving booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $booking = Booking::with(['guest', 'rooms', 'venues'])->find($id);

            if (! $booking) {
                return response()->json([
                    'message' => 'Booking not found',
                ], 404);
            }

            $validated = $request->validate([
                'status' => 'sometimes|string|in:'.implode(',', [
                    Booking::STATUS_UNPAID,
                    Booking::STATUS_PAID,
                    Booking::STATUS_CONFIRMED,
                    Booking::STATUS_COMPLETED,
                    Booking::STATUS_OCCUPIED,
                    Booking::STATUS_CANCELLED,
                ]),
            ]);

            if (! empty($validated['status'])) {
                $booking->update([
                    'status' => $validated['status'],
                ]);
            }

            $booking->refresh()->load(['guest', 'rooms', 'venues']);

            return response()->json([
                'message' => 'Booking updated successfully',
                'booking' => $booking,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error updating booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $booking = Booking::find($id);

            if (! $booking) {
                return response()->json([
                    'message' => 'Booking not found',
                ], 404);
            }

            $booking->delete();

            return response()->json([
                'message' => 'Booking deleted successfully',
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error deleting booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function cancel(Request $request, Booking $booking)
    {
        $request->validate([
            'otp' => 'required|string',
        ]);

        try {
            $allowedStatuses = [
                Booking::STATUS_UNPAID,
                Booking::STATUS_CONFIRMED,
            ];

            if (defined(Booking::class.'::STATUS_RESCHEDULED')) {
                $allowedStatuses[] = Booking::STATUS_RESCHEDULED;
            }

            if (! in_array($booking->status, $allowedStatuses, true)) {
                return response()->json([
                    'message' => 'Booking cannot be cancelled in its current state.',
                ], 422);
            }

            if (! $this->bookingActionOtpService->verifyAndConsume(
                $booking->reference_number,
                BookingActionOtpService::PURPOSE_CANCEL,
                (string) $request->input('otp'),
            )) {
                return response()->json([
                    'message' => 'Invalid or expired verification code.',
                ], 422);
            }

            $booking->update([
                'status' => Booking::STATUS_CANCELLED,
            ]);

            broadcast(new BookingCancelled($booking))->toOthers();

            return response()->json([
                'message' => 'Booking cancelled successfully.',
                'booking' => $booking,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error cancelling booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function reschedule(Request $request, $reference)
    {
        $request->validate([
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'otp' => 'required|string',
        ]);

        $booking = Booking::where('reference_number', $reference)->firstOrFail();

        if (in_array($booking->status, ['cancelled', 'completed'], true)) {
            return response()->json([
                'message' => 'Cannot reschedule this booking',
            ], 422);
        }

        $checkIn = \Carbon\Carbon::parse($request->check_in)->startOfDay();
        $checkOut = \Carbon\Carbon::parse($request->check_out)->endOfDay();

        $roomIds = $booking->rooms->pluck('id')->toArray();
        if (! empty($roomIds)) {
            $availableRoomIds = Room::whereIn('id', $roomIds)
                ->availableBetween($checkIn, $checkOut, $booking->id)
                ->pluck('id')
                ->toArray();

            if (count($availableRoomIds) !== count($roomIds)) {
                return response()->json([
                    'message' => 'One or more currently booked rooms are not available for the new dates',
                ], 422);
            }
        }

        $venueIds = $booking->venues->pluck('id')->toArray();
        if (! empty($venueIds)) {
            $availableVenueIds = Venue::whereIn('id', $venueIds)
                ->availableBetween($checkIn, $checkOut, $booking->id)
                ->pluck('id')
                ->toArray();

            if (count($availableVenueIds) !== count($venueIds)) {
                return response()->json([
                    'message' => 'One or more currently booked venues are not available for the new dates',
                ], 422);
            }
        }

        if (! $this->bookingActionOtpService->verifyAndConsume(
            $booking->reference_number,
            BookingActionOtpService::PURPOSE_RESCHEDULE,
            (string) $request->input('otp'),
        )) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        $nights = \Carbon\Carbon::parse($request->check_in)
            ->diffInDays(\Carbon\Carbon::parse($request->check_out));

        $newTotal = 0;

        if ($booking->rooms && $booking->rooms->count()) {
            foreach ($booking->rooms as $room) {
                $newTotal += $room->price * $nights;
            }
        }

        if ($booking->venues && $booking->venues->count()) {
            foreach ($booking->venues as $venue) {
                $newTotal += $venue->price;
            }
        }

        $booking->update([
            'check_in' => $request->check_in,
            'check_out' => $request->check_out,
            'total_price' => $newTotal,
            'status' => 'rescheduled',
        ]);

        broadcast(new BookingRescheduled($booking))->toOthers();

        return response()->json([
            'message' => 'Booking rescheduled successfully',
            'booking' => $booking,
        ]);
    }

    private function ensureBookingQrExists(Booking $booking): void
    {
        if ($booking->qr_code && Storage::disk('public')->exists($booking->qr_code)) {
            return;
        }

        $this->generateBookingQr($booking, $booking->qr_code ? basename($booking->qr_code) : null);
    }

    private function generateBookingQr(Booking $booking, ?string $filename = null): string
    {
        $payload = json_encode([
            'booking_id' => $booking->id,
            'reference_number' => $booking->reference_number,
            'guest_id' => $booking->guest_id,
        ]);

        $filename = $filename ?: Str::uuid().'.svg';
        $path = 'qr/bookings/'.$filename;

        $svg = QrCode::format('svg')->size(300)->generate($payload);

        Storage::disk('public')->put($path, $svg);

        if ($booking->qr_code !== $path) {
            $booking->update([
                'qr_code' => $path,
            ]);
        }

        return $path;
    }
}
