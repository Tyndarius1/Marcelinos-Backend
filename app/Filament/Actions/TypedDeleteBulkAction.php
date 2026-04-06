<?php

namespace App\Filament\Actions;

use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;

final class TypedDeleteBulkAction
{
    private const CONFIRM_WORD = 'DELETE';

    public static function make(): DeleteBulkAction
    {
        $word = self::CONFIRM_WORD;

        return DeleteBulkAction::make()
            ->modalDescription(__('Selected records will be moved to the recycle bin. Restore or permanently delete them from the Recycle bin page or the trashed filter on each list.'))
            ->schema([
                TextInput::make('type_to_confirm')
                    ->label(__('To confirm, type :word in all capital letters', ['word' => $word]))
                    ->required()
                    ->autocomplete(false)
                    ->rules([
                        function () use ($word) {
                            return function (string $attribute, mixed $value, \Closure $fail) use ($word): void {
                                if (trim((string) $value) !== $word) {
                                    $fail(__('You must type DELETE in all capital letters.'));
                                }
                            };
                        },
                    ]),
            ]);
    }
}
