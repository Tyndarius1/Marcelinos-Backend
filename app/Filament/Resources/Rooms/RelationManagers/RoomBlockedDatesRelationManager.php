<?php

namespace App\Filament\Resources\Rooms\RelationManagers;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedDeleteBulkAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Actions\TypedForceDeleteBulkAction;
use App\Filament\Forms\Components\BlockedDateConflictsDisplay;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlockedDate;
use Carbon\CarbonInterface;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RoomBlockedDatesRelationManager extends RelationManager
{
    protected static string $relationship = 'roomBlockedDates';

    protected static ?string $title = 'Blocked booking dates';

    protected static ?string $modelLabel = 'blocked date';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Room $ownerRecord */
        return (string) $ownerRecord->roomBlockedDates()->count();
    }

    public function form(Schema $schema): Schema
    {
        $relationManager = $this;

        return $schema
            ->components([
                DatePicker::make('blocked_on')
                    ->label('Date')
                    ->required()
                    ->native(false)
                    ->live()
                    ->closeOnDateSelection(true)
                    ->disabledDates(function (): array {
                        $room = $this->getOwnerRecord();
                        if (! $room instanceof Room) {
                            return [];
                        }

                        return RoomBlockedDate::query()
                            ->where('room_id', $room->id)
                            ->when(
                                $this->getMountedTableActionRecord(),
                                fn ($q) => $q->whereKeyNot($this->getMountedTableActionRecord()->getKey())
                            )
                            ->pluck('blocked_on')
                            ->map(fn ($d) => $d instanceof CarbonInterface ? $d->format('Y-m-d') : (string) $d)
                            ->all();
                    })
                    ->helperText('Guests cannot book this room for stays that include this calendar day.'),

                Section::make('Existing bookings on this date')
                    ->description('Guests with bookings that include this room on the selected date. Contact them before blocking if needed.')
                    ->visible(fn (Get $get) => $this->roomDateHasConflicts($get('blocked_on')))
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('warning')
                    ->schema([
                        BlockedDateConflictsDisplay::make('_conflicts_display')
                            ->conflicts(fn (Get $get) => $this->conflictsForSelectedDate($get('blocked_on')))
                            ->live()
                            ->dehydrated(false),
                        Toggle::make('confirm_contacted')
                            ->label('I have reviewed the bookings above before blocking this date.')
                            ->required(fn (Get $get) => $this->roomDateHasConflicts($get('blocked_on')))
                            ->rules([
                                fn (Get $get) => function (string $attribute, $value, $fail) use ($get, $relationManager): void {
                                    if (! $relationManager->roomDateHasConflicts($get('blocked_on'))) {
                                        return;
                                    }
                                    if (! $value) {
                                        $fail('Please confirm you have reviewed existing bookings for this room on this date.');
                                    }
                                },
                            ]),
                    ]),

                TextInput::make('reason')
                    ->label('Reason (optional)')
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('blocked_on')
            ->columns([
                TextColumn::make('blocked_on')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('reason')
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->defaultSort('blocked_on', 'desc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Block date'),
            ])
            ->recordActions([
                EditAction::make(),
                RestoreAction::make(),
                TypedForceDeleteAction::make(fn (RoomBlockedDate $record): string => $record->blocked_on?->format('Y-m-d') ?? ''),
                TypedDeleteAction::make(fn (RoomBlockedDate $record): string => $record->blocked_on?->format('Y-m-d') ?? ''),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    TypedDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    TypedForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    private function conflictsForSelectedDate(?string $blockedOn): array
    {
        $room = $this->getOwnerRecord();
        if (! $room instanceof Room || ! $blockedOn) {
            return [];
        }

        return Booking::getConflictsForRoomOnDate((int) $room->id, $blockedOn);
    }

    private function roomDateHasConflicts(?string $blockedOn): bool
    {
        return \count($this->conflictsForSelectedDate($blockedOn)) > 0;
    }
}
