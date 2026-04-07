<?php

namespace App\Filament\Resources\BubbleChatFaqs\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\BubbleChatFaqs\BubbleChatFaqResource;
use App\Models\BubbleChatFaq;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditBubbleChatFaq extends EditRecord
{
    protected static string $resource = BubbleChatFaqResource::class;

    protected function getHeaderActions(): array
    {
        if ($this->record->trashed()) {
            return [
                RestoreAction::make(),
                TypedForceDeleteAction::make(fn (BubbleChatFaq $record): string => $record->question),
            ];
        }

        return [
            TypedDeleteAction::make(fn (BubbleChatFaq $record): string => $record->question),
        ];
    }
}
