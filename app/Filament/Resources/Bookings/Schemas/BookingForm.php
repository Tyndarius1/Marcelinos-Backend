<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomBlockedDate;
use App\Models\Venue;
use App\Support\BookingPricing;
use App\Support\RoomInventoryGroupKey;
use App\Support\VenueWeddingPreparation;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class BookingForm
{
    /**
     * Calendar days to disable in Filament date pickers (Y-m-d): resort-wide blocks plus
     * staff blocks on the selected room(s), so blocked days show as unavailable (styled in theme).
     *
     * @param  array<int|string|null>  $roomIds
     * @return array<int, string>
     */
    public static function disabledCalendarDateStringsForWizard(array $roomIds): array
    {
        $roomIds = array_values(array_filter(array_map('intval', $roomIds)));
        sort($roomIds);
        $cacheKey = 'booking.disabled_dates.wizard.v'.self::disabledDatesCacheVersion().'.'.md5(json_encode($roomIds));

        return Cache::remember($cacheKey, 300, function () use ($roomIds): array {
            $dates = collect();

            foreach (BlockedDate::query()->get(['date']) as $row) {
                $d = $row->date;
                $dates->push($d instanceof CarbonInterface ? $d->format('Y-m-d') : Carbon::parse($d)->format('Y-m-d'));
            }

            if ($roomIds !== []) {
                RoomBlockedDate::query()
                    ->whereIn('room_id', $roomIds)
                    ->get(['blocked_on'])
                    ->each(function ($row) use ($dates): void {
                        $dates->push(Carbon::parse($row->blocked_on)->format('Y-m-d'));
                    });
            }

            return $dates->unique()->sort()->values()->all();
        });
    }

    public static function bumpDisabledDatesCacheVersion(): void
    {
        $key = 'booking.disabled_dates.version';

        if (! Cache::has($key)) {
            Cache::forever($key, 1);
        }

        Cache::increment($key);
    }

    private static function disabledDatesCacheVersion(): int
    {
        $key = 'booking.disabled_dates.version';

        if (! Cache::has($key)) {
            Cache::forever($key, 1);
        }

        return max(1, (int) Cache::get($key, 1));
    }

    /**
     * @return array<int, string> Y-m-d dates for days before today
     */
    public static function pastCalendarDateStrings(int $daysBack = 3650): array
    {
        $today = now()->startOfDay();
        $start = $today->copy()->subDays(max(0, $daysBack));
        $end = $today->copy()->subDay();

        if ($end->lessThan($start)) {
            return [];
        }

        $dates = [];
        for ($d = $start->copy(); $d->lessThanOrEqualTo($end); $d->addDay()) {
            $dates[] = $d->format('Y-m-d');
        }

        return $dates;
    }

    /**
     * Distinct row backgrounds for “type + bed spec” groups (Tailwind must see full class strings).
     *
     * @var array<int, string>
     */
    private const ROOM_ASSIGNMENT_GROUP_BG_CLASSES = [
        'rounded-sm px-2 py-0.5 border-s-[3px] border-amber-500 bg-amber-500/15 dark:bg-amber-500/20',
        'rounded-sm px-2 py-0.5 border-s-[3px] border-sky-500 bg-sky-500/15 dark:bg-sky-500/20',
        'rounded-sm px-2 py-0.5 border-s-[3px] border-violet-500 bg-violet-500/15 dark:bg-violet-500/20',
        'rounded-sm px-2 py-0.5 border-s-[3px] border-emerald-500 bg-emerald-500/15 dark:bg-emerald-500/20',
        'rounded-sm px-2 py-0.5 border-s-[3px] border-rose-500 bg-rose-500/15 dark:bg-rose-500/20',
        'rounded-sm px-2 py-0.5 border-s-[3px] border-orange-500 bg-orange-500/15 dark:bg-orange-500/20',
        'rounded-sm px-2 py-0.5 border-s-[3px] border-cyan-500 bg-cyan-500/15 dark:bg-cyan-500/20',
        'rounded-sm px-2 py-0.5 border-s-[3px] border-fuchsia-500 bg-fuchsia-500/15 dark:bg-fuchsia-500/20',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Booking details')
                ->description('Guest, room allocation, and venue assignment.')
                ->extraAttributes(['class' => 'h-full'])
                ->columns(2)
                ->schema([
                    Select::make('guest_id')
                        ->label('Guest')
                        ->relationship('guest', 'first_name')
                        ->getOptionLabelFromRecordUsing(fn (Guest $record) => $record->full_name)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->visibleOn('create')
                        ->columnSpanFull(),
                    Section::make('Guest information')
                        ->description('Edit guest details here. Changes are synced to the guest record after saving.')
                        ->columns(2)
                        ->schema([
                            TextInput::make('guest_first_name')
                                ->label('First name')
                                ->required()
                                ->formatStateUsing(fn (?Booking $record): string => (string) ($record?->guest?->first_name ?? ''))
                                ->visibleOn('edit'),
                            TextInput::make('guest_middle_name')
                                ->label('Middle name')
                                ->formatStateUsing(fn (?Booking $record): string => (string) ($record?->guest?->middle_name ?? ''))
                                ->visibleOn('edit'),
                            TextInput::make('guest_last_name')
                                ->label('Last name')
                                ->required()
                                ->formatStateUsing(fn (?Booking $record): string => (string) ($record?->guest?->last_name ?? ''))
                                ->visibleOn('edit'),
                            TextInput::make('guest_info_email')
                                ->label('Email')
                                ->email()
                                ->required()
                                ->formatStateUsing(fn (?Booking $record): string => (string) ($record?->guest?->email ?? ''))
                                ->visibleOn('edit'),
                            Select::make('guest_gender')
                                ->label('Gender')
                                ->options(Guest::genderOptions())
                                ->required()
                                ->formatStateUsing(fn (?Booking $record): ?string => $record?->guest?->gender)
                                ->native(false)
                                ->visibleOn('edit'),
                            Toggle::make('guest_is_international')
                                ->label('Foreign / international address')
                                ->default(false)
                                ->formatStateUsing(fn (?Booking $record): bool => (bool) ($record?->guest?->is_international ?? false))
                                ->live()
                                ->visibleOn('edit'),
                            TextInput::make('guest_info_contact_num')
                                ->label('Contact number')
                                ->required(fn (Get $get): bool => ! ((bool) $get('guest_is_international')))
                                ->formatStateUsing(fn (?Booking $record): string => (string) ($record?->guest?->contact_num ?? ''))
                                ->visibleOn('edit'),
                            TextInput::make('guest_info_country')
                                ->label('Country')
                                ->required(fn (Get $get): bool => (bool) $get('guest_is_international'))
                                ->formatStateUsing(fn (?Booking $record): string => (string) ($record?->guest?->country ?? 'Philippines'))
                                ->visible(fn (Get $get): bool => (bool) $get('guest_is_international'))
                                ->visibleOn('edit'),
                            TextInput::make('guest_region')
                                ->label('Region')
                                ->formatStateUsing(fn (?Booking $record): string => (string) ($record?->guest?->region ?? ''))
                                ->visible(fn (Get $get): bool => ! ((bool) $get('guest_is_international')))
                                ->visibleOn('edit'),
                            TextInput::make('guest_province')
                                ->label('Province')
                                ->formatStateUsing(fn (?Booking $record): string => (string) ($record?->guest?->province ?? ''))
                                ->visible(fn (Get $get): bool => ! ((bool) $get('guest_is_international')))
                                ->visibleOn('edit'),
                            TextInput::make('guest_municipality')
                                ->label('Municipality')
                                ->formatStateUsing(fn (?Booking $record): string => (string) ($record?->guest?->municipality ?? ''))
                                ->visible(fn (Get $get): bool => ! ((bool) $get('guest_is_international')))
                                ->visibleOn('edit'),
                            TextInput::make('guest_barangay')
                                ->label('Barangay')
                                ->formatStateUsing(fn (?Booking $record): string => (string) ($record?->guest?->barangay ?? ''))
                                ->visible(fn (Get $get): bool => ! ((bool) $get('guest_is_international')))
                                ->visibleOn('edit'),
                        ])
                        ->columnSpanFull(),

                    Section::make('Guest booking (billing summary)')
                        ->description(fn (?Booking $record): ?HtmlString => self::guestBookingBillingSummaryHtml($record))
                        ->visible(fn (?Booking $record) => self::shouldShowGuestBookingBillingSummary($record))
                        ->schema([
                            Hidden::make('guest_booking_section_placeholder')->dehydrated(false),
                        ])
                        ->columnSpanFull(),

                    Select::make('rooms')
                        ->label('Assigned rooms')
                        ->visible(fn (Get $get, ?Booking $record): bool => self::shouldShowAssignedRoomsField($get, $record))
                        ->relationship(
                            'rooms',
                            'name',
                            modifyQueryUsing: function ($query, ?string $search, ?Booking $record, Get $get): void {
                                $roomTableKey = $query->getModel()->getQualifiedKeyName();
                                $roomStatusCol = $query->getModel()->qualifyColumn('status');

                                $checkIn = $get('check_in');
                                $checkOut = $get('check_out');

                                if (! $checkIn || ! $checkOut) {
                                    // If editing an existing booking, keep current assigned rooms visible
                                    if ($record instanceof Booking) {
                                        $record->loadMissing('rooms');
                                        $current = $record->rooms->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                                        if ($current !== []) {
                                            $query->whereIn($roomTableKey, $current)->with(['bedSpecifications']);
                                            return;
                                        }
                                    }

                                    // Otherwise show non-maintenance rooms as fallback
                                    $query->where($roomStatusCol, '!=', Room::STATUS_MAINTENANCE)->with(['bedSpecifications']);
                                    $typeCol = $query->getModel()->qualifyColumn('type');
                                    $nameCol = $query->getModel()->qualifyColumn('name');
                                    $query->orderBy($typeCol)->orderBy($nameCol);
                                    return;
                                }

                                try {
                                    $start = Carbon::parse((string) $checkIn);
                                    $end = Carbon::parse((string) $checkOut);
                                } catch (\Exception $e) {
                                    $query->where($roomStatusCol, '!=', Room::STATUS_MAINTENANCE)->with(['bedSpecifications']);
                                    $typeCol = $query->getModel()->qualifyColumn('type');
                                    $nameCol = $query->getModel()->qualifyColumn('name');
                                    $query->orderBy($typeCol)->orderBy($nameCol);
                                    return;
                                }

                                if ($end->lessThanOrEqualTo($start)) {
                                    $query->where($roomStatusCol, '!=', Room::STATUS_MAINTENANCE)->with(['bedSpecifications']);
                                    $typeCol = $query->getModel()->qualifyColumn('type');
                                    $nameCol = $query->getModel()->qualifyColumn('name');
                                    $query->orderBy($typeCol)->orderBy($nameCol);
                                    return;
                                }

                                // Filter rooms available between the selected dates
                                $typeCol = $query->getModel()->qualifyColumn('type');
                                $nameCol = $query->getModel()->qualifyColumn('name');
                                $query->availableBetween($start, $end, $record?->id ?? null)
                                    ->with(['bedSpecifications'])
                                    ->orderBy($typeCol)
                                    ->orderBy($nameCol);
                            },
                        )
                        ->multiple()
                        ->placeholder('Select an option')
                        ->searchable()
                        ->preload()
                        ->allowHtml()
                        ->getOptionLabelFromRecordUsing(function (Room $record): string {
                            $booking = request()->route('record');
                            $booking = $booking instanceof Booking ? $booking : null;

                            return self::assignedRoomOptionHtml($record, $booking);
                        })
                        ->live()
                        ->helperText('Color-coded by room type and bed specification. Change room types freely. Totals update as selections change.')
                        ->rules([
                            fn (Get $get, ?Booking $record) => function (string $attribute, $value, $fail) use ($get, $record): void {
                                $roomIds = array_filter((array) ($get('rooms') ?? []));
                                $venueIds = array_filter((array) ($get('venues') ?? []));
                                $hasRoomLines = $record instanceof Booking && $record->roomLines()->exists();

                                if ($roomIds === [] && $venueIds === [] && ! $hasRoomLines) {
                                    $fail('Select at least one room or one venue.');
                                }
                            },
                            fn (Get $get, ?Booking $record) => function (string $attribute, $value, $fail) use ($get, $record): void {
                                if ($record instanceof Booking && $record->booking_status === Booking::BOOKING_STATUS_CANCELLED) {
                                    return;
                                }

                                if (self::hasRoomConflicts($value, $get('check_in'), $get('check_out'), $record)) {
                                    $fail('One or more selected rooms are not available for the chosen dates.');
                                }
                            },
                        ])
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            // Allow any room selection without clamping to room lines
                            self::updatePricing($get, $set);
                        })
                        ->columnSpanFull(),

                    Select::make('venues')
                        ->label('Venues')
                        ->relationship(
                            'venues',
                            'name',
                            modifyQueryUsing: function ($query, ?string $search, ?Booking $record, Get $get): void {
                                self::constrainAvailableVenuesQuery($query, $get, $record);
                            },
                        )
                        ->multiple()
                        ->placeholder('Select an option')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->helperText('Optional. Uses the same date-range availability checks.')
                        ->rules([
                            fn (Get $get, ?Booking $record) => function (string $attribute, $value, $fail) use ($get, $record): void {
                                $roomIds = array_filter((array) ($get('rooms') ?? []));
                                $venueIds = array_filter((array) ($get('venues') ?? []));
                                $hasRoomLines = $record instanceof Booking && $record->roomLines()->exists();

                                if ($roomIds === [] && $venueIds === [] && ! $hasRoomLines) {
                                    $fail('Select at least one room or one venue.');
                                }
                            },
                            fn (Get $get, ?Booking $record) => function (string $attribute, $value, $fail) use ($get, $record): void {
                                if ($record instanceof Booking && $record->booking_status === Booking::BOOKING_STATUS_CANCELLED) {
                                    return;
                                }

                                if (self::hasVenueConflicts(
                                    $value,
                                    $get('check_in'),
                                    $get('check_out'),
                                    $record,
                                    is_string($get('venue_event_type')) ? $get('venue_event_type') : null,
                                )) {
                                    $fail('One or more selected venues are not available for the chosen dates.');
                                }
                            },
                        ])
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::updatePricing($get, $set))
                        ->columnSpanFull(),

                    Radio::make('venue_event_type')
                        ->label('Venue event type')
                        ->options(BookingPricing::venueEventTypeOptions())
                        ->default(BookingPricing::VENUE_EVENT_WEDDING)
                        ->formatStateUsing(fn ($state): string => BookingPricing::normalizeVenueEventType(is_string($state) ? $state : null))
                        ->dehydrateStateUsing(fn ($state): string => BookingPricing::normalizeVenueEventType(is_string($state) ? $state : null))
                        ->visible(fn (Get $get, ?Booking $record): bool => ! $record instanceof Booking && self::shouldShowVenueEventTypeField($get, $record))
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::updatePricing($get, $set)),

                ]),

            Section::make('Schedule and pricing')
                ->description('Set stay dates and review auto-computed totals.')
                ->extraAttributes(['class' => 'h-full'])
                ->columns(2)
                ->schema([
                    DateTimePicker::make('check_in')
                        ->required()
                        ->native(false)
                        ->live(onBlur: true)
                        ->seconds(false)
                        ->minDate(fn (?Booking $record) => $record instanceof Booking ? null : now()->startOfDay())
                        ->disabledDates(fn (Get $get): array => self::disabledCalendarDateStringsForWizard(array_filter((array) ($get('rooms') ?? []))))
                        ->helperText('Check-in time is fixed at 12:00 PM for rooms.')
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            // Apply fixed times based on booking type
                            self::applyFixedTimes($get, $set);
                            
                            // Auto-set check-out to next day with fixed time
                            self::autoSetCheckOut($get, $set);
                            
                            self::updatePricing($get, $set);
                        }),

                    DateTimePicker::make('check_out')
                        ->required()
                        ->native(false)
                        ->live(onBlur: true)
                        ->seconds(false)
                        ->minDate(fn (Get $get, ?Booking $record) => $record instanceof Booking
                            ? null
                            : (filled($get('check_in'))
                                ? (self::isVenueOnlyBookingState($get)
                                    ? Carbon::parse($get('check_in'))->startOfDay()
                                    : Carbon::parse($get('check_in'))->startOfDay()->addDay())
                                : now()))
                        ->disabledDates(function (Get $get): array {
                            $disabled = self::disabledCalendarDateStringsForWizard(array_filter((array) ($get('rooms') ?? [])));

                            $checkIn = $get('check_in');
                            if (filled($checkIn) && ! self::isVenueOnlyBookingState($get)) {
                                try {
                                    $disabled[] = Carbon::parse($checkIn)->format('Y-m-d');
                                } catch (\Exception $e) {
                                    // ignore invalid date
                                }
                            }

                            return array_values(array_unique($disabled));
                        })
                        ->helperText('Check-out time is fixed at 10:00 AM for rooms.')
                        ->rules([
                            fn (Get $get) => function (string $attribute, $value, $fail) use ($get): void {
                                $checkIn = $get('check_in');
                                if (! $checkIn || ! $value) {
                                    return;
                                }

                                try {
                                    $start = Carbon::parse($checkIn);
                                    $end = Carbon::parse($value);
                                } catch (\Exception $e) {
                                    return;
                                }

                                if (self::isVenueOnlyBookingState($get)) {
                                    if ($end->copy()->startOfDay()->lt($start->copy()->startOfDay())) {
                                        $fail('Check-out date cannot be before check-in date.');
                                    }

                                    return;
                                }

                                if ($end->lessThanOrEqualTo($start) || $end->isSameDay($start)) {
                                    $fail('Check-out must be at least the next day after check-in.');
                                }
                            },
                        ])
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            // Ensure check-out time remains fixed (don't allow it to be changed)
                            self::applyFixedTimes($get, $set);
                            self::updatePricing($get, $set);
                        }),

                    TextInput::make('no_of_days')
                        ->label('Nights')
                        ->numeric()
                        ->suffix(' nights')
                        ->readOnly()
                        ->dehydrated(),

                    TextInput::make('total_price')
                        ->default(0)
                        ->readOnly()
                        ->dehydrated()
                        ->numeric()
                        ->prefix('₱')
                        ->helperText('Auto-calculated from selected rooms/venues and nights.'),

                    Section::make('Status and reference')
                        ->columns(2)
                        ->columnSpanFull()
                        ->schema([
                            Select::make('booking_status')
                                ->label('Stay status')
                                ->options(Booking::bookingStatusOptions())
                                ->required(),
                            Select::make('payment_status')
                                ->label('Payment status')
                                ->options(Booking::paymentStatusOptions())
                                ->required(),
                            Select::make('damage_settlement_status')
                                ->label('Damage settlement')
                                ->options(Booking::damageSettlementStatusOptions())
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('has_damage_claim')
                                ->label('Damage claim')
                                ->formatStateUsing(fn ($state): string => (bool) $state ? 'Yes' : 'No')
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('damage_settlement_notes')
                                ->label('Damage settlement notes')
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('reference_number')
                                ->label('Reference number')
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('payment_method')
                                ->label('Payment method')
                                ->formatStateUsing(fn ($state) => $state ? strtoupper((string) $state) : 'CASH')
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('online_payment_plan')
                                ->label('Online payment plan')
                                ->formatStateUsing(function ($state): string {
                                    $value = (string) $state;
                                    if (preg_match('/^partial_([1-9]|[1-9][0-9])$/', $value, $matches) === 1) {
                                        return 'PARTIAL '.$matches[1].'%';
                                    }

                                    return match ($value) {
                                        'full' => 'FULL',
                                        default => '—',
                                    };
                                })
                                ->disabled()
                                ->dehydrated(false),
                        ]),
                ]),
        ]);
    }

    public static function updatePricing(Get $get, Set $set): void
    {
        self::calculateDays($get, $set);
        self::calculateTotal($get, $set);
    }

    public static function calculateDays(Get $get, Set $set): void
    {
        $checkIn = $get('check_in');
        $checkOut = $get('check_out');

        if (! $checkIn || ! $checkOut) {
            $set('no_of_days', 0);

            return;
        }

        try {
            $startDate = Carbon::parse($checkIn);
            $endDate = Carbon::parse($checkOut);

            if (self::isVenueOnlyBookingState($get)) {
                if ($endDate->copy()->startOfDay()->lt($startDate->copy()->startOfDay())) {
                    $set('no_of_days', 0);

                    return;
                }

                // Venue-only bookings are billed as inclusive calendar days.
                $days = (int) $startDate->copy()->startOfDay()->diffInDays($endDate->copy()->startOfDay()) + 1;
                $set('no_of_days', max(1, $days));

                return;
            }

            if ($endDate->lessThanOrEqualTo($startDate)) {
                $set('no_of_days', 0);

                return;
            }

            // Room bookings follow calendar lodging nights, not elapsed 24-hour blocks.
            $days = (int) $startDate->copy()->startOfDay()->diffInDays($endDate->copy()->startOfDay());
            $set('no_of_days', max(1, $days));
        } catch (\Exception $e) {
            $set('no_of_days', 0);
        }
    }

    public static function calculateTotal(Get $get, Set $set): void
    {
        $roomIds = $get('rooms') ?? [];
        $venueIds = $get('venues') ?? [];
        $days = (int) $get('no_of_days');

        $roomIds = is_array($roomIds) ? $roomIds : [$roomIds];
        $venueIds = is_array($venueIds) ? $venueIds : [$venueIds];
        $roomIds = array_filter($roomIds);
        $venueIds = array_filter($venueIds);

        // For the create-booking wizard (new record), expose breakdown fields and allow
        // an editable venue subtotal ONLY when venue event type is "Others".
        $routeRecord = request()->route('record');
        $record = $routeRecord instanceof Booking ? $routeRecord : null;
        $isWizardCreate = ! $record instanceof Booking;

        if (($roomIds || $venueIds) && $days > 0) {
            if ($roomIds !== []) {
                $roomsTotal = Room::whereIn('id', $roomIds)->sum('price');
            } else {
                $roomsTotal = 0.0;
                if ($record instanceof Booking) {
                    $record->loadMissing('roomLines');
                    if ($record->roomLines->isNotEmpty()) {
                        $roomsTotal = (float) $record->roomLines->sum(
                            fn ($line) => $line->quantity * (float) $line->unit_price_per_night
                        );
                    }
                }
            }
            $venueEventTypeRaw = is_string($get('venue_event_type')) ? $get('venue_event_type') : null;
            $venueEventType = BookingPricing::normalizeVenueEventType($venueEventTypeRaw);
            $venues = Venue::whereIn('id', $venueIds)->get();
            $computedVenuesTotal = BookingPricing::sumVenueLine($venues, $venueEventType);

            $effectiveVenuesTotal = $computedVenuesTotal;
            if ($isWizardCreate && $venueIds !== [] && $venueEventType === BookingPricing::VENUE_EVENT_OTHERS) {
                $manual = $get('venue_subtotal');
                if ($manual !== null && $manual !== '') {
                    $effectiveVenuesTotal = max(0.0, (float) $manual);
                } else {
                    $effectiveVenuesTotal = max(0.0, (float) $computedVenuesTotal);
                }
            }

            if ($isWizardCreate) {
                $set('rooms_subtotal', (float) $roomsTotal);

                if ($venueIds === []) {
                    $set('venue_subtotal', 0);
                } elseif ($venueEventType === BookingPricing::VENUE_EVENT_OTHERS) {
                    // Only initialize default when empty; don't overwrite user edits.
                    $current = $get('venue_subtotal');
                    if ($current === null || $current === '') {
                        $set('venue_subtotal', (float) $effectiveVenuesTotal);
                    }
                } else {
                    // Fixed venue pricing for non-Others.
                    $set('venue_subtotal', (float) $computedVenuesTotal);
                }
            }

            $set('total_price', ($roomsTotal + $effectiveVenuesTotal) * $days);
        } else {
            $set('total_price', 0);
            if ($isWizardCreate) {
                $set('rooms_subtotal', 0);
                $set('venue_subtotal', 0);
            }
        }
    }

    /**
     * Recomputes derived booking form fields when state is updated outside the main form
     * flow (for example, in a slide-over editor).
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function syncDerivedState(array $state, ?Booking $record = null): array
    {
        $checkIn = $state['check_in'] ?? null;
        $checkOut = $state['check_out'] ?? null;
        $roomIds = array_values(array_filter((array) ($state['rooms'] ?? [])));
        $venueIds = array_values(array_filter((array) ($state['venues'] ?? [])));

        $days = 0;

        if ($checkIn && $checkOut) {
            try {
                $startDate = Carbon::parse((string) $checkIn);
                $endDate = Carbon::parse((string) $checkOut);

                $isVenueOnly = $roomIds === [] && $venueIds !== []
                    && ! ($record instanceof Booking && $record->expectsRoomAssignments());

                if ($isVenueOnly) {
                    if (! $endDate->copy()->startOfDay()->lt($startDate->copy()->startOfDay())) {
                        $days = max(1, (int) $startDate->copy()->startOfDay()->diffInDays($endDate->copy()->startOfDay()) + 1);
                    }
                } elseif ($endDate->greaterThan($startDate) && ! $endDate->isSameDay($startDate)) {
                    $days = max(1, (int) $startDate->copy()->startOfDay()->diffInDays($endDate->copy()->startOfDay()));
                }
            } catch (\Exception $e) {
                $days = 0;
            }
        }

        $state['no_of_days'] = $days;

        if (($roomIds !== [] || $venueIds !== []) && $days > 0) {
            if ($roomIds !== []) {
                $roomsTotal = (float) Room::query()->whereIn('id', $roomIds)->sum('price');
            } else {
                $roomsTotal = 0.0;
                if ($record instanceof Booking) {
                    $record->loadMissing('roomLines');
                    if ($record->roomLines->isNotEmpty()) {
                        $roomsTotal = (float) $record->roomLines->sum(
                            fn ($line) => $line->quantity * (float) $line->unit_price_per_night
                        );
                    }
                }
            }

            $venueEventType = $state['venue_event_type'] ?? null;
            $venues = Venue::query()->whereIn('id', $venueIds)->get();
            $venuesTotal = BookingPricing::sumVenueLine($venues, $venueEventType);

            $state['total_price'] = ($roomsTotal + $venuesTotal) * $days;
        } else {
            $state['total_price'] = 0;
        }

        return $state;
    }

    public static function hasRoomConflicts($roomIds, $checkIn, $checkOut, ?Booking $record): bool
    {
        $roomIds = is_array($roomIds) ? $roomIds : [$roomIds];
        $roomIds = array_filter($roomIds);

        if (empty($roomIds) || ! $checkIn || ! $checkOut) {
            return false;
        }

        try {
            $start = Carbon::parse($checkIn);
            $end = Carbon::parse($checkOut);
        } catch (\Exception $e) {
            return false;
        }

        if ($end->lessThanOrEqualTo($start)) {
            return false;
        }

        if (BlockedDate::overlapsRange($start, $end)) {
            return true;
        }

        if (Booking::query()
            ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
            ->whereNotIn('booking_status', [Booking::BOOKING_STATUS_CANCELLED, Booking::BOOKING_STATUS_COMPLETED])
            ->where('check_in', '<', $end)
            ->where('check_out', '>', $start)
            ->whereHas('rooms', fn ($query) => $query->whereIn('rooms.id', $roomIds))
            ->exists()) {
            return true;
        }

        return Room::whereIn('id', $roomIds)
            ->whereHas('roomBlockedDates', function ($query) use ($start, $end): void {
                $query->overlappingBookingRange($start, $end);
            })
            ->exists();
    }

    public static function hasVenueConflicts($venueIds, $checkIn, $checkOut, ?Booking $record, ?string $venueEventType = null): bool
    {
        $venueIds = is_array($venueIds) ? $venueIds : [$venueIds];
        $venueIds = array_filter($venueIds);

        if (empty($venueIds) || ! $checkIn || ! $checkOut) {
            return false;
        }

        try {
            $start = Carbon::parse($checkIn);
            $end = Carbon::parse($checkOut);
        } catch (\Exception $e) {
            return false;
        }

        $startDay = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        if ($endDay->lt($startDay)) {
            return false;
        }

        if (self::hasBlockedCalendarDatesInInclusiveRange($startDay, $endDay)) {
            return true;
        }

        if (Venue::query()
            ->whereIn('id', $venueIds)
            ->where(function ($query) use ($startDay, $endDay): void {
                $query->where('status', Venue::STATUS_MAINTENANCE)
                    ->orWhereHas('venueBlockedDates', function ($blocked) use ($startDay, $endDay): void {
                        $blocked->whereDate('venue_blocked_dates.blocked_on', '>=', $startDay->toDateString())
                            ->whereDate('venue_blocked_dates.blocked_on', '<=', $endDay->toDateString());
                    });
            })
            ->exists()) {
            return true;
        }

        $venuesCount = count($venueIds);
        $availableCount = Venue::query()
            ->whereIn('id', $venueIds)
            ->availableBetween(
                $start,
                $end,
                $record?->id,
                BookingPricing::normalizeVenueEventType($venueEventType),
                true,
            )
            ->count();

        return $availableCount < $venuesCount;
    }

    public static function constrainAvailableVenuesQuery($query, Get $get, ?Booking $record = null): void
    {
        $checkIn = $get('check_in');
        $checkOut = $get('check_out');
        $venueTableKey = $query->getModel()->getQualifiedKeyName();

        $currentVenueIds = [];
        if ($record instanceof Booking) {
            $record->loadMissing('venues:id');
            $currentVenueIds = $record->venues->pluck('id')->map(fn ($id) => (int) $id)->filter()->values()->all();
        }

        if (! $checkIn || ! $checkOut) {
            if ($currentVenueIds !== []) {
                $query->whereIn($venueTableKey, $currentVenueIds);
            } else {
                $query->whereRaw('0 = 1');
            }

            return;
        }

        try {
            $start = Carbon::parse((string) $checkIn);
            $end = Carbon::parse((string) $checkOut);
        } catch (\Exception $e) {
            if ($currentVenueIds !== []) {
                $query->whereIn($venueTableKey, $currentVenueIds);
            } else {
                $query->whereRaw('0 = 1');
            }

            return;
        }

        $startDay = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();
        if ($endDay->lt($startDay)) {
            if ($currentVenueIds !== []) {
                $query->whereIn($venueTableKey, $currentVenueIds);
            } else {
                $query->whereRaw('0 = 1');
            }

            return;
        }

        if (self::hasBlockedCalendarDatesInInclusiveRange($startDay, $endDay)) {
            if ($currentVenueIds !== []) {
                $query->whereIn($venueTableKey, $currentVenueIds);
            } else {
                $query->whereRaw('0 = 1');
            }

            return;
        }

        $nameCol = $query->getModel()->qualifyColumn('name');

        $venueEventType = (string) ($get('venue_event_type') ?? '');
        if ($venueEventType === '') {
            $venueEventType = BookingPricing::VENUE_EVENT_WEDDING;
        } else {
            $venueEventType = BookingPricing::normalizeVenueEventType($venueEventType);
        }
        $hasVenueSelection = ! empty(array_filter((array) ($get('venues') ?? [])));
        $effIn = VenueWeddingPreparation::effectiveVenueBlockStart($start, $venueEventType, $hasVenueSelection);
        $end = $end->copy();

        $query->where(function ($outerQuery) use ($query, $startDay, $endDay, $effIn, $end, $record, $currentVenueIds, $venueTableKey): void {
            $outerQuery
                ->where(function ($availableQuery) use ($query, $startDay, $endDay, $effIn, $end, $record): void {
                    $availableQuery
                        ->where($query->getModel()->qualifyColumn('status'), '!=', Venue::STATUS_MAINTENANCE)
                        ->whereDoesntHave('bookings', function ($bookingQuery) use ($effIn, $end, $record): void {
                            $bookingQuery
                                ->whereIn('bookings.booking_status', Booking::availabilityBlockingStatuses());
                            VenueWeddingPreparation::constrainToBookingsThatCollideWithVenueCandidateRange(
                                $bookingQuery,
                                $effIn,
                                $end,
                                $record instanceof Booking ? (int) $record->id : null,
                            );
                        })
                        ->whereDoesntHave('venueBlockedDates', function ($blockedQuery) use ($startDay, $endDay): void {
                            $blockedQuery
                                ->whereDate('venue_blocked_dates.blocked_on', '>=', $startDay->toDateString())
                                ->whereDate('venue_blocked_dates.blocked_on', '<=', $endDay->toDateString());
                        });
                });

            if ($currentVenueIds !== []) {
                $outerQuery->orWhereIn($venueTableKey, $currentVenueIds);
            }
        })
            ->orderBy($nameCol);
    }

    /**
     * HTML label for the Assigned rooms select: colored strip per type + bed-spec group.
     * When the booking has room lines, colors follow the same order as the billing summary lines.
     */
    private static function assignedRoomOptionHtml(Room $room, ?Booking $booking): string
    {
        $room->loadMissing(['bedSpecifications']);
        $igk = RoomInventoryGroupKey::forRoom($room);
        $palette = self::ROOM_ASSIGNMENT_GROUP_BG_CLASSES;
        $n = count($palette);
        $class = $palette[abs(crc32($room->type."\0".$igk)) % $n];

        if ($booking !== null) {
            $booking->loadMissing('roomLines');
            foreach ($booking->roomLines as $index => $line) {
                if ($room->type === $line->room_type && $igk === $line->inventory_group_key) {
                    $class = $palette[$index % $n];
                    break;
                }
            }
        }

        $name = e(trim((string) $room->name));
        $summary = e($room->typeDashBedSummary());

        return '<span class="block w-full min-w-0 text-left '.$class.'"><span class="font-medium text-gray-950 dark:text-white">'.$name.'</span> <span class="text-gray-600 dark:text-gray-300">('.$summary.')</span></span>';
    }

    /**
     * @param  array<int|string|null>  $roomIds
     * @return array<int>
     */
    private static function normalizeRoomIdList(array $roomIds): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $roomIds))));
    }

    /**
     * Keeps selection order but drops rooms beyond each billing line's quantity for the same room type + bed-spec group.
     *
     * @param  array<int|string|null>  $roomIds
     * @return array<int>
     */
    private static function clampAssignedRoomsToRoomLines(Booking $booking, array $roomIds): array
    {
        // No longer clamps to room lines - allows full flexibility in room selection
        return self::normalizeRoomIdList($roomIds);

        return $result;
    }

    private static function shouldShowAssignedRoomsField(Get $get, ?Booking $record): bool
    {
        if ($record instanceof Booking) {
            $record->loadMissing('roomLines');

            // Persisted room-line requirements take precedence over transient UI state.
            if ($record->expectsRoomAssignments()) {
                return true;
            }
        }

        if (self::isVenueOnlyBookingState($get)) {
            return false;
        }

        if (! $record instanceof Booking) {
            return true;
        }

        return ! ($record->expectsVenueAssignments() && ! $record->expectsRoomAssignments());
    }

    private static function isVenueOnlyBookingState(Get $get): bool
    {
        $bookingType = (string) ($get('booking_type') ?? '');
        if ($bookingType !== '') {
            return $bookingType === 'venue';
        }

        $routeRecord = request()->route('record');
        if ($routeRecord instanceof Booking) {
            if ($routeRecord->expectsRoomAssignments()) {
                return false;
            }

            if ($routeRecord->expectsVenueAssignments()) {
                return true;
            }
        }

        $roomIds = array_filter((array) ($get('rooms') ?? []));
        $venueIds = array_filter((array) ($get('venues') ?? []));
        if ($roomIds === [] && $venueIds !== []) {
            return true;
        }

        return false;
    }

    private static function shouldShowGuestBookingBillingSummary(?Booking $record): bool
    {
        if (! $record instanceof Booking) {
            return false;
        }

        $record->loadMissing(['roomLines', 'rooms.bedSpecifications', 'venues']);

        return $record->roomLines->isNotEmpty()
            || $record->rooms->isNotEmpty()
            || $record->venues->isNotEmpty();
    }

    private static function guestBookingBillingSummaryHtml(?Booking $record): ?HtmlString
    {
        if (! $record instanceof Booking) {
            return null;
        }

        $record->loadMissing(['roomLines', 'rooms.bedSpecifications', 'venues']);

        $items = [];
        foreach ($record->roomLines as $line) {
            $items[] = e($line->displayLabel()).' × '.(int) $line->quantity;
        }

        if ($record->roomLines->isEmpty()) {
            foreach ($record->rooms as $room) {
                $items[] = e($room->typeDashBedSummary()).' × 1';
            }
        }

        foreach ($record->venues as $venue) {
            $items[] = e((string) ($venue->name ?? 'Venue')).' × 1';
        }

        if ($items === []) {
            return null;
        }

        $html = '<ul class="list-disc ms-5 space-y-1 text-sm">';
        foreach ($items as $item) {
            $html .= '<li>'.$item.'</li>';
        }
        $html .= '</ul>';

        $stayCount = max(0, (int) ($record->no_of_days ?? 0));
        $totalPrice = max(0, (float) ($record->total_price ?? 0));
        if ($stayCount > 0) {
            $unitPrice = $totalPrice / $stayCount;
            $stayLabel = $stayCount === 1 ? 'night' : 'nights';
            $html .= '<p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200">'
                .e($stayCount.' '.$stayLabel)
                .' × ₱'.number_format($unitPrice, 2)
                .' = ₱'.number_format($totalPrice, 2)
                .'</p>';
        }

        if ($record->roomLines->isNotEmpty()) {
            $total = (int) $record->roomLines->sum('quantity');
            $html .= '<p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Assign exactly <strong>'.$total.'</strong> physical room(s) in “Assigned rooms” so each line matches.</p>';
        }

        return new HtmlString($html);
    }

    private static function hasBlockedCalendarDatesInInclusiveRange(Carbon $startDay, Carbon $endDay): bool
    {
        return BlockedDate::query()
            ->whereDate('blocked_dates.date', '>=', $startDay->toDateString())
            ->whereDate('blocked_dates.date', '<=', $endDay->toDateString())
            ->exists();
    }

    private static function shouldShowVenueEventTypeField(Get $get, ?Booking $record): bool
    {
        if (! empty(array_filter((array) ($get('venues') ?? [])))) {
            return true;
        }

        if (filled($get('venue_event_type'))) {
            return true;
        }

        return $record instanceof Booking && $record->expectsVenueAssignments();
    }

    /**
     * Apply fixed times based on booking type.
     * Room bookings: 12:00 PM check-in, 10:00 AM check-out
     * Venue bookings: 8:00 AM check-in, 12:00 AM (midnight) check-out
     */
    public static function applyFixedTimes(Get $get, Set $set): void
    {
        $bookingType = (string) ($get('booking_type') ?? 'rooms');
        
        if ($bookingType === 'venue') {
            // Venue-only: 8:00 AM check-in, 12:00 AM (midnight) check-out
            self::setTimeIfPresent($get, $set, 'check_in', 8, 0);
            self::setTimeIfPresent($get, $set, 'check_out', 0, 0);
        } else {
            // Room bookings (rooms, rooms_and_venues): 12:00 PM check-in, 10:00 AM check-out
            self::setTimeIfPresent($get, $set, 'check_in', 12, 0);
            self::setTimeIfPresent($get, $set, 'check_out', 10, 0);
        }
    }

    /**
     * Auto-set check-out date to next day when check-in is selected.
     * This improves UX by pre-selecting a reasonable default for check-out.
     */
    public static function autoSetCheckOut(Get $get, Set $set): void
    {
        $checkIn = $get('check_in');
        $checkOut = $get('check_out');
        
        // Only auto-set if check-in is filled but check-out is not yet set or needs updating
        if (! filled($checkIn)) {
            return;
        }
        
        try {
            $checkInDate = Carbon::parse((string) $checkIn);
            $bookingType = (string) ($get('booking_type') ?? 'rooms');
            
            // For venue-only bookings, same-day check-out is allowed
            if ($bookingType === 'venue') {
                $defaultCheckOut = $checkInDate->copy()->setTime(0, 0, 0);
            } else {
                // For room bookings, next day check-out
                $defaultCheckOut = $checkInDate->copy()->addDay()->setTime(10, 0, 0);
            }
            
            // Auto-set check-out if it's empty or if it's before the minimum required date
            if (! filled($checkOut)) {
                $set('check_out', $defaultCheckOut->toDateTimeString());
            } else {
                $checkOutDate = Carbon::parse((string) $checkOut);
                // If check-out would violate minimum requirements, auto-adjust it
                if ($bookingType !== 'venue' && $checkOutDate->lessThanOrEqualTo($checkInDate)) {
                    $set('check_out', $defaultCheckOut->toDateTimeString());
                }
            }
        } catch (\Exception $e) {
            // Silently ignore parsing errors
            return;
        }
    }

    /**
     * Set a specific time on a date field if the field is filled.
     * Used to enforce fixed times (e.g., 12:00 PM check-in, 10:00 AM check-out).
     */
    private static function setTimeIfPresent(Get $get, Set $set, string $field, int $hour, int $minute): void
    {
        $value = $get($field);
        if (! filled($value)) {
            return;
        }

        try {
            $parsed = Carbon::parse((string) $value);
            $target = $parsed->copy()->setTime($hour, $minute, 0);

            if (! $parsed->equalTo($target)) {
                $set($field, $target->toDateTimeString());
            }
        } catch (\Exception $e) {
            return;
        }
    }
}

