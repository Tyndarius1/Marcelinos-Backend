<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\Venue;
use App\Support\BookingPricing;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class BookingForm
{
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

            Select::make('rooms')
                ->label('Rooms')
                ->relationship('rooms', 'name')
                ->multiple()
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->helperText('Selecting rooms automatically recalculates nights and total. Conflicting rooms will be blocked.')
                ->rules([
                    fn (Get $get, ?Booking $record) => function (string $attribute, $value, $fail) use ($get, $record): void {
                        if (self::hasRoomConflicts($value, $get('check_in'), $get('check_out'), $record)) {
                            $fail('One or more selected rooms are not available for the chosen dates.');
                        }
                    },
                ])
                ->afterStateUpdated(fn (Get $get, Set $set) => self::updatePricing($get, $set)),

            Select::make('venues')
                ->label('Venues')
                ->relationship('venues', 'name')
                ->multiple()
                ->searchable()
                ->preload()
                ->live()
                ->helperText('Optional. Venues are validated against the same date range.')
                ->rules([
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
            $roomsTotal = Room::whereIn('id', $roomIds)->sum('price');
            $venueEventType = $get('venue_event_type');
            $venues = Venue::whereIn('id', $venueIds)->get();
            $venuesTotal = BookingPricing::sumVenueLine($venues, $venueEventType);
            $set('total_price', ($roomsTotal + $venuesTotal) * $days);
        } else {
            $set('total_price', 0);
        }
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
}
