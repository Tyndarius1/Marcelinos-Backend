<?php

namespace App\Filament\Resources\BlockedDates\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\BlockedDate;

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
                    ->closeOnDateSelection(true)
                    ->disabledDates(fn () =>
                        BlockedDate::pluck('date')->toArray()
                    ),
                TextInput::make('reason')
                ->required()
                ->maxLength(255),
            ]);
    }
}
