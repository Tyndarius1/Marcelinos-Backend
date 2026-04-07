<?php

namespace App\Filament\Resources\BubbleChatFaqs;

use App\Filament\Resources\BubbleChatFaqs\Pages\CreateBubbleChatFaq;
use App\Filament\Resources\BubbleChatFaqs\Pages\EditBubbleChatFaq;
use App\Filament\Resources\BubbleChatFaqs\Pages\ListBubbleChatFaqs;
use App\Filament\Resources\BubbleChatFaqs\Schemas\BubbleChatFaqForm;
use App\Filament\Resources\BubbleChatFaqs\Tables\BubbleChatFaqsTable;
use App\Filament\Resources\Concerns\ResolvesTrashedRecordRoutes;
use App\Models\BubbleChatFaq;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class BubbleChatFaqResource extends Resource
{
    use ResolvesTrashedRecordRoutes;

    protected static ?string $model = BubbleChatFaq::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Bubble chat FAQs';

    protected static ?string $modelLabel = 'bubble chat FAQ';

    protected static ?string $pluralModelLabel = 'bubble chat FAQs';

    protected static ?string $recordTitleAttribute = 'question';

    public static function form(Schema $schema): Schema
    {
        return BubbleChatFaqForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BubbleChatFaqsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBubbleChatFaqs::route('/'),
            'create' => CreateBubbleChatFaq::route('/create'),
            'edit' => EditBubbleChatFaq::route('/{record}/edit'),
        ];
    }
}
