<?php

namespace App\Filament\Resources\DamagesAndLosses;

use App\Filament\Resources\DamagesAndLosses\Pages\ListDamagesAndLosses;
use App\Filament\Resources\DamagesAndLosses\Tables\DamagesAndLossesTable;
use App\Models\RoomChecklistItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DamagesAndLossesResource extends Resource
{
    protected static ?string $model = RoomChecklistItem::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Damages & Losses';

    protected static ?string $recordTitleAttribute = 'label';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasPrivilege('manage_bookings')
            || $user->hasPrivilege('manage_rooms');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function table(Table $table): Table
    {
        return DamagesAndLossesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('status', [
                RoomChecklistItem::STATUS_BROKEN,
                RoomChecklistItem::STATUS_MISSING,
            ])
            ->with([
                'roomChecklist.room:id,name,type',
                'roomChecklist.booking:id,reference_number,guest_id,damage_settlement_status,damage_settlement_marked_by,damage_settlement_marked_at',
                'roomChecklist.booking.guest:id,first_name,middle_name,last_name,contact_num',
                'roomChecklist.booking.damageSettlementMarker:id,name',
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDamagesAndLosses::route('/'),
        ];
    }
}
