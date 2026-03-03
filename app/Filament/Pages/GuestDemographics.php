<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GuestDemographics extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-map';
    protected static \UnitEnum|string|null $navigationGroup = 'Reports';
    protected static ?string $title = 'Guest Demographics';
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.guest-demographics';

    protected function getViewData(): array
    {
        $unpaidStatuses = [Booking::STATUS_UNPAID];
        $successStatuses = [
            Booking::STATUS_PAID,
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_COMPLETED,
            Booking::STATUS_OCCUPIED
        ];

        return [
            'unpaid' => [
                'today' => $this->getTopMunicipality($unpaidStatuses, Carbon::today(), Carbon::today()),
                'next_7_days' => $this->getTopMunicipality($unpaidStatuses, Carbon::tomorrow(), Carbon::today()->addDays(7)),
                'this_month' => $this->getTopMunicipality($unpaidStatuses, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()),
                'next_month' => $this->getTopMunicipality($unpaidStatuses, Carbon::now()->addMonth()->startOfMonth(), Carbon::now()->addMonth()->endOfMonth()),
            ],
            'successful' => [
                'today' => $this->getTopMunicipality($successStatuses, Carbon::today(), Carbon::today()),
                'next_7_days' => $this->getTopMunicipality($successStatuses, Carbon::tomorrow(), Carbon::today()->addDays(7)),
                'this_month' => $this->getTopMunicipality($successStatuses, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()),
                'next_month' => $this->getTopMunicipality($successStatuses, Carbon::now()->addMonth()->startOfMonth(), Carbon::now()->addMonth()->endOfMonth()),
            ]
        ];
    }

    private function getTopMunicipality(array $statusGroup, Carbon $startDate, Carbon $endDate): ?array
    {
        $result = Booking::select('guests.municipality', DB::raw('count(*) as total'))
            ->join('guests', 'bookings.guest_id', '=', 'guests.id')
            ->whereIn('bookings.status', $statusGroup)
            ->whereBetween('bookings.check_in', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->whereNotNull('guests.municipality')
            ->where('guests.municipality', '!=', '')
            ->groupBy('guests.municipality')
            ->orderBy('total', 'desc')
            ->first();

        return $result ? [
            'name' => $result->municipality,
            'count' => $result->total
        ] : null;
    }

    public function viewBookingsAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('viewBookings')
            ->modalHeading(function (array $arguments) {
                $period = str_replace('_', ' ', $arguments['period'] ?? '');
                $type = $arguments['type'] ?? '';
                return 'Booking Details (' . ucwords($type) . ' - ' . ucwords($period) . ')';
            })
            ->modalContent(function (array $arguments) {
                $period = $arguments['period'] ?? 'today';
                $type = $arguments['type'] ?? 'unpaid';

                $statuses = $type === 'unpaid'
                    ? [Booking::STATUS_UNPAID]
                    : [Booking::STATUS_PAID, Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED, Booking::STATUS_OCCUPIED];

                $dates = $this->getDateRangeForPeriod($period);

                $bookings = Booking::with('guest')
                    ->whereIn('status', $statuses)
                    ->whereBetween('check_in', [$dates[0]->startOfDay(), $dates[1]->endOfDay()])
                    ->orderBy('check_in', 'asc')
                    ->get();

                return view('filament.pages.demographics-details-modal', [
                    'bookings' => $bookings,
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    private function getDateRangeForPeriod($period)
    {
        return match ($period) {
            'today' => [Carbon::today(), Carbon::today()],
            'next_7_days' => [Carbon::tomorrow(), Carbon::today()->addDays(7)],
            'this_month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            'next_month' => [Carbon::now()->addMonth()->startOfMonth(), Carbon::now()->addMonth()->endOfMonth()],
            default => [Carbon::today(), Carbon::today()]
        };
    }
}
