<?php

namespace App\Filament\Resources\BubbleChatFaqs\Pages;

use App\Filament\Resources\BubbleChatFaqs\BubbleChatFaqResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBubbleChatFaqs extends ListRecords
{
    protected static string $resource = BubbleChatFaqResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
