<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('total_amount')
                    ->label('Booking Total Amount')
                    ->default(fn (RelationManager $livewire): int|float => $livewire->getOwnerRecord()->total_price)
                    ->disabled()
                    ->dehydrated()
                    ->required()
                    ->numeric()
                    ->prefix('₱'),
                TextInput::make('partial_amount')
                    ->label('Amount Paid (Cash)')
                    ->required()
                    ->numeric()
                    ->prefix('₱')
                    ->maxValue(fn (RelationManager $livewire): int|float => $livewire->getOwnerRecord()->balance),
                Toggle::make('is_fullypaid')
                    ->label('Mark as Fully Paid')
                    ->default(false)
                    ->hidden(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('total_amount')
            ->columns([
                TextColumn::make('total_amount')
                    ->label('Booking Total')
                    ->money('PHP')
                    ->sortable(),
                TextColumn::make('partial_amount')
                    ->label('Amount Paid')
                    ->money('PHP')
                    ->sortable(),
                IconColumn::make('is_fullypaid')
                    ->boolean()
                    ->label('Fully Paid?'),
                TextColumn::make('created_at')
                    ->label('Date Paid')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Payment')
                    ->modalHeading('Record New Cash Payment')
                    ->mutateFormDataUsing(function (array $data, RelationManager $livewire): array {
                        $booking = $livewire->getOwnerRecord();
                        $totalPaidSoFar = collect($livewire->getRelationship()->get())->sum('partial_amount');
                        $newTotalPaid = $totalPaidSoFar + $data['partial_amount'];
                        
                        $data['is_fullypaid'] = $newTotalPaid >= $booking->total_price;
                        return $data;
                    })
                    ->after(function (array $data, RelationManager $livewire) {
                        $booking = $livewire->getOwnerRecord();
                        // Update booking status if fully paid
                        if ($booking->total_paid >= $booking->total_price) {
                            $booking->update(['status' => \App\Models\Booking::STATUS_PAID]);
                        }
                    }),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
