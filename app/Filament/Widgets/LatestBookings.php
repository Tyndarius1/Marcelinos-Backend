<?php

namespace App\Filament\Widgets;

use Filament\Widgets\TableWidget;
use App\Models\Booking;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class LatestBookings extends TableWidget
{
    protected static ?int $sort = 2;
    
    protected static ?string $heading = 'Latest Bookings';

    protected int | string | array $columnSpan = 'full';

    // Corrected method signature
    protected function getTableQuery(): Builder|Relation|null
    {
        return Booking::query()->with('guest')->latest();
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('id')
                ->label('Booking ID')
                ->sortable(),
            
            Tables\Columns\TextColumn::make('guest.full_name')
                ->label('Customer')
                ->formatStateUsing(fn ($record) => $record->displayGuestName()),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Date')
                ->dateTime('M d, Y H:i'),

            Tables\Columns\BadgeColumn::make('booking_status')
                ->label('Status')
                ->formatStateUsing(fn (?string $state) => Booking::bookingStatusOptions()[$state] ?? ucfirst((string) $state))
                ->colors(Booking::bookingStatusColors()),
        ];
    }
}