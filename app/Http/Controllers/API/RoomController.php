<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\RoomResource;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlockedDate;
use App\Support\RoomInventoryGroupAvailability;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RoomController extends Controller
{
    /**
     * List rooms.
     * Same availability contract as VenueController: when check_in/check_out are provided,
     * All rooms are returned but add availability status (available or not available).
     * - is_all=true: return all rooms (e.g. homepage).
     * - Otherwise: require check_in & check_out; return rooms with availability status based on the date range.
     * - is_block_date: true if this room has a staff block overlapping [check_in, check_out]; null when is_all.
     * - When available is false: unavailability_code, unavailability_title, unavailability_detail explain why
     *   (maintenance, blocked dates with staff reason, or existing reservation).
     */
    public function index(Request $request)
    {
        try {
            $isAll = filter_var($request->query('is_all', false), FILTER_VALIDATE_BOOLEAN);

            if (! $isAll) {
                $request->validate([
                    'check_in' => 'required|string',
                    'check_out' => 'required|string',
                ], [
                    'check_in.required' => 'check_in is required when is_all is not true.',
                    'check_out.required' => 'check_out is required when is_all is not true.',
                ]);

                try {
                    $checkIn = Carbon::parse($request->query('check_in'))->startOfDay();
                    $checkOut = Carbon::parse($request->query('check_out'))->endOfDay();
                } catch (Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid date format for check_in or check_out.',
                    ], 422);
                }

                if ($checkOut->lt($checkIn)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'check_out cannot be before check_in.',
                    ], 422);
                }
            }

            $query = Room::with(['amenities', 'media', 'bedSpecifications'])
                ->orderByRaw("FIELD(type, 'standard', 'family', 'deluxe')");
            $checkIn = null;
            $checkOut = null;
            if (! $isAll) {
                $checkIn = Carbon::parse($request->query('check_in'))->startOfDay();
                $checkOut = Carbon::parse($request->query('check_out'))->endOfDay();
            }

            $rooms = $query->get();

            $availableRoomIds = [];
            $blockedDateRoomIds = [];
            if (! $isAll && $rooms->isNotEmpty()) {
                $roomIds = $rooms->pluck('id')->all();
                $availableRoomIds = Room::whereIn('id', $roomIds)
                    ->availableBetween($checkIn, $checkOut)
                    ->pluck('id')
                    ->all();
                $blockedDateRoomIds = RoomBlockedDate::query()
                    ->whereIn('room_id', $roomIds)
                    ->overlappingBookingRange($checkIn, $checkOut)
                    ->pluck('room_id')
                    ->unique()
                    ->all();
            }

            $data = RoomResource::collection($rooms)->resolve();
            $data = array_map(function ($item) use ($isAll, $availableRoomIds, $blockedDateRoomIds, $rooms, $checkIn, $checkOut) {
                $item['available'] = $isAll ? null : in_array($item['id'], $availableRoomIds, true);
                $item['is_block_date'] = $isAll ? null : in_array($item['id'], $blockedDateRoomIds, true);
                $item['unavailability_code'] = null;
                $item['unavailability_title'] = null;
                $item['unavailability_detail'] = null;
                if (! $isAll && $item['available'] === false) {
                    $room = $rooms->firstWhere('id', $item['id']);
                    $u = $this->resolveRoomUnavailability($room, $checkIn, $checkOut);
                    if ($u !== null) {
                        $item['unavailability_code'] = $u['code'];
                        $item['unavailability_title'] = $u['title'];
                        $item['unavailability_detail'] = $u['detail'];
                    }
                }

                return $item;
            }, $data);

            $inventoryGroupAvailability = $isAll
                ? null
                : RoomInventoryGroupAvailability::rowsForRange($checkIn, $checkOut, null);

            return response()->json([
                'success' => true,
                'data' => $data,
                'inventory_group_availability' => $inventoryGroupAvailability,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rooms',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Human-readable reason when a room is not bookable for the requested range.
     * Priority: maintenance → staff-blocked dates (with reasons) → overlapping guest booking.
     */
    private function resolveRoomUnavailability(?Room $room, Carbon $checkIn, Carbon $checkOut): ?array
    {
        if ($room === null) {
            return null;
        }

        if ($room->status === Room::STATUS_MAINTENANCE) {
            return [
                'code' => 'maintenance',
                'title' => 'Under maintenance',
                'detail' => 'This room is temporarily unavailable for bookings.',
            ];
        }

        $blockedOverlap = RoomBlockedDate::query()
            ->where('room_id', $room->id)
            ->overlappingBookingRange($checkIn, $checkOut)
            ->get(['blocked_on', 'reason']);

        if ($blockedOverlap->isNotEmpty()) {
            $reasonTexts = $blockedOverlap
                ->pluck('reason')
                ->filter(fn ($r) => filled($r))
                ->unique()
                ->values()
                ->all();

            $detail = count($reasonTexts) > 0
                ? implode(' · ', $reasonTexts)
                : 'One or more days in your stay are blocked by staff (e.g. maintenance or cleaning).';

            return [
                'code' => 'blocked',
                'title' => 'Blocked for your dates',
                'detail' => $detail,
            ];
        }

        $hasBooking = $room->bookings()
            ->where('bookings.status', '!=', Booking::STATUS_CANCELLED)
            ->where('bookings.check_in', '<', $checkOut)
            ->where('bookings.check_out', '>', $checkIn)
            ->exists();

        if ($hasBooking) {
            return [
                'code' => 'booked',
                'title' => 'Already reserved',
                'detail' => 'Another guest has booked this room for all or part of your selected dates.',
            ];
        }

        return [
            'code' => 'unknown',
            'title' => 'Not available for selected dates',
            'detail' => 'Choose different dates or another room.',
        ];
    }

    /**
     * Show room details by id.
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $room = Room::with(['amenities', 'media', 'bedSpecifications'])->findOrFail($id);
            $data = (new RoomResource($room))->resolve();

            $checkInQuery = $request->query('check_in');
            $checkOutQuery = $request->query('check_out');
            if ($checkInQuery !== null && $checkInQuery !== '' && $checkOutQuery !== null && $checkOutQuery !== '') {
                try {
                    $ci = Carbon::parse($checkInQuery)->startOfDay();
                    $co = Carbon::parse($checkOutQuery)->endOfDay();
                    if (! $co->lt($ci)) {
                        $data['is_block_date'] = RoomBlockedDate::query()
                            ->where('room_id', $room->id)
                            ->overlappingBookingRange($ci, $co)
                            ->exists();
                    }
                } catch (Exception $e) {
                    // leave is_block_date unset if dates are invalid
                }
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Room not found',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch the room',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
