<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedDeleteBulkAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Actions\TypedForceDeleteBulkAction;
use App\Filament\Exports\BookingExporter;
use App\Mail\BookingCreated;
use App\Mail\VerifyBookingEmail;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Support\BookingAdminGuidance;
use App\Support\BookingCheckInEligibility;
use App\Support\BookingDamageSettlement;
use App\Support\BookingFullBalancePayment;
use App\Support\BookingLifecycleActions;
use App\Support\BookingPricing;
use App\Support\BookingSpecialDiscount;
use App\Support\CancellationPolicy;
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
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordAction('view')
            ->poll('10s')
            ->striped()
            ->filtersFormColumns([
                'default' => 1,
                'sm' => 2,
                'lg' => 3,
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
                    ->formatStateUsing(fn (Booking $record) => $record->displayGuestName())
                    ->description(fn (Booking $record) => $record->displayGuestEmail() !== '—'
                        ? $record->displayGuestEmail()
                        : 'No email')
                    ->extraAttributes(['class' => 'cursor-pointer'])
                    ->action(
                        Action::make('viewGuest')
                            ->modalHeading('Guest information')
                            ->modalCancelActionLabel('Close')
                            ->modalSubmitAction(false)
                            ->modalContent(function (Booking $record): View {
                                return view('filament.bookings.guest-modal', [
                                    'guest' => $record->guest,
                                    'booking' => $record,
                                ]);
                            })
                    )
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $bookingQuery) use ($search): void {
                            $bookingQuery
                                ->where('guest_name_snapshot', 'like', "%{$search}%")
                                ->orWhereHas('guest', function (Builder $guestQuery) use ($search): void {
                                    $guestQuery
                                        ->where('first_name', 'like', "%{$search}%")
                                        ->orWhere('middle_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%");
                                });
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

                TextColumn::make('rooms')
                    ->label('Rooms')
                    ->getStateUsing(function (Booking $record): string {
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
                    ->description(function (Booking $record): string {
                        $paid = number_format((float) ($record->total_paid ?? 0), 2);
                        $balance = number_format((float) ($record->balance ?? 0), 2);
                        $desc = "Paid: {$paid} · Balance: {$balance}";

                        if (BookingSpecialDiscount::hasDiscount($record)) {
                            $gross = number_format(BookingSpecialDiscount::grossTotal($record), 2);
                            $discount = number_format(BookingSpecialDiscount::discountAmount($record), 2);
                            $target = BookingSpecialDiscount::resolveDiscountTarget($record, (string) ($record->special_discount_target ?? null));
                            $targetLabel = BookingSpecialDiscount::targetLabel($target);
                            $desc .= " · Discount: -{$discount} ({$targetLabel}; Gross {$gross})";
                        }

                        return $desc;
                    })
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

                BadgeColumn::make('payment_method')
                    ->label('Payment intent')
                    ->formatStateUsing(function ($state, $record): string {
                        $method = (string) ($record->payment_method ?? 'cash');
                        $plan = (string) ($record->online_payment_plan ?? '');

                        if ($method === 'online' && preg_match('/^partial_([1-9]|[1-9][0-9])$/', $plan, $matches) === 1) {
                            return 'Online · Partial '.$matches[1].'%';
                        }

                        if ($method === 'online') {
                            return 'Online · Full';
                        }

                        return 'Cash';
                    })
                    ->colors([
                        'success' => fn ($state, $record): bool => (string) ($record->payment_method ?? '') === 'online',
                        'gray' => fn ($state, $record): bool => (string) ($record->payment_method ?? 'cash') !== 'online',
                    ])
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy('payment_method', $direction)
                        ->orderBy('online_payment_plan', $direction)),

                BadgeColumn::make('booking_status')
                    ->label('Stay')
                    ->colors(Booking::bookingStatusColors())
                    ->formatStateUsing(fn (?string $state): string => Booking::bookingStatusOptions()[$state] ?? (string) $state)
                    ->sortable(),
                BadgeColumn::make('payment_status')
                    ->label('Payment')
                    ->colors(Booking::paymentStatusColors())
                    ->formatStateUsing(fn (?string $state): string => Booking::paymentStatusOptions()[$state] ?? (string) $state)
                    ->sortable(),
                BadgeColumn::make('damage_settlement_status')
                    ->label('Damage settlement')
                    ->colors(Booking::damageSettlementStatusColors())
                    ->formatStateUsing(fn (?string $state): string => Booking::damageSettlementStatusOptions()[$state] ?? (string) $state)
                    ->sortable(),
                BadgeColumn::make('damage_due')
                    ->label('Damage due')
                    ->getStateUsing(function (Booking $record): string {
                        $amount = self::damageOutstandingForBooking($record);

                        return $amount > 0
                            ? '₱'.number_format($amount, 2)
                            : '—';
                    })
                    ->color(fn (string $state): string => $state === '—' ? 'gray' : 'danger')
                    ->visible(fn (?Booking $record): bool => $record instanceof Booking
                        && self::damageOutstandingForBooking($record) > 0)
                    ->sortable(false),

                TextColumn::make('next_step')
                    ->label('Next step')
                    ->getStateUsing(fn (Booking $record): string => BookingAdminGuidance::listNextActionLabel($record))
                    ->tooltip(fn (Booking $record): ?string => BookingAdminGuidance::listNextStepTooltip($record))
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('booking_status')
                    ->label('Stay status')
                    ->options(Booking::bookingStatusOptions()),
                SelectFilter::make('payment_status')
                    ->label('Payment status')
                    ->options(Booking::paymentStatusOptions()),
                SelectFilter::make('damage_settlement_status')
                    ->label('Damage settlement')
                    ->options(Booking::damageSettlementStatusOptions()),
                SelectFilter::make('payment_method')
                    ->label('Payment intent')
                    ->options([
                        'cash' => 'Cash',
                        'online_full' => 'Online · Full',
                        'online_partial' => 'Online · Partial',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'cash' => $query->where(function (Builder $q): void {
                                $q->whereNull('payment_method')
                                    ->orWhere('payment_method', 'cash');
                            }),
                            'online_full' => $query->where('payment_method', 'online')
                                ->where(function (Builder $q): void {
                                    $q->whereNull('online_payment_plan')
                                        ->orWhere('online_payment_plan', 'full');
                                }),
                            'online_partial' => $query->where('payment_method', 'online')
                                ->where('online_payment_plan', 'like', 'partial_%'),
                            default => $query,
                        };
                    }),
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
                        Select::make('month')
                            ->label(fn (Get $get): string => 'Month ('.($get('year') ?? now()->year).')')
                            ->options(self::monthButtonLabels())
                            ->placeholder('Select a month')
                            ->native(false)
                            ->searchable()
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
                                if ((string) $month === 'all') {
                                    return ["Year: {$year} (all months)"];
                                }

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
                Action::make('import')
                    ->label('Import')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->modalHeading('Import')
                    ->modalDescription('Step 1: Upload your CSV file. Step 2: Keep "Check file only" turned on and click import. Step 3: If results look correct, turn it off and import again to save.')
                    ->form([
                        Placeholder::make('template_path')
                            ->label('CSV Template')
                            ->content('Copy this template format: `storage/app/examples/legacy-bookings-template.csv`.'),
                        FileUpload::make('csv_file')
                            ->label('CSV File')
                            ->disk('local')
                            ->directory('imports/legacy-bookings')
                            ->storeFiles(false)
                            ->acceptedFileTypes([
                                'text/csv',
                                'text/plain',
                                'application/vnd.ms-excel',
                            ])
                            ->required(),
                        Toggle::make('dry_run')
                            ->label('Check file only (do not save yet)')
                            ->default(true),
                        Toggle::make('allow_duplicates')
                            ->label('Import even if booking may already exist')
                            ->helperText('Keep this OFF in normal use to avoid duplicate bookings.')
                            ->default(false),
                    ])
                    ->action(function (array $data): void {
                        $uploaded = $data['csv_file'] ?? null;
                        $absolutePath = null;

                        if ($uploaded instanceof TemporaryUploadedFile) {
                            $absolutePath = $uploaded->getRealPath();
                        } elseif (is_string($uploaded) && trim($uploaded) !== '') {
                            $candidate = storage_path('app/'.$uploaded);
                            $absolutePath = is_file($candidate) ? $candidate : $uploaded;
                        }

                        if (! is_string($absolutePath) || trim($absolutePath) === '' || ! is_readable($absolutePath)) {
                            Notification::make()
                                ->title('Uploaded CSV file is not readable.')
                                ->body('Please upload the file again and retry import.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $dryRun = (bool) ($data['dry_run'] ?? true);
                        $allowDuplicates = (bool) ($data['allow_duplicates'] ?? false);

                        $exitCode = Artisan::call('bookings:import-legacy-csv', [
                            'file' => $absolutePath,
                            '--dry-run' => $dryRun,
                            '--allow-duplicates' => $allowDuplicates,
                        ]);

                        $output = trim(Artisan::output());
                        $notification = Notification::make()
                            ->title($exitCode === 0 ? 'Import processed.' : 'Import failed.')
                            ->body($output !== '' ? $output : 'No command output.');

                        if ($exitCode === 0) {
                            $notification->success()->send();
                        } else {
                            $notification->danger()->send();
                        }
                    }),
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
                        ->label('Settle remaining balance')
                        ->icon('heroicon-o-banknotes')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Mark booking as fully paid?')
                        ->modalDescription('Records one payment for the full remaining balance and sets payment to Paid. For partial cash amounts, use the Payments tab on the booking.')
                        ->modalSubmitActionLabel('Yes, mark as paid')
                        ->successNotificationTitle('Remaining balance recorded. Booking is now paid.')
                        ->visible(fn (Booking $record): bool => BookingFullBalancePayment::assess($record)['allowed'])
                        ->action(function (Booking $record) {
                            try {
                                BookingFullBalancePayment::record($record);
                            } catch (\InvalidArgumentException $e) {
                                Notification::make()
                                    ->title('Cannot mark as paid')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                throw new Halt;
                            }
                        }),
                    Action::make('markRefundCompleted')
                        ->label('Mark refund completed')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Confirm refund completion?')
                        ->modalDescription(fn (Booking $record): string => CancellationPolicy::adminMarkRefundCompletedModalBody($record))
                        ->visible(fn (Booking $record): bool => ! $record->trashed()
                            && in_array($record->booking_status, [
                                Booking::BOOKING_STATUS_RESCHEDULED,
                                Booking::BOOKING_STATUS_CANCELLED,
                            ], true)
                            && $record->payment_status === Booking::PAYMENT_STATUS_REFUND_PENDING)
                        ->action(function (Booking $record): void {
                            $record->update([
                                'payment_status' => Booking::PAYMENT_STATUS_REFUNDED,
                            ]);

                            Notification::make()
                                ->title('Refund marked as completed.')
                                ->success()
                                ->send();
                        }),
                    Action::make('checkIn')
                        ->label('Check in guest')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Check in this guest?')
                        ->modalDescription('Sets stay status to Occupied (guest is on site).')
                        ->visible(fn (Booking $record) => ! $record->trashed() && BookingCheckInEligibility::assess($record)['allowed'])
                        ->action(function (Booking $record) {
                            $record->loadMissing(['roomLines', 'venues', 'rooms.bedSpecifications']);
                            try {
                                BookingLifecycleActions::checkIn($record);
                            } catch (\InvalidArgumentException $e) {
                                Notification::make()
                                    ->title('Cannot check in')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Booking checked in.')
                                ->success()
                                ->send();
                        }),
                    Action::make('complete')
                        ->label(fn (Booking $record): string => $record->adminCheckoutActionLabel())
                        ->icon('heroicon-o-flag')
                        ->color('secondary')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Booking $record): string => $record->adminCheckoutActionLabel())
                        ->modalDescription('Mark this booking as completed.')
                        ->visible(fn (Booking $record): bool => $record->canAdminCheckout())
                        ->action(function (Booking $record): void {
                            try {
                                BookingLifecycleActions::complete($record);
                            } catch (\InvalidArgumentException $e) {
                                Notification::make()
                                    ->title('Cannot complete')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Booking marked as completed.')
                                ->success()
                                ->send();
                        }),
                    Action::make('markDamageSettled')
                        ->label('Mark damage settled')
                        ->icon('heroicon-o-shield-check')
                        ->color('success')
                        ->modalHeading('Mark damage/loss claim as settled?')
                        ->form([
                            Textarea::make('notes')
                                ->label('Settlement notes')
                                ->rows(3)
                                ->placeholder('Optional accounting note or OR/reference number.'),
                        ])
                        ->visible(fn (Booking $record): bool => (string) $record->damage_settlement_status === Booking::DAMAGE_SETTLEMENT_STATUS_PENDING)
                        ->action(function (Booking $record, array $data): void {
                            BookingDamageSettlement::markSettled(
                                $record,
                                isset($data['notes']) ? (string) $data['notes'] : null,
                                auth()->user(),
                            );

                            Notification::make()
                                ->title('Damage settlement marked as settled.')
                                ->success()
                                ->send();
                        }),
                    Action::make('cancel')
                        ->label('Cancel booking')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Booking $record) => ! $record->trashed() && ! in_array($record->booking_status, [Booking::BOOKING_STATUS_CANCELLED, Booking::BOOKING_STATUS_COMPLETED], true))
                        ->action(function (Booking $record) {
                            try {
                                BookingLifecycleActions::cancel($record);
                            } catch (\InvalidArgumentException $e) {
                                Notification::make()
                                    ->title('Cannot cancel')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Booking cancelled.')
                                ->success()
                                ->send();
                        }),
                    RestoreAction::make(),
                    TypedForceDeleteAction::make(fn (Booking $record): string => $record->reference_number),
                    TypedDeleteAction::make(fn (Booking $record): string => $record->reference_number),
                    Action::make('resendEmail')
                        ->label('Resend Email')
                        ->icon('heroicon-o-envelope')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Resend Booking Confirmation')
                        ->modalDescription('This will resend the booking email to the guest. Pending verification bookings receive the verification email.')
                        ->modalSubmitActionLabel('Yes, resend email')
                        ->successNotificationTitle('Email successfully resent.')
                        ->visible(fn (Booking $record) => $record->guest?->email !== null)
                        ->action(function (Booking $record) {
                            if ($record->guest?->email) {
                                $mail = Mail::to($record->guest->email);
                                $bookingCcAddress = config('mail.booking_cc_address');

                                if (filled($bookingCcAddress)) {
                                    $mail->cc($bookingCcAddress);
                                }

                                if ($record->booking_status === Booking::BOOKING_STATUS_PENDING_VERIFICATION) {
                                    $hours = max(1, (int) config('booking.pending_verification_url_ttl_hours', 72));
                                    $verifyUrl = URL::temporarySignedRoute(
                                        'bookings.verify-email',
                                        now()->addHours($hours),
                                        ['booking' => $record->id],
                                    );
                                    $billingToken = $record->generateBillingAccessToken();
                                    $mail->send(new VerifyBookingEmail($record, $verifyUrl, $billingToken));

                                    return;
                                }

                                $billingToken = $record->generateBillingAccessToken();
                                $mail->send(new BookingCreated($record, $billingToken));
                            }
                        }),
                ]),
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
            'all' => 'All',
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
                if ((string) $month === 'all') {
                    $carbon = Carbon::createFromDate((int) $year, 1, 1)->startOfYear();

                    return [$carbon->copy(), $carbon->copy()->endOfYear()];
                }

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

    private static function damageOutstandingForBooking(Booking $booking): float
    {
        $booking->loadMissing(['roomChecklists.items', 'payments']);

        $damageTotal = (float) $booking->roomChecklists
            ->flatMap(fn ($checklist) => $checklist->items)
            ->filter(fn ($item): bool => in_array((string) ($item->status ?? ''), [
                'broken',
                'missing',
            ], true))
            ->sum(function ($item): float {
                $raw = (string) ($item->charge ?? '0');
                $normalized = preg_replace('/[^0-9.\-]/', '', $raw);
                if (! is_string($normalized) || $normalized === '' || $normalized === '-' || $normalized === '.') {
                    return 0.0;
                }

                return max(0.0, (float) $normalized);
            });

        $damagePaid = (float) $booking->payments
            ->where('payment_type', Payment::TYPE_DAMAGE)
            ->sum('partial_amount');

        return max(0.0, round($damageTotal - $damagePaid, 2));
    }
}
