<?php

namespace App\Filament\Resources\Reviews\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Models\Review;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditReview extends EditRecord
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        $resolveExpected = function (Review $record): string {
            $title = trim((string) $record->title);

            return $title !== '' ? $title : 'Review #'.$record->getKey();
        };

        if ($this->record->trashed()) {
            return [
                RestoreAction::make(),
                TypedForceDeleteAction::make($resolveExpected),
            ];
        }

        return [
            TypedDeleteAction::make($resolveExpected),
        ];
    }
}
