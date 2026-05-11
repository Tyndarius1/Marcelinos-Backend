<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomChecklist;
use App\Models\RoomChecklistItem;
use App\Models\RoomChecklistTemplate;

final class SettledDamageLossRevenue
{
    /**
     * @var array<string, float>|null
     */
    private static ?array $templateChargeMap = null;

    public static function forBooking(Booking $booking): float
    {
        if ((string) $booking->damage_settlement_status !== Booking::DAMAGE_SETTLEMENT_STATUS_SETTLED) {
            return 0.0;
        }

        $booking->loadMissing(['roomChecklists.items', 'roomChecklists.room']);

        return (float) $booking->roomChecklists
            ->flatMap(fn (RoomChecklist $checklist) => $checklist->items)
            ->filter(fn (RoomChecklistItem $item): bool => in_array((string) $item->status, [
                RoomChecklistItem::STATUS_BROKEN,
                RoomChecklistItem::STATUS_MISSING,
            ], true))
            ->sum(function (RoomChecklistItem $item): float {
                $quantity = max(1, (int) ($item->quantity ?? 1));

                return self::resolveItemCharge($item) * $quantity;
            });
    }

    /**
     * @param  iterable<Booking>  $bookings
     */
    public static function forBookings(iterable $bookings): float
    {
        $total = 0.0;

        foreach ($bookings as $booking) {
            if (! $booking instanceof Booking) {
                continue;
            }

            $total += self::forBooking($booking);
        }

        return $total;
    }

    private static function parseMoneyToFloat(string $value): float
    {
        $normalized = preg_replace('/[^0-9.\-]/', '', $value);
        if (! is_string($normalized) || $normalized === '' || $normalized === '-' || $normalized === '.') {
            return 0.0;
        }

        return max(0, (float) $normalized);
    }

    private static function resolveItemCharge(RoomChecklistItem $item): float
    {
        $directCharge = self::parseMoneyToFloat((string) ($item->charge ?? '0'));
        if ($directCharge > 0) {
            return $directCharge;
        }

        $label = strtolower(trim((string) $item->label));
        if ($label === '') {
            return 0.0;
        }

        $roomType = strtolower(trim((string) ($item->roomChecklist?->room?->type ?? '')));
        $roomId = (int) ($item->roomChecklist?->room?->id ?? 0);
        $map = self::templateChargeMap();
        if ($roomId > 0 && array_key_exists("{$label}::#{$roomId}", $map)) {
            return $map["{$label}::#{$roomId}"];
        }

        if ($roomType !== '' && array_key_exists("{$label}::{$roomType}", $map)) {
            return $map["{$label}::{$roomType}"];
        }

        return $map["{$label}::*"] ?? 0.0;
    }

    /**
     * @return array<string, float>
     */
    private static function templateChargeMap(): array
    {
        if (self::$templateChargeMap !== null) {
            return self::$templateChargeMap;
        }

        $templates = RoomChecklistTemplate::query()
            ->where('is_active', true)
            ->get(['label', 'default_charge', 'applicable_room_types']);

        $roomTypesById = Room::query()
            ->select(['id', 'type'])
            ->get()
            ->mapWithKeys(fn (Room $room): array => [(int) $room->id => strtolower(trim((string) $room->type))])
            ->all();

        self::$templateChargeMap = $templates
            ->reduce(function (array $carry, RoomChecklistTemplate $template) use ($roomTypesById): array {
                $label = strtolower(trim((string) $template->label));
                if ($label === '') {
                    return $carry;
                }

                $amount = self::parseMoneyToFloat((string) ($template->default_charge ?? '0'));
                $allowed = is_array($template->applicable_room_types) ? $template->applicable_room_types : [];

                if ($allowed === []) {
                    $carry["{$label}::*"] = $amount;

                    return $carry;
                }

                foreach ($allowed as $value) {
                    $roomId = (int) $value;
                    if ($roomId > 0) {
                        $carry["{$label}::#{$roomId}"] = $amount;
                        $roomType = $roomTypesById[$roomId] ?? '';
                        if ($roomType !== '') {
                            $carry["{$label}::{$roomType}"] = $amount;
                        }
                        continue;
                    }

                    $normalized = strtolower(trim((string) $value));
                    if ($normalized === '') {
                        continue;
                    }

                    $carry["{$label}::{$normalized}"] = $amount;
                }

                return $carry;
            }, []);

        return self::$templateChargeMap;
    }
}