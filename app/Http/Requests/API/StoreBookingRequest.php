<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('rooms') && is_array($this->rooms)) {
            $this->merge([
                'rooms' => collect($this->rooms)
                    ->map(fn ($room) => is_array($room) ? ($room['id'] ?? $room[0] ?? null) : $room)
                    ->filter()
                    ->values()
                    ->all(),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'reference_number' => 'nullable|string',
            'rooms'   => 'nullable|array',
            'rooms.*' => ['integer', 'distinct', Rule::exists('rooms', 'id')],
            'venues'  => 'nullable|array',
            'venues.*' => ['required_with:venues', 'integer', 'distinct', Rule::exists('venues', 'id')],
            'check_in'  => 'required|string',
            'check_out' => 'required|string',
            'days'      => 'required|integer|min:1',
            'total_price' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'rooms.*.exists' => 'Selected room :input does not exist.',
            'rooms.*.distinct' => 'Duplicate room selection is not allowed.',
            'venues.*.exists' => 'Selected venue :input does not exist.',
        ];
    }
}
