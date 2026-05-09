<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Booking;
use App\Models\RoomChecklistItem;
use App\Models\RoomChecklistTemplate;
use App\Support\BookingRevenueCalculator;
use Carbon\Carbon;

class BookingStatsOverview extends StatsOverviewWidget
{
    /**
     * @var array<string, float>|null
     */
    private ?array $templateChargeMap = null;

    protected function getStats(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $todayCount = Booking::whereDate('created_at', $today)->count();
        $yesterdayCount = Booking::whereDate('created_at', $yesterday)->count();
        $todayDelta = $todayCount - $yesterdayCount;

        $currentRevenue = Booking::where('created_at', '>=', now()->subDays(30))
            ->where(function ($q): void {
                $q->where('payment_status', Booking::PAYMENT_STATUS_PAID)
                    ->orWhere('payment_status', Booking::PAYMENT_STATUS_PARTIAL)
                    ->orWhere('booking_status', Booking::BOOKING_STATUS_COMPLETED);
            })
            ->with('payments')
            ->get()
            ->reduce(function ($carry, Booking $booking) {
                return $carry + BookingRevenueCalculator::forBooking($booking);
            }, 0.0);
        
        $previousRevenue = Booking::whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])
            ->where(function ($q): void {
                $q->where('payment_status', Booking::PAYMENT_STATUS_PAID)
                    ->orWhere('payment_status', Booking::PAYMENT_STATUS_PARTIAL)
                    ->orWhere('booking_status', Booking::BOOKING_STATUS_COMPLETED);
            })
            ->with('payments')
            ->get()
            ->reduce(function ($carry, Booking $booking) {
                return $carry + BookingRevenueCalculator::forBooking($booking);
            }, 0.0);
        $revenueDelta = $currentRevenue - $previousRevenue;
        $damageAndLossCharges = RoomChecklistItem::query()
            ->whereIn('status', [
                RoomChecklistItem::STATUS_BROKEN,
                RoomChecklistItem::STATUS_MISSING,
            ])
            ->whereHas('roomChecklist.booking', function ($query): void {
                $query->where('damage_settlement_status', Booking::DAMAGE_SETTLEMENT_STATUS_SETTLED);
            })
            ->with('roomChecklist.room:id,type')
            ->get(['label', 'charge', 'quantity', 'room_checklist_id'])
            ->sum(function (RoomChecklistItem $item): float {
                $quantity = max(1, (int) ($item->quantity ?? 1));
                $charge = $this->resolveItemCharge($item);

                return $charge * $quantity;
            });

        return [
            Stat::make('New Bookings', $todayCount)
                ->description($todayDelta === 0 ? 'No change vs yesterday' : ($todayDelta > 0 ? "+{$todayDelta} vs yesterday" : "{$todayDelta} vs yesterday"))
                ->descriptionIcon($todayDelta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->icon('heroicon-o-plus-circle')
                ->color($todayDelta >= 0 ? 'success' : 'danger'),

            Stat::make('Total Revenue', '₱ ' . number_format((float) $currentRevenue, 2))
                ->description($revenueDelta === 0 ? 'No change vs last 30 days' : ($revenueDelta > 0 ? '+₱ ' . number_format($revenueDelta, 2) . ' vs last 30 days' : '-₱ ' . number_format(abs($revenueDelta), 2) . ' vs last 30 days'))
                ->descriptionIcon($revenueDelta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->icon('heroicon-o-banknotes')
                ->color($revenueDelta >= 0 ? 'success' : 'danger'),

            Stat::make('Damage & Loss Charges', '₱ ' . number_format((float) $damageAndLossCharges, 2))
                ->description('Total charges marked as settled')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning'),
        ];
    }

    private function parseMoneyToFloat(string $value): float
    {
        $normalized = preg_replace('/[^0-9.\-]/', '', $value);
        if (! is_string($normalized) || $normalized === '' || $normalized === '-' || $normalized === '.') {
            return 0.0;
        }

        return max(0, (float) $normalized);
    }

    private function resolveItemCharge(RoomChecklistItem $item): float
    {
        $directCharge = $this->parseMoneyToFloat((string) ($item->charge ?? '0'));
        if ($directCharge > 0) {
            return $directCharge;
        }

        $label = strtolower(trim((string) $item->label));
        if ($label === '') {
            return 0.0;
        }

        $roomType = strtolower(trim((string) ($item->roomChecklist?->room?->type ?? '')));
        $map = $this->templateChargeMap();
        if ($roomType !== '' && array_key_exists("{$label}::{$roomType}", $map)) {
            return $map["{$label}::{$roomType}"];
        }

        return $map["{$label}::*"] ?? 0.0;
    }

    /**
     * @return array<string, float>
     */
    private function templateChargeMap(): array
    {
        if ($this->templateChargeMap !== null) {
            return $this->templateChargeMap;
        }

        $templates = RoomChecklistTemplate::query()
            ->where('is_active', true)
            ->get(['label', 'default_charge', 'applicable_room_types']);

        $this->templateChargeMap = $templates
            ->reduce(function (array $carry, RoomChecklistTemplate $template): array {
                $label = strtolower(trim((string) $template->label));
                if ($label === '') {
                    return $carry;
                }

                $amount = $this->parseMoneyToFloat((string) ($template->default_charge ?? '0'));
                $types = is_array($template->applicable_room_types) ? $template->applicable_room_types : [];

                if ($types === []) {
                    $carry["{$label}::*"] = $amount;

                    return $carry;
                }

                foreach ($types as $type) {
                    $normalized = strtolower(trim((string) $type));
                    if ($normalized === '') {
                        continue;
                    }
                    $carry["{$label}::{$normalized}"] = $amount;
                }

                return $carry;
            }, []);

        return $this->templateChargeMap;
    }
}
