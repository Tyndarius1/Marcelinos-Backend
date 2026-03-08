<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;

class GuestDemographics extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-map';
    protected static \UnitEnum|string|null $navigationGroup = 'Reports';
    protected static ?string $title = 'Guest Demographics';
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.guest-demographics';

    public string $overviewPreset = 'this_month';
    public ?string $overviewStart = null; // Y-m-d
    public ?string $overviewEnd = null;   // Y-m-d

    public function mount(): void
    {
        $this->setOverviewPresetDefaults($this->overviewPreset);
        $this->form->fill([
            'overviewStart' => $this->overviewStart,
            'overviewEnd' => $this->overviewEnd,
        ]);
    }

    public function updatedOverviewPreset(string $value): void
    {
        $this->setOverviewPresetDefaults($value);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'sm' => 2,
            ])
            ->components([
                DatePicker::make('overviewStart')
                    ->label('From')
                    ->required()
                    ->native(false)
                    ->closeOnDateSelection(true)
                    ->live(),
                DatePicker::make('overviewEnd')
                    ->label('To')
                    ->required()
                    ->native(false)
                    ->closeOnDateSelection(true)
                    ->live(),
            ]);
    }

    protected function getViewData(): array
    {
        $unpaidStatuses = [Booking::STATUS_UNPAID];
        $successStatuses = [
            Booking::STATUS_PAID,
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_COMPLETED,
            Booking::STATUS_OCCUPIED
        ];

        // Overview report (calendar/presets)
        [$overviewStart, $overviewEnd] = $this->resolveOverviewRange();
        $overviewDemographics = $this->getHierarchicalData($successStatuses, $overviewStart, $overviewEnd);
        $overviewLocalDemographics = $overviewDemographics->where('is_international', false);
        $overviewForeignDemographics = $overviewDemographics->where('is_international', true);

        $overviewLabel = $this->overviewLabel($overviewStart, $overviewEnd);

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $startOfYear = Carbon::now()->startOfYear();
        $endOfYear = Carbon::now()->endOfYear();

        // Existing monthly breakdown (current month)
        $monthlyDemographics = $this->getHierarchicalData($successStatuses, $startOfMonth, $endOfMonth);
        $localDemographics = $monthlyDemographics->where('is_international', false);
        $foreignDemographics = $monthlyDemographics->where('is_international', true);

        // Existing yearly breakdown (current year)
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
            'reportYear' => Carbon::now()->format('Y'),

            // New: calendar-driven overview (use this for "Print report" buttons)
            'overviewLocalDemographics' => $overviewLocalDemographics,
            'overviewForeignDemographics' => $overviewForeignDemographics,
            'overviewLabel' => $overviewLabel,

            // Recent activity stream shown below reports.
            'activityLogs' => ActivityLog::query()
                ->with('user:id,name')
                ->whereIn('category', ['auth', 'booking', 'review', 'resource', 'report'])
                ->latest('created_at')
                ->limit(30)
                ->get(),
        ];
    }

    public function logReportDownload(string $type, ?string $period = null): void
    {
        $normalizedPeriod = $period === 'null' ? null : $period;

        ActivityLogger::log(
            category: 'report',
            event: 'report.downloaded',
            description: sprintf(
                'downloaded %s report%s.',
                str_replace('_', ' ', $type),
                $normalizedPeriod ? ' (' . str_replace('_', ' ', $normalizedPeriod) . ')' : '',
            ),
            meta: [
                'type' => $type,
                'period' => $normalizedPeriod,
            ],
        );
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
                    ->select('bookings.*')
                    ->whereIn('bookings.status', $statuses)
                    ->whereBetween('bookings.check_in', [$dates[0]->startOfDay(), $dates[1]->endOfDay()])
                    ->orderByRaw("guests.region DESC, guests.province DESC, guests.municipality DESC, bookings.check_in ASC")
                    ->get();

                return new \Illuminate\Support\HtmlString(
                    view('filament.pages.demographics-details-modal', [
                        'bookings' => $bookings,
                    ])->render()
                );
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

    private function setOverviewPresetDefaults(string $preset): void
    {
        $now = Carbon::now();

        if ($preset === 'custom') {
            if (! $this->overviewStart) {
                $this->overviewStart = $now->copy()->startOfMonth()->toDateString();
            }
            if (! $this->overviewEnd) {
                $this->overviewEnd = $now->copy()->endOfMonth()->toDateString();
            }
            return;
        }

        [$start, $end] = match ($preset) {
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };

        $this->overviewStart = $start->toDateString();
        $this->overviewEnd = $end->toDateString();
    }

    private function resolveOverviewRange(): array
    {
        $start = null;
        $end = null;

        if ($this->overviewStart) {
            $start = Carbon::parse($this->overviewStart);
        }
        if ($this->overviewEnd) {
            $end = Carbon::parse($this->overviewEnd);
        }

        $start ??= Carbon::now()->startOfMonth();
        $end ??= Carbon::now()->endOfMonth();

        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    private function overviewLabel(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($start->copy()->startOfMonth()) && $end->isSameDay($start->copy()->endOfMonth())) {
            return 'Month: ' . $start->format('F Y');
        }

        if ($start->isSameDay($start->copy()->startOfYear()) && $end->isSameDay($start->copy()->endOfYear())) {
            return 'Year: ' . $start->format('Y');
        }

        return 'Dates: ' . $start->toDateString() . ' → ' . $end->toDateString();
    }
}
