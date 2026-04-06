<?php

namespace App\Filament\Resources\Galleries\Tables;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedDeleteBulkAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Actions\TypedForceDeleteBulkAction;
use App\Filament\Resources\Galleries\GalleryResource;
use App\Models\Gallery;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class GalleriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->extraAttributes([
                'class' => 'gallery-table-flat',
            ])
            ->columns([])
            ->content(view('filament.tables.content.galleries-grid'))
            ->defaultSort('created_at', 'desc')
            ->paginated(false)
            ->recordUrl(fn ($record): string => GalleryResource::getUrl('edit', ['record' => $record]))
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Edit image')
                    ->extraAttributes(['class' => 'hidden']),
                RestoreAction::make()
                    ->extraAttributes(['class' => 'hidden']),
                TypedForceDeleteAction::make(fn (Gallery $record): string => 'Gallery #'.$record->getKey())
                    ->label('Delete permanently')
                    ->extraAttributes(['class' => 'hidden']),
                TypedDeleteAction::make(fn (Gallery $record): string => 'Gallery #'.$record->getKey())
                    ->label('Delete image')
                    ->modalHeading('Delete image')
                    ->successNotificationTitle('Image moved to recycle bin')
                    ->extraAttributes(['class' => 'hidden']),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    TypedDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    TypedForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
