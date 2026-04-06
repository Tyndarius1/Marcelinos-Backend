<?php

namespace App\Filament\Actions;

use Closure;
use Filament\Actions\ForceDeleteAction;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

final class TypedForceDeleteAction
{
    /**
     * Permanently delete only after the user types the exact string returned by $resolveExpectedText.
     *
     * @param  Closure(Model): string  $resolveExpectedText
     */
    public static function make(Closure $resolveExpectedText): ForceDeleteAction
    {
        return ForceDeleteAction::make()
            ->modalDescription(__('This permanently removes the record from the database. It cannot be undone.'))
            ->schema(function (Model $record) use ($resolveExpectedText): array {
                $expected = $resolveExpectedText($record);

                return [
                    TextInput::make('type_to_confirm')
                        ->label(__('To confirm, type the following exactly'))
                        ->placeholder($expected)
                        ->helperText(new HtmlString('<span class="font-mono font-semibold">'.e($expected).'</span>'))
                        ->required()
                        ->autocomplete(false)
                        ->rules([
                            function () use ($expected) {
                                return function (string $attribute, mixed $value, Closure $fail) use ($expected): void {
                                    if (trim((string) $value) !== trim($expected)) {
                                        $fail(__('The text does not match. Copy it exactly, including spaces and capitalization.'));
                                    }
                                };
                            },
                        ]),
                ];
            });
    }
}
