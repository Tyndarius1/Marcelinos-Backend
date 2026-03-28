<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Filament\Forms\Components\PhAddressFields;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\DateTimePicker;
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
use Illuminate\Validation\Rule;

class BookingCreateWizard
{
    /**
     * @return array<int, Step>
     */
    public static function steps(): array
    {
        return [
            Step::make('Accommodation')
                ->description('Choose check-in and check-out dates, then select rooms only (no venues).')
                ->schema([
                    DateTimePicker::make('check_in')
                        ->label('Check-in')
                        ->required()
                        ->native(false)
                        ->live()
                        ->seconds(false)
                        ->rules([
                            fn (Get $get) => self::roomAvailabilityRuleForCheckIn($get),
                        ])
                        ->afterStateUpdated(fn (Get $get, Set $set) => BookingForm::updatePricing($get, $set)),

                    DateTimePicker::make('check_out')
                        ->label('Check-out')
                        ->required()
                        ->native(false)
                        ->live()
                        ->seconds(false)
                        ->minDate(fn (Get $get) => filled($get('check_in')) ? Carbon::parse($get('check_in'))->addMinute() : null)
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
                            fn (Get $get) => self::roomAvailabilityRuleForCheckOut($get),
                        ])
                        ->afterStateUpdated(fn (Get $get, Set $set) => BookingForm::updatePricing($get, $set)),

                    Select::make('rooms')
                        ->label('Rooms')
                        ->relationship('rooms', 'name')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->helperText('Totals update automatically. Date changes re-check availability below and on each date field.')
                        ->rules([
                            fn (Get $get, ?Booking $record) => function (string $attribute, $value, $fail) use ($get, $record): void {
                                if (BookingForm::hasRoomConflicts($value, $get('check_in'), $get('check_out'), $record)) {
                                    $fail('One or more selected rooms are not available for the chosen dates.');
                                }
                            },
                        ])
                        ->afterStateUpdated(fn (Get $get, Set $set) => BookingForm::updatePricing($get, $set)),

                    TextInput::make('no_of_days')
                        ->label('Nights')
                        ->numeric()
                        ->suffix('nights')
                        ->readOnly()
                        ->dehydrated(),

                    TextInput::make('total_price')
                        ->label('Room total (estimated)')
                        ->default(0)
                        ->readOnly()
                        ->dehydrated()
                        ->numeric()
                        ->prefix('₱')
                        ->helperText('Rooms × nights. Additional payment step records what the guest pays now.'),

                    Text::make(fn (Get $get): string => BookingForm::hasRoomConflicts($get('rooms'), $get('check_in'), $get('check_out'), null)
                        ? 'These rooms are not available for the selected dates (another booking or blocked date overlaps). Change dates or rooms before continuing.'
                        : '')
                        ->color('danger')
                        ->visible(fn (Get $get): bool => BookingForm::hasRoomConflicts($get('rooms'), $get('check_in'), $get('check_out'), null)),
                ]),
            Step::make('Guest details')
                ->description('Create the guest profile for this booking.')
                ->schema([
                    TextInput::make('first_name')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('middle_name')
                        ->maxLength(100),
                    TextInput::make('last_name')
                        ->required()
                        ->maxLength(100),
                    Select::make('gender')
                        ->options(Guest::genderOptions())
                        ->required()
                        ->native(false),
                    TextInput::make('contact_num')
                        ->label('Phone number')
                        ->required()
                        ->maxLength(20),
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->helperText('Will be used to send booking confirmation and other notifications.')
                        ->maxLength(255)
                        ->rules([Rule::unique('guests', 'email')]),
                    Toggle::make('is_international')
                        ->label('Foreign / international address')
                        ->default(false)
                        ->live()
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
                        ->visible(fn (Get $get) => (bool) $get('is_international')),
                    ...PhAddressFields::make(),
                ]),
            Step::make('Review')
                ->description('Confirm stay and guest details. Use the step tabs above to go back and edit or change room selection.')
                ->schema([
                    Section::make('Selected stay')
                        ->schema([
                            Text::make(fn (Get $get): string => self::formatCheckInOut($get))
                                ->weight(FontWeight::SemiBold),
                            Text::make(fn (Get $get): string => self::formatRoomsLine($get)),
                            Text::make(fn (Get $get): string => self::formatNightsAndTotal($get)),
                        ]),
                    Section::make('Guest')
                        ->schema([
                            Text::make(fn (Get $get): string => self::formatGuestName($get))
                                ->weight(FontWeight::SemiBold),
                            Text::make(function (Get $get): string {
                                $g = (string) $get('gender');

                                return 'Gender: '.(Guest::genderOptions()[$g] ?? '—');
                            }),
                            Text::make(fn (Get $get): string => 'Phone: '.($get('contact_num') ?: '—')),
                            Text::make(fn (Get $get): string => 'Email: '.($get('email') ?: '—')),
                            Text::make(fn (Get $get): string => self::formatAddress($get)),
                        ]),
                ]),
            Step::make('Payment')
                ->description('Record what the guest pays now: full balance or any custom amount.')
                ->schema([
                    Radio::make('admin_payment_mode')
                        ->label('Payment')
                        ->options([
                            'full' => 'Pay full amount (matches booking total)',
                            'custom' => 'Custom amount (partial deposit or other)',
                        ])
                        ->default('full')
                        ->live()
                        ->required(),
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
            Step::make('Confirmation')
                ->description('Everything below will be saved when you create the booking.')
                ->schema([
                    Text::make(fn (Get $get): string => 'Stay: '.trim(self::formatCheckInOut($get).' · '.self::formatRoomsLine($get).' · '.self::formatNightsAndTotal($get)))
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

    private static function formatNightsAndTotal(Get $get): string
    {
        $nights = (int) ($get('no_of_days') ?? 0);
        $total = number_format((float) ($get('total_price') ?? 0), 2);

        return "Nights: {$nights} · Total: ₱{$total}";
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
}
