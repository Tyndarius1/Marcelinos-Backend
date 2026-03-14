<?php

namespace App\Filament\Resources\Reviews\Schemas;

use App\Models\Guest;
use App\Models\Review;
use App\Models\Room;
use App\Models\Venue;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('guest_id')
                    ->label('Guest')
                    ->relationship('guest', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn (Guest $record) => $record->full_name)
                    ->searchable()
                    ->required(),

                Select::make('booking_id')
                    ->label('Booking')
                    ->relationship('booking', 'reference_number')
                    ->searchable()
                    ->nullable(),

                Toggle::make('is_site_review')
                    ->label('Site Review')
                    ->default(false)
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state): void {
                        if ($state) {
                            $set('reviewable_type', null);
                            $set('reviewable_id', null);
                        }
                    }),

                Select::make('reviewable_type')
                    ->label('Review Type')
                    ->options([
                        Room::class => 'Room',
                        Venue::class => 'Venue',
                    ])
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('reviewable_id', null))
                    ->required(fn (Get $get) => ! $get('is_site_review'))
                    ->visible(fn (Get $get) => ! $get('is_site_review')),



                Select::make('reviewable_id')
                    ->label('Review Target')
                    ->options(function (Get $get): array {
                        return match ($get('reviewable_type')) {
                            Room::class => Room::query()->pluck('name', 'id')->all(),
                            Venue::class => Venue::query()->pluck('name', 'id')->all(),
                            default => [],
                        };
                    })
                    ->searchable()
                    ->required(fn (Get $get) => ! $get('is_site_review'))
                    ->visible(fn (Get $get) => ! $get('is_site_review')),

                Select::make('rating')
                    ->options(Review::ratingOptions())
                    ->required(),



                Textarea::make('comment')
                    ->rows(4)
                    ->columnSpanFull(),

                Toggle::make('is_approved')
                    ->label('Approved')
                    ->default(false),

                DateTimePicker::make('reviewed_at')
                    ->native(false),
            ]);
    }
}
