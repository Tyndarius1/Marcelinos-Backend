<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\BookingRoomLine;
use App\Models\Room;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;

final class BookingSpecialDiscount
{
    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    public const TARGET_TOTAL = 'total';

    public const TARGET_ROOM = 'room';

    public const TARGET_VENUE = 'venue';

    /**
     * @return array{allowed: bool, reason: string, message?: string}
     */
    public static function assessCanMutate(Booking $booking, ?User $actor): array
    {
        if ($booking->trashed()) {
            return ['allowed' => false, 'reason' => 'trashed', 'message' => 'Restore this booking to apply a discount.'];
        }

        if (! $actor || ! in_array((string) $actor->role, ['admin', 'staff'], true)) {
            return ['allowed' => false, 'reason' => 'forbidden', 'message' => 'You are not allowed to apply special discounts.'];
        }

        if (in_array((string) $booking->booking_status, [
            Booking::BOOKING_STATUS_CANCELLED,
            Booking::BOOKING_STATUS_COMPLETED,
            Booking::BOOKING_STATUS_FLAGGED,
        ], true)) {
            return ['allowed' => false, 'reason' => 'finalized', 'message' => 'Discounts are not allowed on cancelled, completed, or flagged bookings.'];
        }

        $hasPayments = (float) $booking->total_paid > 0.009 || $booking->payments()->exists();

        // Simple policy: staff can discount only before any payments exist; admins can override.
        if ($hasPayments && (string) $actor->role !== 'admin') {
            return ['allowed' => false, 'reason' => 'has_payments', 'message' => 'Only admins can modify discounts after payments were recorded.'];
        }

        return ['allowed' => true, 'reason' => 'ok'];
    }

    public static function hasDiscount(Booking $booking): bool
    {
        return filled($booking->special_discount_type)
            && (float) ($booking->special_discount_amount_applied ?? 0) > 0.009
            && (float) ($booking->special_discount_original_total_price ?? 0) > 0.009;
    }

    public static function grossTotal(Booking $booking): float
    {
        if (self::hasDiscount($booking)) {
            return (float) $booking->special_discount_original_total_price;
        }

        return (float) $booking->total_price;
    }

    /**
     * @return array{room: float, venue: float, total: float}
     */
    public static function chargeBreakdown(Booking $booking): array
    {
        $booking->loadMissing(['roomLines', 'rooms', 'venues']);
        $billingUnits = self::billingUnits($booking);
        $venueEventType = self::normalizeVenueEventType((string) ($booking->venue_event_type ?? 'wedding'));

        $roomSubtotal = $booking->roomLines->isNotEmpty()
            ? $booking->roomLines->reduce(function (float $sum, BookingRoomLine $line) use ($billingUnits): float {
                $unitPrice = (float) $line->unit_price_per_night;
                $qty = max(1, (int) $line->quantity);

                return $sum + ($unitPrice * $qty * $billingUnits);
            }, 0.0)
            : $booking->rooms->reduce(function (float $sum, Room $room) use ($billingUnits): float {
                return $sum + ((float) $room->price * $billingUnits);
            }, 0.0);

        $venueSubtotal = $booking->venues->reduce(function (float $sum, Venue $venue) use ($billingUnits, $venueEventType): float {
            $unit = match ($venueEventType) {
                'birthday' => (float) $venue->birthday_price,
                'meeting_staff' => (float) $venue->meeting_staff_price,
                default => (float) $venue->wedding_price,
            };

            return $sum + ($unit * $billingUnits);
        }, 0.0);

        return [
            'room' => round(max(0, $roomSubtotal), 2),
            'venue' => round(max(0, $venueSubtotal), 2),
            'total' => round(max(0, $roomSubtotal + $venueSubtotal), 2),
        ];
    }

    public static function discountAmount(Booking $booking): float
    {
        return (float) ($booking->special_discount_amount_applied ?? 0);
    }

    public static function netTotal(Booking $booking): float
    {
        return max(0, self::grossTotal($booking) - self::discountAmount($booking));
    }

    /**
     * @return array{gross: float, discount: float, net: float}
     */
    public static function preview(Booking $booking, string $type, float $value, ?string $target = null): array
    {
        $gross = max(0, self::grossTotal($booking));
        $resolvedTarget = self::resolveDiscountTarget($booking, $target);
        $discountableGross = self::discountableGrossForTarget($booking, $resolvedTarget, $gross);
        $discount = self::computeDiscountAmount($discountableGross, $type, $value);
        $net = max(0, $gross - $discount);

        return compact('gross', 'discount', 'net', 'discountableGross');
    }

