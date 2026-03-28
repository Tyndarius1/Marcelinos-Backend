<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\Venue;
use App\Support\BookingPricing;
use App\Support\RoomInventoryGroupKey;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class BookingForm
{
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
            Select::make('guest_id')
                ->label('Guest')
                ->relationship('guest', 'first_name')
                ->getOptionLabelFromRecordUsing(fn (Guest $record) => $record->full_name)
                ->searchable()
                ->preload()
                ->required(),

            Section::make('Guest booking (billing summary)')
                ->description(function (?Booking $record): ?HtmlString {
                    if (! $record || $record->roomLines->isEmpty()) {
                        return null;
                    }
                    $record->loadMissing('roomLines');
                    $html = '<ul class="list-disc ms-5 space-y-1 text-sm">';
                    foreach ($record->roomLines as $line) {
                        $html .= '<li>'.e($line->displayLabel()).' × '.(int) $line->quantity.'</li>';
                    }
                    $total = (int) $record->roomLines->sum('quantity');
                    $html .= '</ul>';
                    $html .= '<p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Assign exactly <strong>'.$total.'</strong> physical room(s) under “Assigned rooms” so each line matches. You cannot save until this matches the guest request.</p>';

                    return new HtmlString($html);
                })
                ->visible(fn (?Booking $record) => $record?->roomLines?->isNotEmpty())
                ->schema([
                    Hidden::make('guest_booking_section_placeholder')->dehydrated(false),
                ]),

            Select::make('rooms')
                ->label('Assigned rooms')
                ->relationship(
                    'rooms',
                    'name',
                    modifyQueryUsing: function ($query, ?string $search, ?Booking $record = null): void {
                        $booking = $record ?? (request()->route('record') instanceof Booking ? request()->route('record') : null);
                        $roomTableKey = $query->getModel()->getQualifiedKeyName();
                        $roomStatusCol = $query->getModel()->qualifyColumn('status');
                        if ($booking instanceof Booking) {
                            $eligible = Room::idsEligibleForBookingAssignment($booking);
                            if ($eligible !== null) {
                                if ($eligible === []) {
                                    $query->whereRaw('0 = 1');
                                } else {
                                    $query->whereIn($roomTableKey, $eligible);
                                }
                                $query->with(['bedSpecifications', 'bedModifiers']);
                                $typeCol = $query->getModel()->qualifyColumn('type');
                                $nameCol = $query->getModel()->qualifyColumn('name');
                                $query->orderBy($typeCol)->orderBy($nameCol);

                                return;
                            }
                        }
                        $query->where($roomStatusCol, '!=', Room::STATUS_MAINTENANCE)
                            ->with(['bedSpecifications', 'bedModifiers']);
                        $typeCol = $query->getModel()->qualifyColumn('type');
                        $nameCol = $query->getModel()->qualifyColumn('name');
                        $query->orderBy($typeCol)->orderBy($nameCol);
                    },
                )
                ->multiple()
                ->searchable()
                ->preload()
                ->allowHtml()
                ->getOptionLabelFromRecordUsing(function (Room $record): string {
                    $booking = request()->route('record');
                    $booking = $booking instanceof Booking ? $booking : null;

                    return self::assignedRoomOptionHtml($record, $booking);
                })
                ->live()
                ->maxItems(function (?Booking $record): ?int {
                    if (! $record instanceof Booking) {
                        return null;
                    }
                    $record->loadMissing('roomLines');
                    if ($record->roomLines->isEmpty()) {
                        return null;
                    }
                    $total = (int) $record->roomLines->sum('quantity');

                    return $total > 0 ? $total : null;
                })
                ->helperText('Options are color-coded by room type + bed specification (same colors as the billing summary lines when this booking has room lines). You can pick at most as many rooms as billing lines require; extra picks for the same type/bed group are dropped. Totals update when you select rooms.')
                ->rules([
                    fn (Get $get, ?Booking $record) => function (string $attribute, $value, $fail) use ($get, $record): void {
                        $roomIds = array_filter((array) ($get('rooms') ?? []));
                        $venueIds = array_filter((array) ($get('venues') ?? []));
                        if ($roomIds === [] && $venueIds === []) {
                            $fail('Select at least one room or one venue.');
                        }
                    },
                    fn (Get $get, ?Booking $record) => function (string $attribute, $value, $fail) use ($get, $record): void {
                        if (self::hasRoomConflicts($value, $get('check_in'), $get('check_out'), $record)) {
                            $fail('One or more selected rooms are not available for the chosen dates.');
                        }
                    },
                    fn (Get $get, ?Booking $record) => function (string $attribute, $value, $fail) use ($record): void {
                        if (! $record instanceof Booking) {
                            return;
                        }
                        try {
                            Booking::validateAssignedRoomsFulfillRoomLines($record, is_array($value) ? $value : []);
                        } catch (ValidationException $e) {
                            $msg = $e->errors()['rooms'][0] ?? $e->getMessage();
                            $fail($msg);
                        }
                    },
                ])
                ->afterStateUpdated(function (Get $get, Set $set): void {
                    $routeRecord = request()->route('record');
                    $booking = $routeRecord instanceof Booking ? $routeRecord : null;
                    $rooms = $get('rooms');
                    $roomIds = is_array($rooms) ? $rooms : [];
                    if ($booking instanceof Booking) {
                        $clamped = self::clampAssignedRoomsToRoomLines($booking, $roomIds);
                        $before = self::normalizeRoomIdList($roomIds);
                        if ($clamped !== $before) {
                            $set('rooms', $clamped);
                        }
                    }
                    self::updatePricing($get, $set);
                }),

            Select::make('venues')
                ->label('Venues')
                ->relationship('venues', 'name')
                ->multiple()
                ->searchable()
                ->preload()
                ->live()
                ->helperText('Optional. Venues are validated against the same date range.')
                ->rules([
                    fn (Get $get) => function (string $attribute, $value, $fail) use ($get): void {
                        $roomIds = array_filter((array) ($get('rooms') ?? []));
                        $venueIds = array_filter((array) ($get('venues') ?? []));
                        if ($roomIds === [] && $venueIds === []) {
                            $fail('Select at least one room or one venue.');
                        }
                    },
                    fn (Get $get, ?Booking $record) => function (string $attribute, $value, $fail) use ($get, $record): void {
                        if (self::hasVenueConflicts($value, $get('check_in'), $get('check_out'), $record)) {
                            $fail('One or more selected venues are not available for the chosen dates.');
                        }
                    },
                ])
                ->afterStateUpdated(fn (Get $get, Set $set) => self::updatePricing($get, $set)),

            Radio::make('venue_event_type')
                ->label('Venue event type')
                ->options(BookingPricing::venueEventTypeOptions())
                ->default(BookingPricing::VENUE_EVENT_WEDDING)
                ->visible(fn (Get $get) => ! empty(array_filter((array) ($get('venues') ?? []))))
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::updatePricing($get, $set)),

            DateTimePicker::make('check_in')
                ->required()
                ->native(false)
                ->live()
                ->seconds(false)
                ->helperText('Check-in date & time. Used for availability and pricing.')
                ->afterStateUpdated(fn (Get $get, Set $set) => self::updatePricing($get, $set)),

            DateTimePicker::make('check_out')
                ->required()
                ->native(false)
                ->live()
                ->seconds(false)
                ->minDate(fn (Get $get) => filled($get('check_in')) ? Carbon::parse($get('check_in'))->addMinute() : null)
                ->helperText('Must be after check-in.')
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

                        if ($end->lessThanOrEqualTo($start)) {
                            $fail('Check-out must be after check-in.');
                        }
                    },
                ])
                ->afterStateUpdated(fn (Get $get, Set $set) => self::updatePricing($get, $set)),

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
                ->helperText('Auto-calculated from selected rooms/venues × nights.'),

            Radio::make('status')
                ->label('Booking Status')
                ->options(Booking::statusOptions())
                ->descriptions([
                    Booking::STATUS_UNPAID => 'Awaiting payment.',
                    Booking::STATUS_CONFIRMED => 'Booking confirmed.',
                    Booking::STATUS_PAID => 'Payment received.',
                    Booking::STATUS_OCCUPIED => 'Guest checked in.',
                    Booking::STATUS_COMPLETED => 'Stay completed.',
                    Booking::STATUS_CANCELLED => 'Booking cancelled.',
                ])
                ->columns(2)
                ->default(Booking::STATUS_UNPAID)
                ->required()
                ->helperText('Select the current status for this booking.'),

            TextInput::make('reference_number')
                ->label('Reference Number')
                ->disabled()
                ->dehydrated(false),
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
            $days = (int) $startDate->diffInDays($endDate);

            $set('no_of_days', max(1, $days)); // Store integer 1, 2, etc.
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

        if (($roomIds || $venueIds) && $days > 0) {
            if ($roomIds !== []) {
                $roomsTotal = Room::whereIn('id', $roomIds)->sum('price');
            } else {
                $roomsTotal = 0.0;
                $routeRecord = request()->route('record');
                if ($routeRecord instanceof Booking) {
                    $routeRecord->loadMissing('roomLines');
                    if ($routeRecord->roomLines->isNotEmpty()) {
                        $roomsTotal = (float) $routeRecord->roomLines->sum(
                            fn ($line) => $line->quantity * (float) $line->unit_price_per_night
                        );
                    }
                }
            }
            $venueEventType = $get('venue_event_type');
            $venues = Venue::whereIn('id', $venueIds)->get();
            $venuesTotal = BookingPricing::sumVenueLine($venues, $venueEventType);
            $set('total_price', ($roomsTotal + $venuesTotal) * $days);
        } else {
            $set('total_price', 0);
        }
    }

    private static function hasRoomConflicts($roomIds, $checkIn, $checkOut, ?Booking $record): bool
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

        if (Booking::query()
            ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
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

    private static function hasVenueConflicts($venueIds, $checkIn, $checkOut, ?Booking $record): bool
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

        if ($end->lessThanOrEqualTo($start)) {
            return false;
        }

        return Booking::query()
            ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
            ->where('check_in', '<', $end)
            ->where('check_out', '>', $start)
            ->whereHas('venues', fn ($query) => $query->whereIn('venues.id', $venueIds))
            ->exists();
    }

    /**
     * HTML label for the Assigned rooms select: colored strip per type + bed-spec group.
     * When the booking has room lines, colors follow the same order as the billing summary lines.
     */
    private static function assignedRoomOptionHtml(Room $room, ?Booking $booking): string
    {
        $room->loadMissing(['bedSpecifications', 'bedModifiers']);
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

        return '<span class="inline-flex w-full max-w-full min-w-0 items-baseline gap-x-1 '.$class.'"><span class="font-medium text-gray-950 dark:text-white">'.$name.'</span><span class="text-gray-600 dark:text-gray-300 shrink-0">('.$summary.')</span></span>';
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
        $booking->loadMissing('roomLines');
        if ($booking->roomLines->isEmpty()) {
            return self::normalizeRoomIdList($roomIds);
        }

        $needs = [];
        foreach ($booking->roomLines as $line) {
            $k = $line->room_type."\0".$line->inventory_group_key;
            $needs[$k] = ($needs[$k] ?? 0) + (int) $line->quantity;
        }

        $roomIds = self::normalizeRoomIdList($roomIds);
        if ($roomIds === []) {
            return [];
        }

        $rooms = Room::query()
            ->whereIn('id', $roomIds)
            ->with(['bedSpecifications', 'bedModifiers'])
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($roomIds as $id) {
            $room = $rooms->get($id);
            if (! $room) {
                continue;
            }
            $k = $room->type."\0".RoomInventoryGroupKey::forRoom($room);
            if (($needs[$k] ?? 0) > 0) {
                $result[] = $id;
                $needs[$k]--;
            }
        }

        return $result;
    }
}
