<?php

namespace App\Filament\Resources\Galleries\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\Galleries\GalleryResource;
use App\Models\Gallery;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditGallery extends EditRecord
{
    protected static string $resource = GalleryResource::class;

    protected function getHeaderActions(): array
    {
        $expected = fn (Gallery $record): string => 'Gallery #'.$record->getKey();

        if ($this->record->trashed()) {
            return [
                RestoreAction::make(),
                TypedForceDeleteAction::make($expected)
                    ->label('Delete permanently')
                    ->modalHeading('Delete image permanently'),
            ];
        }

        return [
            TypedDeleteAction::make($expected)
                ->label('Delete image')
                ->modalHeading('Delete image')
                ->successNotificationTitle('Image moved to recycle bin'),
        ];
    }
}
