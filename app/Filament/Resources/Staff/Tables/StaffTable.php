<?php

namespace App\Filament\Resources\Staff\Tables;

use App\Models\User;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class StaffTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('name')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable()
                    ->extraAttributes(['class' => 'font-bold']),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->extraAttributes(['class' => 'text-gray-600']),

                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->colors([
                        'success' => fn($state) => $state === 'staff',
                        'primary' => fn($state) => $state === 'admin',
                    ])
                    ->sortable(),

                TextColumn::make('permissions')
                    ->label('Privileges')
                    ->badge()
                    ->formatStateUsing(function ($state): string {
                        if (is_string($state)) {
                            $state = json_decode($state, true);
                        }
                        if (! is_array($state) || empty($state)) {
                            return 'No privileges';
                        }

                        return (string) count($state) . ' selected';
                    }),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),

                    TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive')
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'staff' => 'Staff',
                        'admin' => 'Admin',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
            ])
            ->recordActions([
                Action::make('changeStatus')
                    ->label(fn ($record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn ($record) =>
                        $record->is_active
                            ? 'heroicon-o-x-circle'
                            : 'heroicon-o-check-circle'
                    )
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) =>
                        $record->is_active ? 'Deactivate Staff' : 'Activate Staff'
                    )
                    ->modalDescription(fn ($record) =>
                        $record->is_active
                            ? 'Are you sure you want to deactivate this staff member? They will no longer be able to log in.'
                            : 'Are you sure you want to activate this staff member? They will regain access to the system.'
                    )
                    ->modalSubmitActionLabel(fn ($record) =>
                        $record->is_active ? 'Yes, Deactivate' : 'Yes, Activate'
                    )
                    ->action(fn ($record) =>
                        $record->update(['is_active' => ! $record->is_active])
                    ),
                    EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
