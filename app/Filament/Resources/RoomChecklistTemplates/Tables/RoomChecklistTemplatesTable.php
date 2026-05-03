<?php

namespace App\Filament\Resources\RoomChecklistTemplates\Tables;

use App\Filament\Actions\TypedDeleteBulkAction;
use App\Filament\Actions\TypedForceDeleteBulkAction;
use App\Filament\Resources\RoomChecklistTemplates\RoomChecklistTemplateResource;
use App\Models\Room;
use App\Models\RoomChecklistTemplate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RoomChecklistTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordAction('view')
            ->columns([
                TextColumn::make('label')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('applicable_room_types_display')
                    ->label('Room types')
                    ->getStateUsing(function (RoomChecklistTemplate $record): string {
                        $state = $record->applicable_room_types;
                        $types = is_array($state)
                            ? array_values(array_filter($state, fn ($value): bool => is_string($value) && trim((string) $value) !== ''))
                            : [];

                        if ($types === []) {
                            return 'All rooms';
                        }

                        $labels = Room::typeOptions();
                        $normalized = collect($types)
                            ->map(fn ($type): string => strtolower(trim((string) $type)))
                            ->filter(fn (string $type): bool => array_key_exists($type, $labels))
                            ->unique()
                            ->values();

                        if ($normalized->isEmpty()) {
                            return 'All rooms';
                        }

                        return $normalized
                            ->map(fn (string $type): string => (string) $labels[$type])
                            ->implode(', ');
                    })
                    ->wrap(),

                TextColumn::make('default_charge')
                    ->label('Default charge')
                    ->placeholder('—'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->modalHeading('Checklist item template'),
                EditAction::make()
                    ->slideOver()
                    ->modalHeading('Edit checklist item template')
                    ->modalSubmitActionLabel('Save changes'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    TypedDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    TypedForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
