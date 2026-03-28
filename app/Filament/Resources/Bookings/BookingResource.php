<?php

namespace App\Filament\Resources\Bookings;

use App\Filament\Resources\Bookings\RelationManagers\ReviewsRelationManager;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Bookings\Schemas\BookingForm;
use App\Filament\Resources\Bookings\Tables\BookingsTable;
use App\Filament\Widgets\BookingStatsOverview;
use App\Models\Booking;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class BookingResource extends Resource
{
     protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';
    protected static string|\UnitEnum|null $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Bookings';

    protected static ?string $recordTitleAttribute = 'reference_number';

    public static function form(Schema $schema): Schema
    {
        return BookingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BookingsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'guest:id,first_name,middle_name,last_name,email',
                'rooms' => fn ($q) => $q->with(['bedSpecifications', 'bedModifiers']),
                'venues:id,name',
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ReviewsRelationManager::class,
            \App\Filament\Resources\Bookings\RelationManagers\PaymentsRelationManager::class,
            \App\Filament\Resources\Bookings\RelationManagers\RoomLinesRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            BookingStatsOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookings::route('/'),
            'create' => CreateBooking::route('/create'),
            'edit' => EditBooking::route('/{record}/edit'),
            'view' => ViewBooking::route('/{record}'),
        ];
    }

}
