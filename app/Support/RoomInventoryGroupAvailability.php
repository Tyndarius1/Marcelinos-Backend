<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Support\Carbon;

/**
 * Computes how many units remain bookable per room type + bed-spec group for a date range,
 * counting both assigned rooms and guest room_lines on overlapping bookings.
 */
final class RoomInventoryGroupAvailability
{
    /**
     * Physical capacity per group key: "room_type\0inventory_group_key" => count.
     *
     * @return array<string, int>
     */
    public static function capacityByGroupKey(): array
    {
        $out = [];
        $rooms = Room::query()
            ->where('status', '!=', Room::STATUS_MAINTENANCE)
            ->with(['bedSpecifications'])
            ->get();

        foreach ($rooms as $room) {
            $k = self::compositeKey($room->type, RoomInventoryGroupKey::forRoom($room));
            $out[$k] = ($out[$k] ?? 0) + 1;
        }

        return $out;
    }

    /**
     * Committed units per group from overlapping bookings (room_lines + assigned rooms, de-duplicated per booking).
     *
     * @return array<string, int>
     */
    public static function committedByGroupKey(
        Carbon $rangeStart,
        Carbon $rangeEnd,
        ?int $excludeBookingId = null,
    ): array {
        $out = [];

        $query = Booking::query()
            ->where('status', '!=', Booking::STATUS_CANCELLED)
            ->where('check_in', '<', $rangeEnd)
            ->where('check_out', '>', $rangeStart)
            ->where(function ($q) {
                $q->whereHas('roomLines')
                    ->orWhereHas('rooms');
            })
            ->with([
                'roomLines',
                'rooms' => fn ($q) => $q->with(['bedSpecifications']),
            ]);

        if ($excludeBookingId !== null) {
            $query->where('bookings.id', '!=', $excludeBookingId);
        }

        foreach ($query->get() as $booking) {
            $lineTotals = [];
            foreach ($booking->roomLines as $line) {
                $k = self::compositeKey($line->room_type, $line->inventory_group_key);
                $lineTotals[$k] = ($lineTotals[$k] ?? 0) + (int) $line->quantity;
            }

            $assignedTotals = [];
            foreach ($booking->rooms as $room) {
                if ($room->status === Room::STATUS_MAINTENANCE) {
                    continue;
                }
                $rk = RoomInventoryGroupKey::forRoom($room);
                $k = self::compositeKey($room->type, $rk);
                $assignedTotals[$k] = ($assignedTotals[$k] ?? 0) + 1;
            }

            $keys = array_unique(array_merge(array_keys($lineTotals), array_keys($assignedTotals)));
            foreach ($keys as $k) {
                $committed = max($lineTotals[$k] ?? 0, $assignedTotals[$k] ?? 0);
                $out[$k] = ($out[$k] ?? 0) + $committed;
            }
        }

        return $out;
    }

    /**
     * Remaining units per composite key (single pass over bookings).
     *
     * @return array<string, int> composite key => remaining
     */
    public static function remainingForRangeMap(
        Carbon $rangeStart,
        Carbon $rangeEnd,
        ?int $excludeBookingId = null,
    ): array {
        $capacity = self::capacityByGroupKey();
        $committed = self::committedByGroupKey($rangeStart, $rangeEnd, $excludeBookingId);
        $keys = array_unique(array_merge(array_keys($capacity), array_keys($committed)));
        $map = [];
        foreach ($keys as $composite) {
            $cap = $capacity[$composite] ?? 0;
            $com = $committed[$composite] ?? 0;
            $map[$composite] = max(0, $cap - $com);
        }

        return $map;
    }

    /**
     * Per-group availability for the guest API (capacity, committed, remaining).
     *
     * @return list<array{room_type: string, inventory_group_key: string, capacity: int, committed: int, remaining: int}>
     */
    public static function rowsForRange(
        Carbon $rangeStart,
        Carbon $rangeEnd,
        ?int $excludeBookingId = null,
    ): array {
        $capacity = self::capacityByGroupKey();
        $committed = self::committedByGroupKey($rangeStart, $rangeEnd, $excludeBookingId);

        $keys = array_unique(array_merge(array_keys($capacity), array_keys($committed)));
        sort($keys);

        $rows = [];
        foreach ($keys as $composite) {
            [$type, $invKey] = explode("\0", $composite, 2);
            $cap = $capacity[$composite] ?? 0;
            $com = $committed[$composite] ?? 0;
            $rows[] = [
                'room_type' => $type,
                'inventory_group_key' => $invKey,
                'capacity' => $cap,
                'committed' => $com,
                'remaining' => max(0, $cap - $com),
            ];
        }

        return $rows;
    }

    public static function remainingForLine(
        string $roomType,
        string $inventoryGroupKey,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        ?int $excludeBookingId = null,
    ): int {
        $map = self::remainingForRangeMap($rangeStart, $rangeEnd, $excludeBookingId);

        return $map[self::compositeKey($roomType, $inventoryGroupKey)] ?? 0;
    }

    public static function compositeKey(string $roomType, string $inventoryGroupKey): string
    {
        return $roomType."\0".$inventoryGroupKey;
    }
}
