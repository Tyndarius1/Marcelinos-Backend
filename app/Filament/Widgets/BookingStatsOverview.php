<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BookingStatsOverview extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $todayCount = Booking::whereDate('created_at', $today)->count();
        $yesterdayCount = Booking::whereDate('created_at', $yesterday)->count();
        $todayDelta = $todayCount - $yesterdayCount;

        $currentRevenue = $this->bookingRevenueSince(now()->subDays(30));
        $previousRevenue = $this->bookingRevenueSince(now()->subDays(60), now()->subDays(30));
        $currentTotal = $currentRevenue + $this->settledPropertyRevenueSince(now()->subDays(30));
        $previousTotal = $previousRevenue + $this->settledPropertyRevenueSince(now()->subDays(60), now()->subDays(30));
        $revenueDelta = $currentRevenue - $previousRevenue;

        $totalPayable = $this->getTotalPayable();

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

            Stat::make('Total', '₱ ' . number_format((float) $currentTotal, 2))
                ->description('Includes settled property charges')
                ->descriptionIcon($currentTotal >= $previousTotal ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->icon('heroicon-o-calculator')
                ->color($currentTotal >= $previousTotal ? 'success' : 'warning'),

            // `Total Payable` removed per request
        ];
    }

    public function getColumns(): array | int | null
    {
        return [
            'default' => 1,
            'lg' => 3,
        ];
    }

    private function bookingRevenueSince(Carbon $from, ?Carbon $to = null): float
    {
        $query = Booking::query()->where('created_at', '>=', $from);

        if ($to !== null) {
            $query->where('created_at', '<', $to);
        }

        return (float) $query
            ->where(function ($q): void {
                $q->where('payment_status', Booking::PAYMENT_STATUS_PAID)
                    ->orWhere('booking_status', Booking::BOOKING_STATUS_COMPLETED);
            })
            ->sum('total_price');
    }

    private function settledPropertyRevenueSince(Carbon $from, ?Carbon $to = null): float
    {
        $query = Booking::query()->where('created_at', '>=', $from);

        if ($to !== null) {
            $query->where('created_at', '<', $to);
        }

        $bookings = $query
            ->where('damage_settlement_status', Booking::DAMAGE_SETTLEMENT_STATUS_SETTLED)
            ->with(['bookingInspection.items.inventoryItem'])
            ->get();

        return (float) $bookings->sum(function (Booking $booking): float {
            $inspection = $booking->bookingInspection;
            if (! $inspection) {
                return 0.0;
            }

            return (float) $inspection->items
                ->filter(fn ($item): bool => in_array((string) $item->status, ['damaged', 'missing'], true))
                ->sum(function ($item): float {
                    $inventoryItem = $item->inventoryItem;

                    return (float) ($inventoryItem?->price ?? 0) * max(1, (int) ($inventoryItem?->quantity ?? 1));
                });
        });
    }

    private function getTotalPayable(): float
    {
        // Outstanding balance for pending/open bookings
        $openBookingsBalance = (float) Booking::query()
            ->where('booking_status', '!=', Booking::BOOKING_STATUS_COMPLETED)
            ->sum('total_price');

        // Pending damage claims (not yet settled)
        $pendingDamageClaims = (float) Booking::query()
            ->where('damage_settlement_status', Booking::DAMAGE_SETTLEMENT_STATUS_PENDING)
            ->with(['bookingInspection.items.inventoryItem'])
            ->get()
            ->sum(function (Booking $booking): float {
                $inspection = $booking->bookingInspection;
                if (! $inspection) {
                    return 0.0;
                }

                return (float) $inspection->items
                    ->filter(fn ($item): bool => in_array((string) $item->status, ['damaged', 'missing'], true))
                    ->sum(function ($item): float {
                        $inventoryItem = $item->inventoryItem;

                        return (float) ($inventoryItem?->price ?? 0) * max(1, (int) ($inventoryItem?->quantity ?? 1));
                    });
            });

        return $openBookingsBalance + $pendingDamageClaims;
    }
}