    public static function apply(
        Booking $booking,
        string $type,
        float $value,
        ?string $target,
        ?string $reasonCode,
        ?string $note,
        ?User $actor,
    ): Booking {
        $assessment = self::assessCanMutate($booking, $actor);
        if (! $assessment['allowed']) {
            throw new \InvalidArgumentException($assessment['message'] ?? 'Discount not allowed.');
        }

        $type = trim($type);
        if (! in_array($type, [self::TYPE_PERCENT, self::TYPE_FIXED], true)) {
            throw new \InvalidArgumentException('Invalid discount type.');
        }

        $value = (float) $value;
        if (! is_finite($value) || $value <= 0) {
            throw new \InvalidArgumentException('Discount value must be greater than 0.');
        }

        $reasonCode = $reasonCode !== null ? trim($reasonCode) : null;
        $note = $note !== null ? trim($note) : null;

        return DB::transaction(function () use ($booking, $type, $value, $target, $reasonCode, $note, $actor) {
            /** @var Booking $fresh */
            $fresh = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            $gross = max(0, self::grossTotal($fresh));
            $resolvedTarget = self::resolveDiscountTarget($fresh, $target);
            $discountableGross = self::discountableGrossForTarget($fresh, $resolvedTarget, $gross);
            $discountAmount = self::computeDiscountAmount($discountableGross, $type, $value);
            $net = max(0, $gross - $discountAmount);

            $now = now();
            $actorId = $actor?->id;
            $isFirstApply = ! filled($fresh->special_discounted_at);

            $fresh->forceFill([
                'special_discount_type' => $type,
                'special_discount_target' => $resolvedTarget,
                'special_discount_value' => round($value, 2),
                'special_discount_reason_code' => $reasonCode,
                'special_discount_note' => $note,
                'special_discount_original_total_price' => round($gross, 2),
                'special_discount_amount_applied' => round($discountAmount, 2),
                'total_price' => round($net, 2),
                'special_discounted_by_user_id' => $isFirstApply ? $actorId : ($fresh->special_discounted_by_user_id ?? $actorId),
                'special_discounted_at' => $isFirstApply ? $now : ($fresh->special_discounted_at ?? $now),
                'special_discount_last_modified_by_user_id' => $actorId,
                'special_discount_last_modified_at' => $now,
            ])->save();

            ActivityLogger::log(
                category: 'booking',
                event: $isFirstApply ? 'booking.discount_applied' : 'booking.discount_updated',
                description: sprintf(
                    '%s %s special discount on booking %s (gross ₱%s → net ₱%s; discount ₱%s; %s %s).',
                    (string) ($actor?->name ?? 'System'),
                    $isFirstApply ? 'applied a' : 'updated the',
                    (string) $fresh->reference_number,
                    number_format($gross, 2),
                    number_format($net, 2),
                    number_format($discountAmount, 2),
                    $type === self::TYPE_PERCENT ? rtrim(rtrim(number_format($value, 2), '0'), '.') : '₱'.number_format($value, 2),
                    $type === self::TYPE_PERCENT ? '%' : 'fixed',
                ),
                subject: $fresh,
                meta: [
                    'reference_number' => (string) $fresh->reference_number,
                    'discount' => [
                        'target' => $resolvedTarget,
                        'target_gross_total' => round($discountableGross, 2),
                        'type' => $type,
                        'value' => round($value, 2),
                        'reason_code' => $reasonCode,
                        'note' => $note,
                        'gross_total' => round($gross, 2),
                        'discount_amount' => round($discountAmount, 2),
                        'net_total' => round($net, 2),
                    ],
                    'changed_by_user_id' => $actorId,
                    'changed_by_user_name' => (string) ($actor?->name ?? ''),
                ],
                userId: $actorId,
            );

            return $fresh->fresh();
        });
    }

