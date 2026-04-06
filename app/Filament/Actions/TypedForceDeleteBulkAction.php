<?php

namespace App\Filament\Actions;

use Filament\Actions\ForceDeleteBulkAction;
use Filament\Forms\Components\TextInput;

final class TypedForceDeleteBulkAction
{
    private const CONFIRM_WORD = 'DELETE';

    public static function make(): ForceDeleteBulkAction
    {
        $word = self::CONFIRM_WORD;

        return ForceDeleteBulkAction::make()
            ->modalDescription(__('Selected records will be permanently removed from the database. This cannot be undone.'))
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
