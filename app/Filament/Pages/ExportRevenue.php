<?php

namespace App\Filament\Pages;

use App\Models\Booking;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ExportRevenue extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static \UnitEnum|string|null $navigationGroup = 'Reports';

    protected static ?string $title = 'Export Revenue';

    protected static ?string $navigationLabel = 'Export Revenue';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'export-revenue';

    protected string $view = 'filament.pages.export-revenue';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public string $datePreset = 'this_month';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();
        $this->form->fill([
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
        ]);
    }

    public function setDatePreset(string $preset): void
    {
        $this->datePreset = $preset;
        $now = Carbon::now();
        $today = $now->copy()->startOfDay();
        $yesterday = $now->copy()->subDay()->startOfDay();

        $range = match ($preset) {
            'today' => [$today->toDateString(), $today->toDateString()],
            'yesterday' => [$yesterday->toDateString(), $yesterday->toDateString()],
            'this_week' => [$now->copy()->startOfWeek()->toDateString(), $today->toDateString()],
            'last_7_days' => [$now->copy()->subDays(6)->startOfDay()->toDateString(), $today->toDateString()],
            'this_month' => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()],
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'last_30_days' => [$now->copy()->subDays(29)->startOfDay()->toDateString(), $today->toDateString()],
            'this_year' => [$now->copy()->startOfYear()->toDateString(), $now->copy()->endOfYear()->toDateString()],
            'last_year' => [
                $now->copy()->subYear()->startOfYear()->toDateString(),
                $now->copy()->subYear()->endOfYear()->toDateString(),
            ],
            default => [$this->dateFrom, $this->dateTo],
        };

        [$this->dateFrom, $this->dateTo] = $range;
        $this->form->fill([
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'sm' => 2])
            ->components([
                DatePicker::make('dateFrom')
                    ->label('From date')
                    ->required()
                    ->native(false)
                    ->closeOnDateSelection(true)
                    ->live(),
                DatePicker::make('dateTo')
                    ->label('To date')
                    ->required()
                    ->native(false)
                    ->closeOnDateSelection(true)
                    ->live(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportRevenue')
                ->label('Export Revenue')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->action(function () {
                    $data = $this->form->getState();
                    $from = $data['dateFrom'] ?? null;
                    $to = $data['dateTo'] ?? null;

                    if (! $from || ! $to) {
                        Notification::make()
                            ->title('Please select both From and To dates.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $from = Carbon::parse($from)->startOfDay();
                    $to = Carbon::parse($to)->endOfDay();

                    if ($to->lessThan($from)) {
                        Notification::make()
                            ->title('To date must be after From date.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $query = $this->getRevenueQuery($from, $to);
                    $count = $query->count();

                    if ($count === 0) {
                        Notification::make()
                            ->title('No revenue data in the selected period.')
                            ->body('Only paid and completed bookings are included.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $filename = sprintf(
                        'marelinos-resort-hotel-revenue-report-%s-to-%s.csv',
                        $from->format('Y-m-d'),
                        $to->format('Y-m-d')
                    );

                    return $this->streamCsvDownload($query, $filename);
                }),
        ];
    }

    public function getRevenueSummaryProperty(): array
    {
        $data = $this->form->getState();
        $fromStr = $data['dateFrom'] ?? $this->dateFrom;
        $toStr = $data['dateTo'] ?? $this->dateTo;

        if (! $fromStr || ! $toStr) {
            return [
                'total_revenue' => 0,
                'booking_count' => 0,
                'from' => null,
                'to' => null,
                'valid' => false,
            ];
        }

        $from = Carbon::parse($fromStr)->startOfDay();
        $to = Carbon::parse($toStr)->endOfDay();

        if ($to->lessThan($from)) {
            return [
                'total_revenue' => 0,
                'booking_count' => 0,
                'from' => $from,
                'to' => $to,
                'valid' => false,
            ];
        }

        $query = $this->getRevenueQuery($from, $to);
        $totalRevenue = (clone $query)->sum('total_price');
        $bookingCount = (clone $query)->count();

        return [
            'total_revenue' => $totalRevenue,
            'booking_count' => $bookingCount,
            'from' => $from,
            'to' => $to,
            'valid' => true,
        ];
    }

    protected function getRevenueQuery(Carbon $from, Carbon $to): Builder
    {
        return Booking::query()
            ->with([
                'guest:id,first_name,middle_name,last_name,email',
                'rooms:id,name',
                'venues:id,name',
            ])
            ->whereIn('status', [Booking::STATUS_PAID, Booking::STATUS_COMPLETED])
            ->where(function (Builder $q) use ($from, $to): void {
                $q->whereBetween('check_in', [$from, $to])
                    ->orWhereBetween('check_out', [$from, $to])
                    ->orWhere(function (Builder $q2) use ($from, $to): void {
                        $q2->where('check_in', '<=', $from)->where('check_out', '>=', $to);
                    });
            })
            ->orderBy('check_in');
    }

    /**
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    protected function streamCsvDownload(Builder $query, string $filename)
    {
        $csvHeaders = [
            'Reference No.',
            'Guest Name',
            'Guest Email',
            'Check-in',
            'Check-out',
            'Nights',
            'Rooms',
            'Venues',
            'Revenue (₱)',
            'Status',
            'Created At',
        ];

        $callback = function () use ($query, $csvHeaders): void {
            $stream = fopen('php://output', 'w');

            fputcsv($stream, $csvHeaders);

            $query->chunk(100, function ($bookings) use ($stream): void {
                foreach ($bookings as $booking) {
                    $booking->loadMissing(['guest', 'rooms', 'venues']);
                    fputcsv($stream, [
                        $booking->reference_number ?? '—',
                        trim((string) ($booking->guest?->full_name ?? '')) ?: '—',
                        (string) ($booking->guest?->email ?? '') ?: '—',
                        $booking->check_in?->format('d/m/y H:i') ?? '—',
                        $booking->check_out?->format('d/m/y H:i') ?? '—',
                        (string) (int) ($booking->no_of_days ?? 0),
                        $booking->rooms?->pluck('name')->filter()->implode(', ') ?: '—',
                        $booking->venues?->pluck('name')->filter()->implode(', ') ?: '—',
                        number_format((float) ($booking->total_price ?? 0), 2, '.', ','),
                        (string) (Booking::statusOptions()[$booking->status ?? ''] ?? $booking->status ?? '—'),
                        $booking->created_at?->format('d/m/y H:i') ?? '—',
                    ]);
                }
            });

            fclose($stream);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
