<?php

namespace App\Filament\Resources\BookingInspections;

use App\Filament\Resources\BookingInspections\Pages\ViewBookingInspection;
use App\Models\BookingInspection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class BookingInspectionResource extends Resource
{
    protected static ?string $model = BookingInspection::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return __('Checkout inspection');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Checkout inspections');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'items.photos',
            'items.inventoryItem.room',
            'inspectedBy:id,name',
            'booking:id,reference_number',
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Inspection'))
                    ->schema([
                        Html::make(function (?BookingInspection $record): View|Htmlable|string {
                            return view('filament.bookings.inspection-detail', [
                                'inspection' => $record,
                            ]);
                        }),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'view' => ViewBookingInspection::route('/{record}'),
        ];
    }
}
