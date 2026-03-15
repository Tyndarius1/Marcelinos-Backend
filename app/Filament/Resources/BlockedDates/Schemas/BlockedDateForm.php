<?php

namespace App\Filament\Resources\BlockedDates\Schemas;

use App\Filament\Forms\Components\BlockedDateConflictsDisplay;
use App\Models\BlockedDate;
use App\Models\Booking;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class BlockedDateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->required()
                    ->minDate(now())
                    ->native(false)
                    ->live()
                    ->closeOnDateSelection(true)
                    ->disabledDates(fn () =>
                        BlockedDate::pluck('date')->toArray()
                    )
                    ->helperText('If this date has existing bookings, you must contact those guests before blocking.'),

                Section::make('Existing bookings on this date')
                    ->description('These guests have bookings on the selected date. Contact them before blocking.')
                    ->visible(fn (Get $get) => self::dateHasConflicts($get('date')))
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('warning')
                    ->schema([
                        BlockedDateConflictsDisplay::make('_conflicts_display')
                            ->conflicts(fn (Get $get) => Booking::getConflictsForDate($get('date') ?? '') ?? [])
                            ->live()
                            ->dehydrated(false),
                        Toggle::make('confirm_contacted')
                            ->label('I have contacted the guests above and confirmed they are aware before blocking.')
                            ->required()
                            ->rules([
                                fn (Get $get) => function (string $attribute, $value, $fail) use ($get): void {
                                    if (! self::dateHasConflicts($get('date'))) {
                                        return;
                                    }
                                    if (! $value) {
                                        $fail('You must contact the customers with existing bookings on this date before blocking. Please confirm above after you have done so.');
                                    }
                                },
                            ]),
                    ]),

                TextInput::make('reason')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    private static function dateHasConflicts(?string $date): bool
    {
        if (! $date) {
            return false;
        }
        return \count(Booking::getConflictsForDate($date)) > 0;
    }
}
