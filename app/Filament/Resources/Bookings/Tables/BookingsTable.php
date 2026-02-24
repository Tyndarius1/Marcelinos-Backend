<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Filament\Exports\BookingExporter;
use App\Models\Booking;
use Carbon\Carbon;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordAction('view')
            ->poll('10s')
            ->columns([
                TextColumn::make('reference_number')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('guest.first_name')
                    ->label('Guest')
                    ->formatStateUsing(fn ($record) => $record->guest?->full_name ?? '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('guest', function (Builder $guestQuery) use ($search): void {
                            $guestQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('middle_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),

                TextColumn::make('check_in')
                    ->date()
                    ->sortable(),

                TextColumn::make('check_out')
                    ->date()
                    ->sortable(),

                BadgeColumn::make('status')
                    ->colors(Booking::statusColors())
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Booking::statusOptions()),
                Filter::make('booking_dates')
                    ->label('Filter by Dates')
                    ->form([
                        ToggleButtons::make('preset')
                            ->label('Quick dates')
                            ->options([
                                'today' => 'Today',
                                'next_7' => 'Next 7 days',
                                'next_30' => 'Next 30 days',
                                'this_month' => 'This month',
                                'last_month' => 'Last month',
                                'last_30' => 'Last 30 days',
                                'last_year' => 'Last year',
                                'last_2_years' => 'Last 2 years',
                                'this_year' => 'This year',
                            ])
                            ->default('today')
                            ->inline()
                            ->visible(fn (Get $get) => ! (bool) $get('use_custom')),
                        Toggle::make('use_custom')
                            ->label('Use custom dates')
                            ->helperText('Turn this on to pick your own From/To dates.')
                            ->default(false)
                            ->live(),
                        DatePicker::make('start')
                            ->label('From')
                            ->native(false)
                            ->visible(fn (Get $get) => (bool) $get('use_custom')),
                        DatePicker::make('end')
                            ->label('To')
                            ->native(false)
                            ->visible(fn (Get $get) => (bool) $get('use_custom')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        [$start, $end] = self::resolveDateRange($data);

                        if (! $start && ! $end) {
                            return $query;
                        }

                        if ($start && $end && $end->lessThan($start)) {
                            [$start, $end] = [$end, $start];
                        }

                        $start = $start?->startOfDay();
                        $end = $end?->endOfDay();
                        return $query
                            ->when($start && $end, fn (Builder $q) => $q
                                ->where('check_in', '<', $end)
                                ->where('check_out', '>', $start))
                            ->when($start && ! $end, fn (Builder $q) => $q->where('check_out', '>', $start))
                            ->when($end && ! $start, fn (Builder $q) => $q->where('check_in', '<', $end));
                    })
                    ->indicateUsing(function (array $data): array {
                        [$start, $end] = self::resolveDateRange($data);

                        if (! $start && ! $end) {
                            return [];
                        }

                        $startText = $start?->toDateString() ?? 'Any';
                        $endText = $end?->toDateString() ?? 'Any';

                        return ["Dates: {$startText} → {$endText}"];
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                ExportAction::make()
                    ->label('Export Bookings')
                    ->exporter(BookingExporter::class),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    private static function resolveDateRange(array $data): array
    {
        $useCustom = (bool) ($data['use_custom'] ?? false);
        $preset = $useCustom ? null : ($data['preset'] ?? null);

        if ($preset) {
            return match ($preset) {
                'today' => [now()->startOfDay(), now()->endOfDay()],
                'next_7' => [now()->startOfDay(), now()->addDays(7)->endOfDay()],
                'next_30' => [now()->startOfDay(), now()->addDays(30)->endOfDay()],
                'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
                'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
                'last_30' => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
                'last_year' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
                'last_2_years' => [now()->subYears(2)->startOfYear(), now()->subYear()->endOfYear()],
                'this_year' => [now()->startOfYear(), now()->endOfYear()],
                default => [null, null],
            };
        }

        $start = $useCustom && isset($data['start']) && $data['start']
            ? Carbon::parse($data['start'])
            : null;
        $end = $useCustom && isset($data['end']) && $data['end']
            ? Carbon::parse($data['end'])
            : null;

        return [$start, $end];
    }
}