    public static function remove(Booking $booking, ?User $actor): Booking
    {
        $assessment = self::assessCanMutate($booking, $actor);
        if (! $assessment['allowed']) {
            throw new \InvalidArgumentException($assessment['message'] ?? 'Discount not allowed.');
        }

        return DB::transaction(function () use ($booking, $actor) {
            /** @var Booking $fresh */
            $fresh = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            $gross = max(0, self::grossTotal($fresh));
            $oldDiscount = self::discountAmount($fresh);
            $net = $gross; // removing discount

            if (! self::hasDiscount($fresh)) {
                return $fresh;
            }

            $now = now();
            $actorId = $actor?->id;

            $fresh->forceFill([
                'total_price' => round($net, 2),
                'special_discount_type' => null,
                'special_discount_target' => null,
                'special_discount_value' => null,
                'special_discount_reason_code' => null,
                'special_discount_note' => null,
                'special_discount_original_total_price' => null,
                'special_discount_amount_applied' => null,
                'special_discount_last_modified_by_user_id' => $actorId,
                'special_discount_last_modified_at' => $now,
            ])->save();

            ActivityLogger::log(
                category: 'booking',
                event: 'booking.discount_removed',
                description: sprintf(
                    '%s removed special discount on booking %s (net ₱%s → ₱%s; restored ₱%s).',
                    (string) ($actor?->name ?? 'System'),
                    (string) $fresh->reference_number,
                    number_format(max(0, $gross - $oldDiscount), 2),
                    number_format($net, 2),
                    number_format($oldDiscount, 2),
                ),
                subject: $fresh,
                meta: [
                    'reference_number' => (string) $fresh->reference_number,
                    'gross_total' => round($gross, 2),
                    'discount_removed' => round($oldDiscount, 2),
                    'net_total' => round($net, 2),
                    'changed_by_user_id' => $actorId,
                    'changed_by_user_name' => (string) ($actor?->name ?? ''),
                ],
                userId: $actorId,
            );

            return $fresh->fresh();
        });
    }

    private static function computeDiscountAmount(float $gross, string $type, float $value): float
    {
        $gross = max(0, (float) $gross);
        $value = (float) $value;

        $amount = match ($type) {
            self::TYPE_PERCENT => $gross * ($value / 100),
            self::TYPE_FIXED => $value,
            default => 0.0,
        };

        $amount = round($amount, 2);

        return min(max(0, $amount), $gross);
    }

    public static function resolveDiscountTarget(Booking $booking, ?string $target = null): string
    {
        $normalized = strtolower(trim((string) $target));
        if (! in_array($normalized, [self::TARGET_TOTAL, self::TARGET_ROOM, self::TARGET_VENUE], true)) {
            $normalized = (string) ($booking->special_discount_target ?? self::TARGET_TOTAL);
        }
        if (! in_array($normalized, [self::TARGET_TOTAL, self::TARGET_ROOM, self::TARGET_VENUE], true)) {
            return self::TARGET_TOTAL;
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    public static function targetOptionsForBooking(Booking $booking): array
    {
        $breakdown = self::chargeBreakdown($booking);
        $hasRoom = $breakdown['room'] > 0.009;
        $hasVenue = $breakdown['venue'] > 0.009;

        $options = [
            self::TARGET_TOTAL => 'Grand total (room + venue)',
        ];

        if ($hasRoom && $hasVenue) {
            $options[self::TARGET_ROOM] = 'Room subtotal only';
            $options[self::TARGET_VENUE] = 'Venue subtotal only';
        }

        return $options;
    }

    public static function targetLabel(string $target): string
    {
        return match ($target) {
            self::TARGET_ROOM => 'Room subtotal only',
            self::TARGET_VENUE => 'Venue subtotal only',
            default => 'Grand total (room + venue)',
        };
    }

    private static function discountableGrossForTarget(Booking $booking, string $target, float $gross): float
    {
        if ($target === self::TARGET_TOTAL) {
            return max(0, $gross);
        }

        $breakdown = self::chargeBreakdown($booking);
        $discountable = $target === self::TARGET_ROOM
            ? (float) ($breakdown['room'] ?? 0)
            : (float) ($breakdown['venue'] ?? 0);

        if ($discountable <= 0.009) {
            throw new \InvalidArgumentException('Selected discount target has no billable amount.');
        }

        return max(0, min($discountable, $gross));
    }

    private static function billingUnits(Booking $booking): int
    {
        $units = (int) ($booking->no_of_days ?? 0);
        if ($units > 0) {
            return $units;
        }

        if ($booking->check_in && $booking->check_out) {
            return max(1, (int) $booking->check_in->diffInDays($booking->check_out));
        }

        return 1;
    }

    private static function normalizeVenueEventType(string $eventType): string
    {
        $normalized = strtolower(trim($eventType));

        return match ($normalized) {
            'seminar', 'meeting', 'meeting_staff' => 'meeting_staff',
            'birthday' => 'birthday',
            'wedding' => 'wedding',
            default => 'wedding',
        };
    }
}
