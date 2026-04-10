<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedDeleteBulkAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Actions\TypedForceDeleteBulkAction;
use App\Filament\Exports\BookingExporter;
use App\Models\Booking;
use App\Models\Room;
use App\Support\BookingPricing;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordAction('view')
            ->poll('10s')
            ->filtersFormColumns([
                'default' => 1,
                'sm' => 2,
            ])
            ->filtersFormWidth(Width::TwoExtraLarge)
            ->filtersFormMaxHeight('min(75dvh, 32rem)')
            ->columns([
                TextColumn::make('reference_number')
                    ->label('Reference')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Reference copied.'),

                TextColumn::make('guest.first_name')
                    ->label('Guest')
                    ->formatStateUsing(fn ($record) => $record->guest?->full_name ?? '—')
                    ->description(fn ($record) => $record->guest?->email ?: 'No email')
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

                TextColumn::make('guest.email')
                    ->label('Email')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('guest', function (Builder $guestQuery) use ($search): void {
                            $guestQuery->where('email', 'like', "%{$search}%");
                        });
                    })
                    ->copyable()
                    ->copyMessage('Email copied.'),

                TextColumn::make('check_in')
                    ->label('Check-in')
                    ->dateTime('M d, Y g:i A')
                    ->description(fn ($record) => 'Check-out: '.($record->check_out?->format('M d, Y g:i A') ?? '—'))
                    ->sortable(),

                TextColumn::make('check_out')
                    ->label('Check-out')
                    ->dateTime('M d, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('no_of_days')
                    ->label('Nights')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('rooms.name')
                    ->label('Rooms')
                    ->formatStateUsing(function ($record): string {
                        $rooms = $record->rooms;
                        if ($rooms && $rooms->isNotEmpty()) {
                            $count = $rooms->count();

                            return $count === 1 ? '1 room' : "{$count} rooms";
                        }

                        $lines = $record->roomLines;
                        if ($lines && $lines->isNotEmpty()) {
                            $count = (int) $lines->sum(fn ($l) => max(1, (int) ($l->quantity ?? 1)));

                            return $count === 1 ? '1 room (unassigned)' : "{$count} rooms (unassigned)";
                        }

                        $venues = $record->venues;
                        if ($venues && $venues->isNotEmpty()) {
                            return '—';
                        }

                        return '—';
                    })
                    ->description(function ($record): ?string {
                        $rooms = $record->rooms;
                        if ($rooms && $rooms->isNotEmpty()) {
                            $counts = $rooms
                                ->groupBy('type')
                                ->map(fn ($g) => $g->count())
                                ->sortDesc();

                            $labels = $counts->map(function (int $n, $type): string {
                                $type = (string) $type;
                                $typeLabel = Room::typeOptions()[$type] ?? ucfirst($type);

                                return "{$typeLabel}×{$n}";
                            })->values();

                            return $labels->take(2)->implode(' · ').($labels->count() > 2 ? ' · …' : '');
                        }

                        $lines = $record->roomLines;
                        if ($lines && $lines->isNotEmpty()) {
                            $counts = $lines
                                ->groupBy('room_type')
                                ->map(fn ($g) => (int) $g->sum(fn ($l) => max(1, (int) ($l->quantity ?? 1))))
                                ->sortDesc();

                            $labels = $counts->map(function (int $n, $type): string {
                                $type = (string) $type;
                                $typeLabel = Room::typeOptions()[$type] ?? ucfirst($type);

                                return "{$typeLabel}×{$n}";
                            })->values();

                            return $labels->take(2)->implode(' · ').($labels->count() > 2 ? ' · …' : '');
                        }

                        return null;
                    })
                    ->extraAttributes(['class' => 'cursor-pointer'])
                    ->action(
                        Action::make('viewRooms')
                            ->modalHeading('Rooms')
                            ->modalCancelActionLabel('Close')
                            ->modalSubmitAction(false)
                            ->modalContent(function ($record): View {
                                $items = [];
                                $subtitle = null;

                                $rooms = $record->rooms;
                                if ($rooms && $rooms->isNotEmpty()) {
                                    $items = $rooms
                                        ->map(fn (Room $room) => trim($room->adminSelectLabel()))
                                        ->filter()
                                        ->values()
                                        ->all();
                                    $subtitle = 'Assigned rooms';
                                } else {
                                    $lines = $record->roomLines;
                                    if ($lines && $lines->isNotEmpty()) {
                                        $items = $lines
                                            ->map(function ($l): string {
                                                $type = (string) ($l->room_type ?? '');
                                                $typeLabel = Room::typeOptions()[$type] ?? ($type !== '' ? ucfirst($type) : 'Room');
                                                $qty = max(1, (int) ($l->quantity ?? 1));

                                                return "{$typeLabel} × {$qty}";
                                            })
                                            ->filter()
                                            ->values()
                                            ->all();
                                        $subtitle = 'Guest-selected (unassigned)';
                                    }
                                }

                                return view('filament.bookings.inventory-modal', [
                                    'title' => 'Rooms',
                                    'subtitle' => $subtitle,
                                    'items' => $items,
                                ]);
                            })
                    )
                    ->wrap()
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $record = $column->getRecord();
                        if (! $record) {
                            return null;
                        }

                        $rooms = $record->rooms;
                        if ($rooms && $rooms->isNotEmpty()) {
                            $full = $rooms
                                ->map(fn (Room $room) => trim($room->adminSelectLabel()))
                                ->filter()
                                ->values()
                                ->implode("\n");

                            return $full !== '' ? $full : null;
                        }

                        $lines = $record->roomLines;
                        if ($lines && $lines->isNotEmpty()) {
                            $full = $lines
                                ->map(function ($l): string {
                                    $type = (string) ($l->room_type ?? '');
                                    $typeLabel = Room::typeOptions()[$type] ?? ($type !== '' ? ucfirst($type) : 'Room');
                                    $qty = max(1, (int) ($l->quantity ?? 1));

                                    return "{$typeLabel} × {$qty}";
                                })
                                ->filter()
                                ->values()
                                ->implode("\n");

                            return $full !== '' ? $full : null;
                        }

                        return null;
                    })
                    ->toggleable(),

                TextColumn::make('venues.name')
                    ->label('Venue')
                    ->formatStateUsing(function ($record): string {
                        $venues = $record->venues;
                        if (! $venues || $venues->isEmpty()) {
                            return '—';
                        }

                        $names = $venues->pluck('name')->filter()->values();
                        if ($names->isEmpty()) {
                            return '—';
                        }

                        $shown = $names->take(1);
                        $remaining = $names->count() - $shown->count();

                        return $shown->implode(', ').($remaining > 0 ? " +{$remaining}" : '');
                    })
                    ->description(function ($record): ?string {
                        $venues = $record->venues;
                        if (! $venues || $venues->isEmpty()) {
                            return null;
                        }

                        $names = $venues->pluck('name')->filter()->values();
                        if ($names->isEmpty()) {
                            return null;
                        }

                        $count = $names->count();

                        return $count === 1 ? '1 venue' : "{$count} venues";
                    })
                    ->extraAttributes(['class' => 'cursor-pointer'])
                    ->action(
                        Action::make('viewVenues')
                            ->modalHeading('Venues')
                            ->modalCancelActionLabel('Close')
                            ->modalSubmitAction(false)
                            ->modalContent(function ($record): View {
                                $venues = $record->venues;
                                $items = $venues
                                    ? $venues->pluck('name')->filter()->values()->all()
                                    : [];

                                return view('filament.bookings.inventory-modal', [
                                    'title' => 'Venues',
                                    'subtitle' => $venues && $venues->isNotEmpty() ? 'Included venues' : null,
                                    'items' => $items,
                                ]);
                            })
                    )
                    ->tooltip(function (TextColumn $column): ?string {
                        $record = $column->getRecord();
                        if (! $record) {
                            return null;
                        }

                        $venues = $record->venues;
                        if (! $venues || $venues->isEmpty()) {
                            return null;
                        }

                        $full = $venues->pluck('name')->filter()->values()->implode("\n");

                        return $full !== '' ? $full : null;
                    })
                    ->wrap()
                    ->toggleable(false),

                TextColumn::make('venue_event_type')
                    ->label('Event type')
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? (BookingPricing::venueEventTypeOptions()[$state] ?? ucfirst($state))
                        : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_price')
                    ->label('Total')
                    ->money('PHP', true)
                    ->description(fn ($record) => 'Paid: '.number_format((float) ($record->total_paid ?? 0), 2).' · Balance: '.number_format((float) ($record->balance ?? 0), 2))
                    ->sortable(),

                TextColumn::make('total_paid')
                    ->label('Paid')
                    ->money('PHP', true)
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('balance')
                    ->label('Balance')
                    ->money('PHP', true)
                    ->sortable(),

                BadgeColumn::make('status')
                    ->colors(Booking::statusColors())
                    ->formatStateUsing(fn (?string $state): string => Booking::statusOptions()[$state] ?? (string) $state)
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Booking::statusOptions()),
                SelectFilter::make('room')
                    ->label('Room')
                    ->relationship(
                        'rooms',
                        'name',
                        modifyQueryUsing: fn ($query) => $query->with(['bedSpecifications']),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Room $record) => $record->adminSelectLabel())
                    ->multiple()
                    ->preload()
                    ->searchable(),
                SelectFilter::make('venue')
                    ->label('Venue')
                    ->relationship('venues', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->columnSpanFull(),
                Filter::make('booking_dates')
                    ->label('Filter by Dates')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Select::make('year')
                                    ->label('Year')
                                    ->options(fn (): array => collect(range(2000, 2031))
                                        ->mapWithKeys(fn (int $y): array => [$y => (string) $y])
                                        ->all())
                                    ->default((int) now()->format('Y'))
                                    ->live()
                                    ->helperText('2000–2031 · full month or custom From/To below.')
                                    ->visible(fn (Get $get) => ! (bool) $get('use_custom')),
                                Toggle::make('use_custom')
                                    ->label('Use custom dates')
                                    ->helperText('From / To pickers')
                                    ->default(false)
                                    ->live(),
                            ]),
                        ToggleButtons::make('month')
                            ->label(fn (Get $get): string => 'Months ('.($get('year') ?? now()->year).')')
                            ->options(self::monthButtonLabels())
                            ->default(now()->format('m'))
                            ->inline(false)
                            ->columns([
                                'default' => 3,
                                'sm' => 4,
                                'md' => 6,
                            ])
                            ->visible(fn (Get $get) => ! (bool) $get('use_custom')),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->visible(fn (Get $get) => (bool) $get('use_custom'))
                            ->schema([
                                DatePicker::make('start')
                                    ->label('From')
                                    ->native(false),
                                DatePicker::make('end')
                                    ->label('To')
                                    ->native(false),
                            ]),
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

                        if (! (bool) ($data['use_custom'] ?? false)) {
                            $year = $data['year'] ?? null;
                            $month = $data['month'] ?? null;
                            if ($year !== null && $year !== '' && $month !== null && $month !== '') {
                                $label = Carbon::createFromDate((int) $year, (int) $month, 1)->format('F Y');

                                return ["Month: {$label}"];
                            }
                        }

                        $startText = $start?->toDateString() ?? 'Any';
                        $endText = $end?->toDateString() ?? 'Any';

                        return ["Dates: {$startText} → {$endText}"];
                    }),

                TrashedFilter::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                ExportAction::make()
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->exporter(BookingExporter::class)
                    ->modalHeading('Export Bookings')
                    ->modalDescription('Download the current list as Excel or CSV. Applied filters and sort order are used. Choose format and start export.')
                    ->modalSubmitActionLabel('Start export')
                    ->columnMappingColumns(2),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('payBalance')
                        ->label('Pay Balance')
                        ->icon('heroicon-o-banknotes')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Mark booking as fully paid?')
                        ->modalDescription('This records the remaining balance as payment and updates status to Paid.')
                        ->modalSubmitActionLabel('Yes, mark as paid')
                        ->successNotificationTitle('Remaining balance recorded. Booking is now paid.')
                        ->visible(function (Booking $record): bool {
                            if ($record->trashed()) {
                                return false;
                            }

                            $balance = (float) $record->balance;

                            return $balance > 0.009
                                && $record->status !== Booking::STATUS_PAID
                                && $record->status !== Booking::STATUS_CANCELLED
                                && $record->rooms()->exists();
                        })
                        ->action(function (Booking $record) {
                            if (! $record->rooms()->exists()) {
                                Notification::make()
                                    ->title('Cannot mark as paid')
                                    ->body('Assign at least one room before recording full balance payment.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            if (in_array($record->status, [Booking::STATUS_PAID, Booking::STATUS_CANCELLED], true)) {
                                return;
                            }

                            if ((float) $record->balance <= 0.009) {
                                return;
                            }

                            $record->payments()->create([
                                'total_amount' => $record->total_price,
                                'partial_amount' => $record->balance,
                                'is_fullypaid' => true,
                            ]);
                            $record->update(['status' => Booking::STATUS_PAID]);
                        }),
                    Action::make('checkIn')
                        ->label('Check-in')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (Booking $record) => ! $record->trashed() && $record->status === Booking::STATUS_PAID)
                        ->action(fn (Booking $record) => $record->update(['status' => Booking::STATUS_OCCUPIED])),
                    Action::make('complete')
                        ->label('Complete')
                        ->icon('heroicon-o-flag')
                        ->color('secondary')
                        ->requiresConfirmation()
                        ->visible(fn (Booking $record) => ! $record->trashed() && $record->status === Booking::STATUS_OCCUPIED)
                        ->action(fn (Booking $record) => $record->update(['status' => Booking::STATUS_COMPLETED])),
                    Action::make('cancel')
                        ->label('Cancel')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Booking $record) => ! $record->trashed() && ! in_array($record->status, [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED], true))
                        ->action(fn (Booking $record) => $record->update(['status' => Booking::STATUS_CANCELLED])),
                    RestoreAction::make(),
                    TypedForceDeleteAction::make(fn (Booking $record): string => $record->reference_number),
                    TypedDeleteAction::make(fn (Booking $record): string => $record->reference_number),
                    Action::make('resendEmail')
                        ->label('Resend Email')
                        ->icon('heroicon-o-envelope')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Resend Booking Confirmation')
                        ->modalDescription('This will send another booking confirmation email to the guest.')
                        ->modalSubmitActionLabel('Yes, resend email')
                        ->successNotificationTitle('Email successfully resent.')
                        ->visible(fn (Booking $record) => $record->guest?->email !== null)
                        ->action(function (Booking $record) {
                            if ($record->guest?->email) {
                                $mail = \Illuminate\Support\Facades\Mail::to($record->guest->email);
                                $bookingCcAddress = config('mail.booking_cc_address');

                                if (filled($bookingCcAddress)) {
                                    $mail->cc($bookingCcAddress);
                                }

                                $mail->send(new \App\Mail\BookingCreated($record));
                            }
                        }),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    TypedDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    TypedForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<string, string> keys 01–12, full month names
     */
    private static function monthButtonLabels(): array
    {
        return [
            '01' => 'January',
            '02' => 'February',
            '03' => 'March',
            '04' => 'April',
            '05' => 'May',
            '06' => 'June',
            '07' => 'July',
            '08' => 'August',
            '09' => 'September',
            '10' => 'October',
            '11' => 'November',
            '12' => 'December',
        ];
    }

    private static function resolveDateRange(array $data): array
    {
        $useCustom = (bool) ($data['use_custom'] ?? false);

        if (! $useCustom) {
            $year = $data['year'] ?? null;
            $month = $data['month'] ?? null;

            if ($year !== null && $year !== '' && $month !== null && $month !== '') {
                $carbon = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth();

                return [$carbon->copy(), $carbon->copy()->endOfMonth()];
            }

            return [null, null];
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
