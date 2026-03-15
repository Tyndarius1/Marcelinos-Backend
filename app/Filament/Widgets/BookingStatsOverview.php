<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Booking;
use Carbon\Carbon;

class BookingStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $todayCount = Booking::whereDate('created_at', $today)->count();
        $yesterdayCount = Booking::whereDate('created_at', $yesterday)->count();
        $todayDelta = $todayCount - $yesterdayCount;

        $currentRevenue = Booking::where('created_at', '>=', now()->subDays(30))
            ->whereIn('status', [Booking::STATUS_PAID, Booking::STATUS_COMPLETED])
            ->sum('total_price');
        $previousRevenue = Booking::whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])
            ->whereIn('status', [Booking::STATUS_PAID, Booking::STATUS_COMPLETED])
            ->sum('total_price');
        $revenueDelta = $currentRevenue - $previousRevenue;

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

            Stat::make('Active Reservations', Booking::whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_OCCUPIED])->count())
                ->description('Confirmed or occupied')
                ->icon('heroicon-o-clock')
                ->color('warning'),
        ];
    }
}
