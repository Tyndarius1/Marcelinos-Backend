<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\RoomResource;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Exception;

class RoomController extends Controller
{
    /**
     * List rooms.
     * Same availability contract as VenueController: when check_in/check_out are provided,
     * All rooms are returned but add availability status (available or not available).
     * - is_all=true: return all rooms (e.g. homepage).
     * - Otherwise: require check_in & check_out; return rooms with availability status based on the date range.
     */
    public function index(Request $request)
    {
        try {
            $isAll = filter_var($request->query('is_all', false), FILTER_VALIDATE_BOOLEAN);

            if (!$isAll) {
                $request->validate([
                    'check_in'  => 'required|string',
                    'check_out' => 'required|string',
                ], [
                    'check_in.required'  => 'check_in is required when is_all is not true.',
                    'check_out.required' => 'check_out is required when is_all is not true.',
                ]);

                try {
                    $checkIn  = Carbon::parse($request->query('check_in'))->startOfDay();
                    $checkOut = Carbon::parse($request->query('check_out'))->endOfDay();
                } catch (\Exception $e) {
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

            $query = Room::with(['amenities', 'media', 'bedSpecifications', 'bedModifiers'])
                ->orderByRaw("FIELD(type, 'standard', 'family', 'deluxe')");
            $checkIn = null;
            $checkOut = null;
            if (!$isAll) {
                $checkIn  = Carbon::parse($request->query('check_in'))->startOfDay();
                $checkOut = Carbon::parse($request->query('check_out'))->endOfDay();
            }

            $rooms = $query->get();

            $availableRoomIds = [];
            if (!$isAll && $rooms->isNotEmpty()) {
                $availableRoomIds = Room::whereIn('id', $rooms->pluck('id'))
                    ->availableBetween($checkIn, $checkOut)
                    ->pluck('id')
                    ->all();
            }

            $data = RoomResource::collection($rooms)->resolve();
            $data = array_map(function ($item) use ($isAll, $availableRoomIds) {
                $item['available'] = $isAll ? null : in_array($item['id'], $availableRoomIds);
                return $item;
            }, $data);

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
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

    public function show($id): JsonResponse
    {
        try {
            $room = Room::with(['amenities', 'media', 'bedSpecifications', 'bedModifiers'])->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => (new RoomResource($room))->resolve(),
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
