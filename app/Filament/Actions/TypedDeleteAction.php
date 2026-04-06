<?php

namespace App\Filament\Actions;

use Closure;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

final class TypedDeleteAction
{
    /**
     * Delete only after the user types the exact string returned by $resolveExpectedText.
     *
     * @param  Closure(Model): string  $resolveExpectedText
     */
    public static function make(Closure $resolveExpectedText): DeleteAction
    {
        return DeleteAction::make()
            ->modalDescription(__('This moves the record to the recycle bin. You can restore it or delete it permanently from there.'))
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
