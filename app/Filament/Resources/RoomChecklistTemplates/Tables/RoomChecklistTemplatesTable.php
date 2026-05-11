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
                    ->label('Rooms')
                    ->getStateUsing(function (RoomChecklistTemplate $record): string {
                        $state = $record->applicable_room_types;
                        $values = is_array($state)
                            ? array_values(array_filter($state, fn ($value): bool => (string) $value !== ''))
                            : [];

                        if ($values === []) {
                            return 'All rooms';
                        }

                        $roomIds = collect($values)
                            ->map(fn ($value): int => (int) $value)
                            ->filter(fn (int $id): bool => $id > 0)
                            ->unique()
                            ->values()
                            ->all();

                        if ($roomIds !== []) {
                            $names = Room::query()
                                ->whereIn('id', $roomIds)
                                ->orderBy('name')
                                ->pluck('name')
                                ->filter(fn ($name): bool => is_string($name) && trim((string) $name) !== '')
                                ->values();

                            if ($names->isNotEmpty()) {
                                return $names->implode(', ');
                            }
                        }

                        // Backward compatibility: legacy rows may still contain room type keys.
                        $labels = Room::typeOptions();
                        $legacy = collect($values)
                            ->map(fn ($value): string => strtolower(trim((string) $value)))
                            ->filter(fn (string $type): bool => array_key_exists($type, $labels))
                            ->unique()
                            ->values();

                        return $legacy->isEmpty()
                            ? 'All rooms'
                            : $legacy->map(fn (string $type): string => (string) $labels[$type])->implode(', ');
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
