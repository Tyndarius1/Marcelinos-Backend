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
use App\Support\BookingPricing;
use App\Support\RoomInventoryGroupKey;
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

            $bookings = Booking::with(['guest', 'rooms', 'venues', 'roomLines'])
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
            $booking = Booking::with(['guest', 'rooms', 'venues', 'roomLines'])
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
                'booking' => $booking->fresh(['guest', 'rooms', 'venues', 'roomLines']),
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

        $roomLines = isset($validated['room_lines']) && is_array($validated['room_lines'])
            ? $validated['room_lines']
            : [];
        $hasRoomLines = count($roomLines) > 0;
        $hasVenues = isset($validated['venues']) && is_array($validated['venues']) && count($validated['venues']) > 0;

        if (! $hasRoomLines && ! $hasVenues) {
            return response()->json([
                'message' => 'Must select at least one room type or one venue.',
                'error' => 'accommodation_required',
            ], 422);
        }

        try {
            $checkInDate = Carbon::createFromFormat('M d, Y', $validated['check_in'])->startOfDay();
            $checkOutDate = Carbon::createFromFormat('M d, Y', $validated['check_out'])->startOfDay();

            if ($checkOutDate->lt($checkInDate)) {
                return response()->json([
                    'message' => 'Invalid date range',
                    'error' => 'Check-out cannot be before check-in',
                ], 422);
            }

            $hasRoomComponent = $hasRoomLines;
            [$checkIn, $checkOut] = $this->bookingWindowForStorage($hasRoomComponent, $checkInDate, $checkOutDate);

            $guest = Guest::store($request);

            $venueIds = $hasVenues
                ? collect($validated['venues'])
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->values()
                    ->all()
                : [];

            if ($hasRoomLines) {
                $roomLineError = $this->validateGuestRoomLines($roomLines, $checkIn, $checkOut, null);
                if ($roomLineError !== null) {
                    return $roomLineError;
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

            $venueEventType = $hasVenues
                ? ($validated['venue_event_type'] ?? BookingPricing::VENUE_EVENT_WEDDING)
                : null;

            $expectedTotal = BookingPricing::expectedTotalFromRoomLines(
                (int) $validated['days'],
                $roomLines,
                $hasVenues ? Venue::whereIn('id', $venueIds)->get() : collect(),
                $venueEventType,
            );

            if (! BookingPricing::totalsMatch($expectedTotal, (float) $validated['total_price'])) {
                return response()->json([
                    'message' => 'Total price does not match the selected room types, venues, and event type.',
                    'error' => 'price_mismatch',
                ], 422);
            }

            $booking = DB::transaction(function () use (
                $guest,
                $validated,
                $checkIn,
                $checkOut,
                $roomLines,
                $venueIds,
                $venueEventType,
                $expectedTotal
            ) {
                $booking = Booking::create([
                    'guest_id' => $guest->id,
                    'reference_number' => $validated['reference_number'] ?? null,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'no_of_days' => $validated['days'],
                    'venue_event_type' => $venueEventType,
                    'total_price' => $expectedTotal,
                    'status' => Booking::STATUS_UNPAID,
                ]);

                $this->generateBookingQr($booking);

                foreach ($roomLines as $line) {
                    $booking->roomLines()->create([
                        'room_type' => $line['room_type'],
                        'inventory_group_key' => $line['inventory_group_key'],
                        'quantity' => (int) $line['quantity'],
                        'unit_price_per_night' => (float) $line['unit_price'],
                    ]);
                }

                if (! empty($venueIds)) {
                    $booking->venues()->attach($venueIds);
                }

                return $booking->fresh(['guest', 'rooms', 'venues', 'roomLines']);
            });

            return response()->json([
                'message' => 'Booking created successfully',
                'guest' => $guest,
                'booking' => $booking,
                'total_price' => $expectedTotal,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to create booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Room stays: check-in 12:00 PM, check-out 10:00 AM (local) on the selected calendar dates.
     * Venue-only: full-day window (start of first day → end of last day) for availability overlap.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    private function bookingWindowForStorage(bool $hasRoomComponent, Carbon $checkInDate, Carbon $checkOutDate): array
    {
        if ($hasRoomComponent) {
            return [
                $checkInDate->copy()->setTime(12, 0, 0),
                $checkOutDate->copy()->setTime(10, 0, 0),
            ];
        }

        return [
            $checkInDate->copy()->startOfDay(),
            $checkOutDate->copy()->endOfDay(),
        ];
    }

    /**
     * Ensure enough unassigned inventory exists for each requested line and unit prices match catalogue.
     */
    private function validateGuestRoomLines(array $roomLines, Carbon $checkIn, Carbon $checkOut, ?int $excludeBookingId): ?\Illuminate\Http\JsonResponse
    {
        foreach ($roomLines as $line) {
            $type = $line['room_type'];
            $key = $line['inventory_group_key'];
            $qty = (int) $line['quantity'];
            $submittedUnit = (float) $line['unit_price'];

            $candidates = Room::query()
                ->where('type', $type)
                ->where('status', '!=', Room::STATUS_MAINTENANCE)
                ->with(['bedSpecifications', 'bedModifiers'])
                ->get()
                ->filter(fn (Room $r) => RoomInventoryGroupKey::forRoom($r) === $key);

            $representative = $candidates->first();
            if ($representative === null) {
                return response()->json([
                    'message' => 'One or more room selections do not match available inventory.',
                    'error' => 'invalid_room_line',
                ], 422);
            }

            if (! BookingPricing::totalsMatch((float) $representative->price, $submittedUnit)) {
                return response()->json([
                    'message' => 'Room line price does not match current rates.',
                    'error' => 'price_mismatch',
                ], 422);
            }

            $ids = $candidates->pluck('id')->all();
            $availableCount = Room::whereIn('id', $ids)
                ->availableBetween($checkIn, $checkOut, $excludeBookingId)
                ->count();

            if ($availableCount < $qty) {
                return response()->json([
                    'message' => 'Booking conflict: not enough rooms available for one of your selected room types for these dates.',
                    'error' => 'date_range_conflict',
                    'conflicts' => [
                        'room_lines' => [
                            [
                                'room_type' => $type,
                                'inventory_group_key' => $key,
                                'requested' => $qty,
                                'available' => $availableCount,
                            ],
                        ],
                    ],
                ], 422);
            }
        }

        return null;
    }

    private function expectedTotalForBooking(Booking $booking, int $nights): float
    {
        $nights = max(1, $nights);
        if ($booking->rooms->isNotEmpty()) {
            return BookingPricing::expectedTotal(
                $nights,
                $booking->rooms,
                $booking->venues,
                $booking->venue_event_type,
            );
        }
        if ($booking->roomLines->isNotEmpty()) {
            return BookingPricing::expectedTotalFromRoomLines(
                $nights,
                $booking->roomLines,
                $booking->venues,
                $booking->venue_event_type,
            );
        }

        return BookingPricing::expectedTotal(
            $nights,
            collect(),
            $booking->venues,
            $booking->venue_event_type,
        );
    }

    /**
     * Display a specific booking.
     */
    public function show($id)
    {
        try {
            $booking = Booking::with(['guest', 'rooms', 'venues', 'roomLines'])->find($id);

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
            $booking = Booking::with(['guest', 'rooms', 'venues', 'roomLines'])->find($id);

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

        $booking->loadMissing(['rooms', 'venues', 'roomLines']);

        $checkInDate = Carbon::parse($request->check_in)->startOfDay();
        $checkOutDate = Carbon::parse($request->check_out)->startOfDay();

        $hasRoomComponent = $booking->rooms->isNotEmpty() || $booking->roomLines->isNotEmpty();
        [$checkIn, $checkOut] = $this->bookingWindowForStorage($hasRoomComponent, $checkInDate, $checkOutDate);

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
        } elseif ($booking->roomLines->isNotEmpty()) {
            $roomLineError = $this->validateGuestRoomLines(
                $booking->roomLines->map(fn ($l) => [
                    'room_type' => $l->room_type,
                    'inventory_group_key' => $l->inventory_group_key,
                    'quantity' => $l->quantity,
                    'unit_price' => (float) $l->unit_price_per_night,
                ])->all(),
                $checkIn,
                $checkOut,
                $booking->id,
            );
            if ($roomLineError !== null) {
                return $roomLineError;
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

        $nights = max(1, (int) $checkInDate->diffInDays($checkOutDate));

        $newTotal = $this->expectedTotalForBooking($booking, $nights);

        $booking->update([
            'check_in' => $checkIn,
            'check_out' => $checkOut,
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
