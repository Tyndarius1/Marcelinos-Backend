<?php

namespace App\Filament\Resources\ContactUs;

use App\Filament\Resources\Concerns\ResolvesTrashedRecordRoutes;
use App\Filament\Resources\ContactUs\Pages\EditContactUs;
use App\Filament\Resources\ContactUs\Pages\ListContactUs;
use App\Filament\Resources\ContactUs\Schemas\ContactUsForm;
use App\Filament\Resources\ContactUs\Tables\ContactUsTable;
use App\Models\ContactUs;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContactUsResource extends Resource
{
    use ResolvesTrashedRecordRoutes;

    /**
     * Show a badge in the navigation if there are new contact requests.
     */
    public static function getNavigationBadge(): ?string
    {
        // Count only 'new' status, matching the table and migration
        $count = ContactUs::where('status', 'new')->count();

        return $count > 0 ? (string) $count : null;
    }

    protected static ?string $model = ContactUs::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Contact Us';

    protected static string|\UnitEnum|null $navigationGroup = 'Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Contact Inquiry';

    protected static ?string $pluralModelLabel = 'Contact Inquiries';

    public static function form(Schema $schema): Schema
    {
        return ContactUsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactUsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactUs::route('/'),
            'edit' => EditContactUs::route('/{record}/edit'),
        ];
    }
}
