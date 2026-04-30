<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Filament\Forms\Components\PhAddressFields;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\Venue;
use App\Support\BookingPricing;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BookingCreateWizard
{
    /**
     * @return array<int, Step>
     */
    public static function steps(): array
    {
        return [
            Step::make('Accommodation')
                ->description('Pick dates, then select available room(s) for those dates.')
                ->schema([
                    Section::make()
                        ->columns(2)
                        ->schema([
                            Select::make('booking_type')
                                ->label('Booking type')
                                ->options([
                                    'rooms' => 'Rooms',
                                    'venue' => 'Venue',
                                    'rooms_and_venues' => 'Rooms + venue',
                                ])
                                ->default('rooms')
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                    if ($state === 'venue') {
                                        $set('rooms', []);
                                    }

                                    if ($state === 'rooms') {
                                        $set('venues', []);
                                        $set('venue_event_type', null);
                                    }

                                    // Apply appropriate fixed times based on new booking type
                                    self::applyFixedTimes($get, $set);
                                    BookingForm::updatePricing($get, $set);
                                })
                                ->columnSpanFull(),

                            DateTimePicker::make('check_in')
                                ->label('Check-in')
                                ->required()
                                ->default(fn () => now()->startOfDay()->addHours(12))
                                ->native(false)
                                ->live(onBlur: true)
                                ->seconds(false)
                                ->minDate(now()->startOfDay())
                                ->disabledDates(fn (Get $get): array => BookingForm::disabledCalendarDateStringsForWizard([]))
                                ->helperText('Check-in time is fixed at 12:00 PM for rooms. Blocked days show in red.')
                                ->rules([
                                    fn (Get $get) => self::roomAvailabilityRuleForCheckIn($get),
                                ])
                                ->afterStateUpdated(function (Get $get, Set $set): void {
                                    // Apply fixed times based on booking type
                                    self::applyFixedTimes($get, $set);
                                    
                                    // Auto-set check-out to next day with fixed time
                                    self::autoSetCheckOut($get, $set);
                                    
                                    BookingForm::updatePricing($get, $set);
                                }),

                            DateTimePicker::make('check_out')
                                ->label('Check-out')
                                ->required()
                                ->default(fn () => now()->startOfDay()->addDay()->addHours(10))
                                ->native(false)
                                ->live(onBlur: true)
                                ->seconds(false)
                                ->disabledDates(function (Get $get): array {
                                    $disabled = BookingForm::disabledCalendarDateStringsForWizard([]);

                                    $checkIn = $get('check_in');
                                    if (filled($checkIn) && ! self::bookingTypeIsVenueOnly($get)) {
                                        try {
                                            $disabled[] = Carbon::parse($checkIn)->format('Y-m-d');
                                        } catch (\Exception $e) {
                                            // ignore invalid date
                                        }
                                    }

                                    return array_values(array_unique($disabled));
                                })
                                ->helperText('Check-out time is fixed at 10:00 AM for rooms. Same blocked days as check-in.')
                                ->minDate(fn (Get $get) => filled($get('check_in'))
                                    ? (self::bookingTypeIsVenueOnly($get)
                                        ? Carbon::parse($get('check_in'))->startOfDay()
                                        : Carbon::parse($get('check_in'))->startOfDay()->addDay())
                                    : now())
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

                                        if (self::bookingTypeIsVenueOnly($get)) {
                                            if ($end->copy()->startOfDay()->lt($start->copy()->startOfDay())) {
                                                $fail('Check-out date cannot be before check-in date.');
                                            }

                                            return;
                                        }

                                        if ($end->lessThanOrEqualTo($start) || $end->isSameDay($start)) {
                                            $fail('Check-out must be at least the next day after check-in.');
                                        }
                                    },
                                    fn (Get $get) => self::roomAvailabilityRuleForCheckOut($get),
                                ])
                                ->afterStateUpdated(function (Get $get, Set $set): void {
                                    // Ensure check-out time remains fixed (don't allow it to be changed)
                                    self::applyFixedTimes($get, $set);
                                    BookingForm::updatePricing($get, $set);
                                }),

                            Select::make('rooms')
                                ->label('Rooms')
                                ->relationship(
                                    'rooms',
                                    'name',
                                    modifyQueryUsing: function ($query, ?string $search, ?Booking $record, Get $get): void {
                                        $checkIn = $get('check_in');
                                        $checkOut = $get('check_out');
                                        if (! $checkIn || ! $checkOut) {
                                            $query->whereRaw('0 = 1');

                                            return;
                                        }
                                        try {
                                            $start = Carbon::parse((string) $checkIn);
                                            $end = Carbon::parse((string) $checkOut);
                                        } catch (\Exception $e) {
                                            $query->whereRaw('0 = 1');

                                            return;
                                        }
                                        if ($end->lessThanOrEqualTo($start)) {
                                            $query->whereRaw('0 = 1');

                                            return;
                                        }

                                        $typeCol = $query->getModel()->qualifyColumn('type');
                                        $nameCol = $query->getModel()->qualifyColumn('name');
                                        $query->availableBetween($start, $end, null)
                                            ->with(['bedSpecifications'])
                                            ->orderBy($typeCol)
                                            ->orderBy($nameCol);
                                    },
                                )
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->required(fn (Get $get): bool => self::bookingTypeRequiresRooms($get))
                                ->visible(fn (Get $get): bool => self::bookingTypeUsesRooms($get))
                                ->live()
                                ->helperText('Rooms are filtered by availability for the selected dates.')
                                ->rules([
                                    fn (Get $get, ?Booking $record) => function (string $attribute, $value, $fail) use ($get, $record): void {
                                        if (! self::bookingTypeUsesRooms($get)) {
                                            return;
                                        }
                                        if (BookingForm::hasRoomConflicts($value, $get('check_in'), $get('check_out'), $record)) {
                                            $fail('One or more selected rooms are not available for the chosen dates.');
                                        }
                                    },
                                ])
                                ->afterStateUpdated(fn (Get $get, Set $set) => BookingForm::updatePricing($get, $set)),

                            Select::make('venues')
                                ->label('Venues')
                                ->relationship(
                                    'venues',
                                    'name',
                                    modifyQueryUsing: function ($query, ?string $search, ?Booking $record, Get $get): void {
                                        BookingForm::constrainAvailableVenuesQuery($query, $get, $record);
                                    },
                                )
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->live()
                                ->visible(fn (Get $get): bool => self::bookingTypeUsesVenues($get))
                                ->required(fn (Get $get): bool => self::bookingTypeRequiresVenues($get))
                                ->helperText('Optional for rooms-only bookings. Uses the same date-range availability checks.')
                                ->rules([
                                    fn (Get $get, ?Booking $record) => function (string $attribute, $value, $fail) use ($get, $record): void {
                                        if (! self::bookingTypeUsesVenues($get)) {
                                            return;
                                        }
                                        if (BookingForm::hasVenueConflicts(
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
                                ->afterStateUpdated(function (Get $get, Set $set): void {
                                    // If venues were cleared, reset venue subtotal so stale manual totals don't linger.
                                    $venueIds = array_filter((array) ($get('venues') ?? []));
                                    if ($venueIds === []) {
                                        $set('venue_subtotal', 0);
                                    }

                                    BookingForm::updatePricing($get, $set);
                                }),

                            Radio::make('venue_event_type')
                                ->label('Venue event type')
                                ->options(BookingPricing::venueEventTypeOptions())
                                ->default(BookingPricing::VENUE_EVENT_WEDDING)
                                ->visible(fn (Get $get): bool => self::bookingTypeUsesVenues($get)
                                    && ! empty(array_filter((array) ($get('venues') ?? []))))
                                ->live()
                                ->afterStateUpdated(fn (Get $get, Set $set) => BookingForm::updatePricing($get, $set))
                                ->columnSpanFull(),

                            TextInput::make('no_of_days')
                                ->label(fn (Get $get): string => self::stayUnitLabel($get))
                                ->numeric()
                                ->suffix(fn (Get $get): string => self::stayUnitSuffix($get))
                                ->readOnly()
                                ->dehydrated(),

                            TextInput::make('rooms_subtotal')
                                ->label('Room total (per night)')
                                ->default(0)
                                ->readOnly()
                                ->dehydrated(false)
                                ->numeric()
                                ->prefix('₱')
                                ->visible(fn (Get $get): bool => self::bookingTypeUsesRooms($get)),

                            TextInput::make('venue_subtotal')
                                ->label('Venue total (per day)')
                                ->default(0)
                                ->readOnly(function (Get $get): bool {
                                    $venues = array_filter((array) ($get('venues') ?? []));
                                    if ($venues === []) {
                                        return true;
                                    }

                                    $venueEventType = BookingPricing::normalizeVenueEventType(
                                        is_string($get('venue_event_type')) ? $get('venue_event_type') : null
                                    );

                                    return $venueEventType !== BookingPricing::VENUE_EVENT_OTHERS;
                                })
                                ->dehydrated(false)
                                ->numeric()
                                ->prefix('₱')
                                ->minValue(0)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => BookingForm::updatePricing($get, $set))
                                ->visible(fn (Get $get): bool => self::bookingTypeUsesVenues($get)
                                    && ! empty(array_filter((array) ($get('venues') ?? [])))),

                            TextInput::make('total_price')
                                ->label(fn (Get $get): string => self::totalEstimateLabel($get))
                                ->default(0)
                                ->readOnly()
                                ->dehydrated()
                                ->numeric()
                                ->prefix('₱')
                                ->minValue(0)
                                ->helperText(function (Get $get): string {
                                    $bookingType = (string) ($get('booking_type') ?? 'rooms');
                                    $venueEventType = BookingPricing::normalizeVenueEventType(
                                        is_string($get('venue_event_type')) ? $get('venue_event_type') : null
                                    );

                                    if (in_array($bookingType, ['venue', 'rooms_and_venues'], true) && $venueEventType === BookingPricing::VENUE_EVENT_OTHERS) {
                                        return 'Total is computed from fixed room rates and your editable Venue total (Others). Additional payment step records what the guest pays now.';
                                    }

                                    return 'Auto-calculated from selected rooms/venues and nights/days. Additional payment step records what the guest pays now.';
                                })
                                ->columnSpanFull(),
                        ]),
                ]),
            Step::make('Guest details')
                ->description('Create the guest profile for this booking.')
                ->schema([
                    Section::make()
                        ->columns(2)
                        ->schema([
                            Hidden::make('booking_source')
                                ->default('manual')
                                ->dehydrated(),
                            Hidden::make('is_manual_booking')
                                ->default(true)
                                ->dehydrated(),
                            Hidden::make('email_is_shared')
                                ->default(false)
                                ->dehydrated(),
                            Hidden::make('existing_guest_found')
                                ->default(false)
                                ->dehydrated(false),
                            Hidden::make('existing_guest_id')
                                ->default(null)
                                ->dehydrated(),
                            Hidden::make('email_has_multiple_matches')
                                ->default(false)
                                ->dehydrated(false),
                            Hidden::make('allow_manual_email_match')
                                ->default(false)
                                ->dehydrated(),
                            Toggle::make('edit_returning_guest')
                                ->label('Edit guest details for this booking')
                                ->helperText('Enable to update the guest profile now. Leave off to keep it read-only.')
                                ->default(false)
                                ->live()
                                ->visible(fn (Get $get): bool => $get('guest_status') === 'returning' && (bool) $get('existing_guest_found'))
                                ->afterStateUpdated(function (Set $set, $state): void {
                                    if (! $state) {
                                        return;
                                    }

                                    // When enabling edit mode, ensure we keep returning guest selection flow.
                                    $set('allow_manual_email_match', false);
                                })
                                ->columnSpanFull(),
                    Radio::make('guest_status')
                        ->label('Guest status')
                        ->options([
                            'new' => 'New guest (walk-in / first time)',
                            'returning' => 'Returning guest (booked before)',
                        ])
                        ->default('new')
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            if ($state !== 'returning') {
                                $set('existing_guest_found', false);
                                $set('existing_guest_id', null);
                                $set('email_has_multiple_matches', false);
                                $set('allow_manual_email_match', false);
                            }
                        })
                        ->columnSpanFull(),
                    Text::make(fn (Get $get): string => $get('guest_status') === 'returning'
                        ? 'Returning guest: enter the previous email. If we find a match, details will auto-fill and lock.'
                        : 'New guest: fill out the details below.')
                        ->color(fn (Get $get) => $get('guest_status') === 'returning' ? 'primary' : 'neutral')
                        ->columnSpanFull(),
                    TextInput::make('first_name')
                        ->required(fn (Get $get): bool => $get('guest_status') !== 'returning')
                        ->live(onBlur: true)
                        ->extraInputAttributes(['class' => 'uppercase'])
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('first_name', Str::upper(trim((string) $state))))
                        ->dehydrateStateUsing(fn (?string $state): string => Str::upper(trim((string) $state)))
                        ->disabled(fn (Get $get): bool => $get('guest_status') === 'returning' && ! (bool) $get('edit_returning_guest'))
                        ->visible(fn (Get $get): bool => $get('guest_status') !== 'returning' || (bool) $get('existing_guest_found'))
                        ->maxLength(100),
                    TextInput::make('middle_name')
                        ->live(onBlur: true)
                        ->extraInputAttributes(['class' => 'uppercase'])
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('middle_name', Str::upper(trim((string) $state))))
                        ->dehydrateStateUsing(fn (?string $state): string => Str::upper(trim((string) $state)))
                        ->disabled(fn (Get $get): bool => $get('guest_status') === 'returning' && ! (bool) $get('edit_returning_guest'))
                        ->visible(fn (Get $get): bool => $get('guest_status') !== 'returning' || (bool) $get('existing_guest_found'))
                        ->maxLength(100),
                    TextInput::make('last_name')
                        ->required(fn (Get $get): bool => $get('guest_status') !== 'returning')
                        ->live(onBlur: true)
                        ->extraInputAttributes(['class' => 'uppercase'])
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('last_name', Str::upper(trim((string) $state))))
                        ->dehydrateStateUsing(fn (?string $state): string => Str::upper(trim((string) $state)))
                        ->disabled(fn (Get $get): bool => $get('guest_status') === 'returning' && ! (bool) $get('edit_returning_guest'))
                        ->visible(fn (Get $get): bool => $get('guest_status') !== 'returning' || (bool) $get('existing_guest_found'))
                        ->maxLength(100),
                    Select::make('gender')
                        ->options(Guest::genderOptions())
                        ->required(fn (Get $get): bool => $get('guest_status') !== 'returning')
                        ->disabled(fn (Get $get): bool => $get('guest_status') === 'returning' && ! (bool) $get('edit_returning_guest'))
                        ->visible(fn (Get $get): bool => $get('guest_status') !== 'returning' || (bool) $get('existing_guest_found'))
                        ->native(false),
                    TextInput::make('contact_num')
                        ->label('Phone number')
                        ->required(fn (Get $get): bool => $get('guest_status') !== 'returning' && ! ((bool) $get('is_international')))
                        ->disabled(fn (Get $get): bool => $get('guest_status') === 'returning' && ! (bool) $get('edit_returning_guest'))
                        ->visible(fn (Get $get): bool => $get('guest_status') !== 'returning' || (bool) $get('existing_guest_found'))
                        ->maxLength(20),
                    TextInput::make('email')
                        ->label(fn (Get $get): string => $get('guest_status') === 'returning' ? 'Email used before' : 'Email')
                        ->email()
                        ->required()
                        ->live(onBlur: true)
                        ->disabled(fn (Get $get): bool => $get('guest_status') === 'returning'
                            && (bool) $get('existing_guest_found')
                            && ! (bool) $get('edit_returning_guest'))
                        ->helperText(function (Get $get): string {
                            if ($get('guest_status') !== 'returning') {
                                return 'For guests without email, you may use a placeholder email (e.g. resort email).';
                            }

                            if ((bool) $get('email_is_shared')) {
                                return 'Shared/placeholder email detected. Select the guest below (same email can belong to many walk-ins).';
                            }

                            return (bool) $get('existing_guest_found')
                                ? 'Guest found. Details were auto-filled.'
                                : 'Enter the email they used before, then click outside the field. If found, details will auto-fill.';
                        })
                        ->rules([
                            fn (Get $get) => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                if ($get('guest_status') !== 'returning') {
                                    return;
                                }
                                if (! (bool) $get('email_is_shared') && ! (bool) $get('existing_guest_found')) {
                                    $fail('No existing guest found for this email. Switch to New guest or use the correct previous email.');
                                }
                            },
                        ])
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                            $isShared = self::matchesSharedEmailPattern((string) $state);
                            $set('email_is_shared', $isShared);

                            $isReturning = $get('guest_status') === 'returning';
                            if (! $isReturning || $isShared) {
                                $set('existing_guest_found', false);
                                $set('existing_guest_id', null);
                                $set('email_has_multiple_matches', false);
                                $set('allow_manual_email_match', false);

                                return;
                            }

                            $candidates = self::findGuestsByEmail((string) $state);
                            if ($candidates->isEmpty()) {
                                $set('existing_guest_found', false);
                                $set('existing_guest_id', null);
                                $set('email_has_multiple_matches', false);
                                $set('allow_manual_email_match', false);

                                return;
                            }

                            if ($candidates->count() > 1) {
                                // Multiple guests share the same email — force explicit selection.
                                $set('existing_guest_found', false);
                                $set('existing_guest_id', null);
                                $set('email_has_multiple_matches', true);
                                $set('allow_manual_email_match', false);

                                return;
                            }

                            $guest = $candidates->first();
                            if (! $guest) {
                                $set('existing_guest_found', false);
                                $set('existing_guest_id', null);
                                $set('email_has_multiple_matches', false);
                                $set('allow_manual_email_match', false);

                                return;
                            }

                            $set('existing_guest_found', true);
                            $set('existing_guest_id', $guest->id);
                            $set('email_has_multiple_matches', false);
                            $set('allow_manual_email_match', true);
                            $set('first_name', (string) $guest->first_name);
                            $set('middle_name', (string) ($guest->middle_name ?? ''));
                            $set('last_name', (string) $guest->last_name);
                            $set('contact_num', (string) ($guest->contact_num ?? ''));
                            $set('gender', (string) ($guest->gender ?? Guest::GENDER_OTHER));
                            $set('is_international', (bool) ($guest->is_international ?? false));
                            $set('country', (string) ($guest->country ?? 'Philippines'));
                            $set('region', $guest->is_international ? null : ($guest->region ?? null));
                            $set('province', $guest->is_international ? null : ($guest->province ?? null));
                            $set('municipality', $guest->is_international ? null : ($guest->municipality ?? null));
                            $set('barangay', $guest->is_international ? null : ($guest->barangay ?? null));
                        })
                        ->maxLength(255),
                    Select::make('existing_guest_id_picker')
                        ->label('Select guest (shared email)')
                        ->visible(fn (Get $get): bool => $get('guest_status') === 'returning'
                            && ((bool) $get('email_is_shared') || (bool) $get('email_has_multiple_matches'))
                            && filled($get('email')))
                        ->options(function (Get $get): array {
                            $email = strtolower(trim((string) ($get('email') ?? '')));
                            if ($email === '') {
                                return [];
                            }

                            return Guest::query()
                                ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                                ->withCount('bookings')
                                ->withMax('bookings', 'created_at')
                                ->orderByDesc('bookings_max_created_at')
                                ->orderByDesc('bookings_count')
                                ->orderBy('last_name')
                                ->orderBy('first_name')
                                ->get(['id', 'first_name', 'middle_name', 'last_name', 'contact_num'])
                                ->mapWithKeys(function (Guest $g): array {
                                    $phone = trim((string) ($g->contact_num ?? ''));
                                    $count = (int) ($g->bookings_count ?? 0);
                                    $label = $g->full_name
                                        .($phone !== '' ? " · {$phone}" : '')
                                        ." · {$count} booking".($count === 1 ? '' : 's');

                                    return [$g->id => $label];
                                })
                                ->all();
                        })
                        ->searchable()
                        ->live()
                        ->required(fn (Get $get): bool => $get('guest_status') === 'returning'
                            && ((bool) $get('email_is_shared') || (bool) $get('email_has_multiple_matches')))
                        ->afterStateUpdated(function (Set $set, $state): void {
                            $id = is_numeric($state) ? (int) $state : null;
                            if (! $id) {
                                $set('existing_guest_found', false);
                                $set('existing_guest_id', null);
                                $set('email_has_multiple_matches', true);

                                return;
                            }

                            $guest = Guest::query()->find($id);
                            if (! $guest) {
                                $set('existing_guest_found', false);
                                $set('existing_guest_id', null);

                                return;
                            }

                            $set('existing_guest_found', true);
                            $set('existing_guest_id', $guest->id);
                            // Shared email: never use email-based merge; we reuse by explicit selection.
                            $set('allow_manual_email_match', false);
                            $set('email_has_multiple_matches', false);
                            $set('first_name', (string) $guest->first_name);
                            $set('middle_name', (string) ($guest->middle_name ?? ''));
                            $set('last_name', (string) $guest->last_name);
                            $set('contact_num', (string) ($guest->contact_num ?? ''));
                            $set('gender', (string) ($guest->gender ?? Guest::GENDER_OTHER));
                            $set('is_international', (bool) ($guest->is_international ?? false));
                            $set('country', (string) ($guest->country ?? 'Philippines'));
                            $set('region', $guest->is_international ? null : ($guest->region ?? null));
                            $set('province', $guest->is_international ? null : ($guest->province ?? null));
                            $set('municipality', $guest->is_international ? null : ($guest->municipality ?? null));
                            $set('barangay', $guest->is_international ? null : ($guest->barangay ?? null));
                        })
                        ->columnSpanFull(),
                    Toggle::make('is_international')
                        ->label('Foreign / international address')
                        ->default(false)
                        ->live()
                        ->disabled(fn (Get $get): bool => $get('guest_status') === 'returning' && ! (bool) $get('edit_returning_guest'))
                        ->visible(fn (Get $get): bool => $get('guest_status') !== 'returning' || (bool) $get('existing_guest_found'))
                        ->afterStateUpdated(function (Set $set, $state): void {
                            if ($state) {
                                $set('ph_region_code', null);
                                $set('ph_province_code', null);
                                $set('ph_municipality_code', null);
                                $set('ph_barangay_code', null);
                                $set('region', null);
                                $set('province', null);
                                $set('municipality', null);
                                $set('barangay', null);
                            } else {
                                $set('country', 'Philippines');
                            }
                        }),
                            TextInput::make('country')
                                ->default('Philippines')
                                ->maxLength(100)
                                ->required(fn (Get $get) => (bool) $get('is_international'))
                                ->visible(fn (Get $get) => (bool) $get('is_international'))
                                ->disabled(fn (Get $get): bool => $get('guest_status') === 'returning' && ! (bool) $get('edit_returning_guest'))
                                ->rules([
                                    fn (Get $get) => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                        if (! ((bool) $get('is_international'))) {
                                            return;
                                        }

                                        $country = trim((string) $value);
                                        if ($country !== '' && strcasecmp($country, 'Philippines') === 0) {
                                            $fail('Foreign guests cannot use Philippines as country.');
                                        }
                                    },
                                ]),
                            ...PhAddressFields::make(),
                        ]),
                ]),
            Step::make('Review')
                ->description('Confirm stay and guest details before payment. Use the step tabs above to go back and edit.')
                ->schema([
                    Section::make('Review summary')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->iconColor('primary')
                        ->description('Check dates, rooms, and guest contact — this is what you are about to book.')
                        ->schema([
                            Text::make('Please confirm the details below')
                                ->weight(FontWeight::Bold)
                                ->size(TextSize::Large)
                                ->color('primary'),
                            Section::make('Stay')
                                ->icon('heroicon-o-home')
                                ->iconColor('primary')
                                ->compact()
                                ->schema([
                                    Text::make(fn (Get $get): string => self::formatCheckInOut($get))
                                        ->weight(FontWeight::SemiBold)
                                        ->size(TextSize::Medium),
                                    Text::make(fn (Get $get): string => self::formatRoomsLine($get))
                                        ->visible(fn (Get $get): bool => self::bookingTypeUsesRooms($get)),
                                    Text::make(fn (Get $get): string => self::formatVenuesLine($get))
                                        ->visible(fn (Get $get): bool => self::bookingTypeUsesVenues($get)),
                                    Text::make(fn (Get $get): string => self::formatNightsAndTotal($get))
                                        ->weight(FontWeight::SemiBold),
                                ]),
                            Section::make('Guest')
                                ->icon('heroicon-o-user')
                                ->iconColor('primary')
                                ->compact()
                                ->schema([
                                    Text::make(fn (Get $get): string => self::formatGuestName($get))
                                        ->weight(FontWeight::SemiBold)
                                        ->size(TextSize::Medium),
                                    Text::make(function (Get $get): string {
                                        $g = (string) $get('gender');

                                        return 'Gender: '.(Guest::genderOptions()[$g] ?? '—');
                                    }),
                                    Text::make(fn (Get $get): string => 'Phone: '.($get('contact_num') ?: '—')),
                                    Text::make(fn (Get $get): string => 'Email: '.($get('email') ?: '—')),
                                    Text::make(fn (Get $get): string => self::formatAddress($get)),
                                ]),
                        ]),
                ]),
            Step::make('Payment')
                ->description('Record what the guest pays now: full balance or any custom amount.')
                ->schema([
                    Section::make()
                        ->columns(2)
                        ->schema([
                            Radio::make('admin_payment_mode')
                                ->label('Payment')
                                ->options([
                                    'full' => 'Pay full amount (matches booking total)',
                                    'custom' => 'Custom amount (partial deposit or other)',
                                ])
                                ->default('full')
                                ->live()
                                ->required()
                                ->columnSpanFull(),
                            TextInput::make('admin_payment_amount')
                                ->label('Amount to record')
                                ->helperText('Only when using a custom amount. Whole pesos; cannot exceed the booking total.')
                                ->numeric()
                                ->prefix('₱')
                                ->minValue(0)
                                ->maxValue(fn (Get $get) => max(0, (int) ceil((float) ($get('total_price') ?? 0))))
                                ->visible(fn (Get $get) => $get('admin_payment_mode') === 'custom')
                                ->required(fn (Get $get) => $get('admin_payment_mode') === 'custom')
                                ->dehydrated(fn (Get $get) => $get('admin_payment_mode') === 'custom'),
                        ]),
                ]),
            Step::make('Confirmation')
                ->description('Everything below will be saved when you create the booking.')
                ->schema([
                    Text::make(fn (Get $get): string => 'Stay: '.trim(self::formatCheckInOut($get).' · '.self::formatAccommodationLine($get).' · '.self::formatNightsAndTotal($get)))
                        ->weight(FontWeight::Bold),
                    Text::make(fn (Get $get): string => 'Guest: '.self::formatGuestSummary($get)),
                    Text::make(fn (Get $get): string => 'Payment to record now: '.self::formatPaymentLine($get))
                        ->weight(FontWeight::SemiBold),
                    Text::make('When you are satisfied, click Create below to finalize. The guest receives the usual booking email when the address is valid.')
                        ->color('neutral'),
                ]),
        ];
    }

    private static function formatCheckInOut(Get $get): string
    {
        $in = $get('check_in');
        $out = $get('check_out');
        try {
            $inF = $in ? Carbon::parse($in)->format('M j, Y g:i A') : '—';
            $outF = $out ? Carbon::parse($out)->format('M j, Y g:i A') : '—';
        } catch (\Exception $e) {
            return 'Check-in / check-out: —';
        }

        return "Check-in: {$inF} → Check-out: {$outF}";
    }

    private static function formatRoomsLine(Get $get): string
    {
        $ids = $get('rooms') ?? [];
        $ids = is_array($ids) ? array_filter($ids) : [];
        if ($ids === []) {
            return 'Rooms: —';
        }
        $names = Room::query()->whereIn('id', $ids)->pluck('name')->sort()->values()->all();

        return 'Rooms: '.(empty($names) ? '—' : implode(', ', $names));
    }

    private static function formatVenuesLine(Get $get): string
    {
        $ids = $get('venues') ?? [];
        $ids = is_array($ids) ? array_filter($ids) : [];
        if ($ids === []) {
            return 'Venues: —';
        }
        $names = Venue::query()->whereIn('id', $ids)->pluck('name')->sort()->values()->all();

        return 'Venues: '.(empty($names) ? '—' : implode(', ', $names));
    }

    private static function formatAccommodationLine(Get $get): string
    {
        $parts = [];
        $roomIds = $get('rooms') ?? [];
        $venueIds = $get('venues') ?? [];
        $roomIds = is_array($roomIds) ? array_filter($roomIds) : [];
        $venueIds = is_array($venueIds) ? array_filter($venueIds) : [];

        if ($roomIds !== []) {
            $parts[] = self::formatRoomsLine($get);
        }
        if ($venueIds !== []) {
            $parts[] = self::formatVenuesLine($get);
        }

        if ($parts === []) {
            return 'Rooms/Venues: —';
        }

        return implode(' · ', $parts);
    }

    private static function formatNightsAndTotal(Get $get): string
    {
        $nights = (int) ($get('no_of_days') ?? 0);
        $total = number_format((float) ($get('total_price') ?? 0), 2);
        $label = self::stayUnitLabel($get);

        return "{$label}: {$nights} · Total: ₱{$total}";
    }

    private static function formatGuestName(Get $get): string
    {
        $first = trim((string) $get('first_name'));
        $middle = trim((string) $get('middle_name'));
        $last = trim((string) $get('last_name'));
        $mid = $middle !== '' ? " {$middle} " : ' ';

        return "Name: {$first}{$mid}{$last}";
    }

    private static function formatAddress(Get $get): string
    {
        if ($get('is_international')) {
            $country = $get('country') ?: '—';

            return "Address (international): {$country}";
        }

        $parts = array_filter([
            $get('region'),
            $get('province'),
            $get('municipality'),
            $get('barangay'),
        ]);

        return 'Address: '.($parts === [] ? '—' : implode(' · ', $parts));
    }

    private static function formatGuestSummary(Get $get): string
    {
        return trim(self::formatGuestName($get).' · '.self::formatAddress($get).' · '.($get('email') ?: '—'));
    }

    private static function formatPaymentLine(Get $get): string
    {
        $total = (float) ($get('total_price') ?? 0);
        $mode = $get('admin_payment_mode');
        if ($mode === 'custom') {
            $amt = (float) ($get('admin_payment_amount') ?? 0);
        } else {
            $amt = $total;
        }

        $amtStr = number_format(max(0, $amt), 2);
        $totalStr = number_format($total, 2);

        return "₱{$amtStr}".($mode === 'full' ? " (full balance of ₱{$totalStr})" : " (custom; booking total ₱{$totalStr})");
    }

    private static function stayUnitLabel(Get $get): string
    {
        return self::bookingTypeIsVenueOnly($get) ? 'Days' : 'Nights';
    }

    private static function stayUnitSuffix(Get $get): string
    {
        return self::bookingTypeIsVenueOnly($get) ? 'days' : 'nights';
    }

    private static function bookingTypeUsesRooms(Get $get): bool
    {
        $type = (string) ($get('booking_type') ?? 'rooms');

        return in_array($type, ['rooms', 'rooms_and_venues'], true);
    }

    private static function bookingTypeRequiresRooms(Get $get): bool
    {
        $type = (string) ($get('booking_type') ?? 'rooms');

        return $type === 'rooms' || $type === 'rooms_and_venues';
    }

    private static function bookingTypeUsesVenues(Get $get): bool
    {
        $type = (string) ($get('booking_type') ?? 'rooms');

        return in_array($type, ['venue', 'rooms_and_venues'], true);
    }

    private static function bookingTypeRequiresVenues(Get $get): bool
    {
        $type = (string) ($get('booking_type') ?? 'rooms');

        return $type === 'venue' || $type === 'rooms_and_venues';
    }

    private static function bookingTypeIsVenueOnly(Get $get): bool
    {
        return (string) ($get('booking_type') ?? 'rooms') === 'venue';
    }

    private static function totalEstimateLabel(Get $get): string
    {
        $type = (string) ($get('booking_type') ?? 'rooms');

        return match ($type) {
            'venue' => 'Venue total (estimated)',
            'rooms_and_venues' => 'Accommodation total (estimated)',
            default => 'Room total (estimated)',
        };
    }

    private static function applyFixedTimes(Get $get, Set $set): void
    {
        $bookingType = (string) ($get('booking_type') ?? 'rooms');
        
        // Apply fixed times based on booking type
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
    private static function autoSetCheckOut(Get $get, Set $set): void
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

    /**
     * Re-validate room conflicts when check-in changes (rooms rule alone does not re-run).
     *
     * @return Closure(string, mixed, Closure): void
     */
    private static function roomAvailabilityRuleForCheckIn(Get $get): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($get): void {
            if (! $value || ! $get('check_out')) {
                return;
            }
            if (BookingForm::hasRoomConflicts($get('rooms'), $value, $get('check_out'), null)) {
                $fail('Selected room(s) are not available for these dates (another booking or block overlaps).');
            }
        };
    }

    /**
     * @return Closure(string, mixed, Closure): void
     */
    private static function roomAvailabilityRuleForCheckOut(Get $get): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($get): void {
            if (! $get('check_in') || ! $value) {
                return;
            }
            if (BookingForm::hasRoomConflicts($get('rooms'), $get('check_in'), $value, null)) {
                $fail('Selected room(s) are not available for these dates (another booking or block overlaps).');
            }
        };
    }

    private static function matchesSharedEmailPattern(string $email): bool
    {
        $normalizedEmail = strtolower(trim($email));
        if ($normalizedEmail === '') {
            return false;
        }

        $patternsRaw = trim((string) env('GUEST_SHARED_EMAIL_PATTERNS', ''));
        if ($patternsRaw === '') {
            return false;
        }

        $patterns = array_values(array_filter(array_map(
            static fn (string $item): string => strtolower(trim($item)),
            explode(',', $patternsRaw)
        )));

        foreach ($patterns as $pattern) {
            if ($pattern === '') {
                continue;
            }

            if (str_starts_with($pattern, '@')) {
                if (str_ends_with($normalizedEmail, $pattern)) {
                    return true;
                }

                continue;
            }

            if ($normalizedEmail === $pattern) {
                return true;
            }
        }

        return false;
    }

    private static function findGuestByEmail(string $email): ?Guest
    {
        $normalizedEmail = strtolower(trim($email));
        if ($normalizedEmail === '') {
            return null;
        }

        return Guest::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Guest>
     */
    private static function findGuestsByEmail(string $email)
    {
        $normalizedEmail = strtolower(trim($email));
        if ($normalizedEmail === '') {
            return collect();
        }

        return Guest::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->get();
    }
}
