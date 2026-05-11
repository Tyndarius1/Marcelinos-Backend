<?php

namespace App\Filament\Resources\RoomChecklistTemplates\Schemas;

use App\Models\Room;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoomChecklistTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Checklist item template')
                    ->description('Define inventory items used during room checkout inspection.')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        TextInput::make('label')
                            ->label('Item label')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Example: TV remote'),

                        TextInput::make('default_charge')
                            ->label('Default charge')
                            ->maxLength(255)
                            ->placeholder('Optional'),

                        TextInput::make('sort_order')
                            ->label('Sort order')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),

                        CheckboxList::make('applicable_room_types')
                            ->label('Applies to rooms')
                            ->helperText('Leave empty to apply this item to all rooms.')
                            ->options(fn (): array => Room::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->columns(2)
                            ->bulkToggleable()
                            ->searchable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
