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

        // Fetch a full monthly breakdown for the printable tourism report
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $monthlyDemographics = $this->getHierarchicalData($successStatuses, $startOfMonth, $endOfMonth);
        $localDemographics = $monthlyDemographics->where('is_international', false);
        $foreignDemographics = $monthlyDemographics->where('is_international', true);

        // Fetch a full yearly breakdown
        $startOfYear = Carbon::now()->startOfYear();
        $endOfYear = Carbon::now()->endOfYear();
        $yearlyDemographics = $this->getHierarchicalData($successStatuses, $startOfYear, $endOfYear);
        $yearlyLocalDemographics = $yearlyDemographics->where('is_international', false);
        $yearlyForeignDemographics = $yearlyDemographics->where('is_international', true);

        return [
            'unpaid' => [
                'today' => $this->getTopLocation($unpaidStatuses, Carbon::today(), Carbon::today()),
                'next_7_days' => $this->getTopLocation($unpaidStatuses, Carbon::tomorrow(), Carbon::today()->addDays(7)),
                'this_month' => $this->getTopLocation($unpaidStatuses, $startOfMonth, $endOfMonth),
                'next_month' => $this->getTopLocation($unpaidStatuses, Carbon::now()->addMonth()->startOfMonth(), Carbon::now()->addMonth()->endOfMonth()),
            ],
            'successful' => [
                'today' => $this->getTopLocation($successStatuses, Carbon::today(), Carbon::today()),
                'next_7_days' => $this->getTopLocation($successStatuses, Carbon::tomorrow(), Carbon::today()->addDays(7)),
                'this_month' => $this->getTopLocation($successStatuses, $startOfMonth, $endOfMonth),
                'next_month' => $this->getTopLocation($successStatuses, Carbon::now()->addMonth()->startOfMonth(), Carbon::now()->addMonth()->endOfMonth()),
            ],

            // Raw data for printing complete hierarchy reports
            'reports' => [
                'unpaid' => [
                    'today' => $this->getHierarchicalData($unpaidStatuses, Carbon::today(), Carbon::today()),
                    'next_7_days' => $this->getHierarchicalData($unpaidStatuses, Carbon::tomorrow(), Carbon::today()->addDays(7)),
                    'this_month' => $this->getHierarchicalData($unpaidStatuses, $startOfMonth, $endOfMonth),
                    'next_month' => $this->getHierarchicalData($unpaidStatuses, Carbon::now()->addMonth()->startOfMonth(), Carbon::now()->addMonth()->endOfMonth()),
                    'all' => $this->getHierarchicalData($unpaidStatuses, Carbon::now()->subYears(10), Carbon::now()->addYears(10)), // all time approx
                ],
                'successful' => [
                    'today' => $this->getHierarchicalData($successStatuses, Carbon::today(), Carbon::today()),
                    'next_7_days' => $this->getHierarchicalData($successStatuses, Carbon::tomorrow(), Carbon::today()->addDays(7)),
                    'this_month' => $this->getHierarchicalData($successStatuses, $startOfMonth, $endOfMonth),
                    'next_month' => $this->getHierarchicalData($successStatuses, Carbon::now()->addMonth()->startOfMonth(), Carbon::now()->addMonth()->endOfMonth()),
                    'all' => $this->getHierarchicalData($successStatuses, Carbon::now()->subYears(10), Carbon::now()->addYears(10)),
                ]
            ],

            'localDemographics' => $localDemographics,
            'foreignDemographics' => $foreignDemographics,
            'yearlyLocalDemographics' => $yearlyLocalDemographics,
            'yearlyForeignDemographics' => $yearlyForeignDemographics,
            'reportMonth' => Carbon::now()->format('F Y'),
            'reportYear' => Carbon::now()->format('Y')
        ];
    }

    private function getHierarchicalData(array $statusGroup, Carbon $startDate, Carbon $endDate)
    {
        return Booking::select(
            'guests.is_international',
            'guests.country',
            'guests.region',
            'guests.province',
            'guests.municipality',
            DB::raw('count(*) as total')
        )
            ->join('guests', 'bookings.guest_id', '=', 'guests.id')
            ->whereIn('bookings.status', $statusGroup)
            ->whereBetween('bookings.check_in', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->groupBy('guests.is_international', 'guests.country', 'guests.region', 'guests.province', 'guests.municipality')
            ->orderByRaw("guests.is_international ASC, total DESC, guests.region ASC")
            ->get();
    }

    private function getTopLocation(array $statusGroup, Carbon $startDate, Carbon $endDate): ?array
    {
        $topRegion = Booking::select('guests.region', DB::raw('count(*) as total'))
            ->join('guests', 'bookings.guest_id', '=', 'guests.id')
            ->whereIn('bookings.status', $statusGroup)
            ->whereBetween('bookings.check_in', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->whereNotNull('guests.region')
            ->where('guests.region', '!=', '')
            ->groupBy('guests.region')
            ->orderBy('total', 'desc')
            ->first();

        if (!$topRegion) {
            return null;
        }

        $topProvince = Booking::select('guests.province', DB::raw('count(*) as total'))
            ->join('guests', 'bookings.guest_id', '=', 'guests.id')
            ->whereIn('bookings.status', $statusGroup)
            ->whereBetween('bookings.check_in', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->where('guests.region', $topRegion->region)
            ->whereNotNull('guests.province')
            ->where('guests.province', '!=', '')
            ->groupBy('guests.province')
            ->orderBy('total', 'desc')
            ->first();

        return [
            'name' => $topRegion->region,
            'sub' => $topProvince ? $topProvince->province : null,
            'count' => $topRegion->total
        ];
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
                    ->join('guests', 'bookings.guest_id', '=', 'guests.id')
                    ->select('bookings.*') // make sure we get booking model attributes correctly
                    ->whereIn('bookings.status', $statuses)
                    ->whereBetween('bookings.check_in', [$dates[0]->startOfDay(), $dates[1]->endOfDay()])
                    // Order by region, then province, then municipality
                    ->orderByRaw("guests.region DESC, guests.province DESC, guests.municipality DESC, bookings.check_in ASC")
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
