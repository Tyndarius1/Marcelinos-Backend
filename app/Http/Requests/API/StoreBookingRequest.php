<?php

namespace App\Http\Requests\API;

use App\Support\BookingPricing;
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
        if ($this->has('room_lines') && is_array($this->room_lines)) {
            $this->merge([
                'room_lines' => collect($this->room_lines)
                    ->map(function ($line) {
                        if (! is_array($line)) {
                            return null;
                        }

                        return [
                            'room_type' => $line['room_type'] ?? null,
                            'inventory_group_key' => $line['inventory_group_key'] ?? null,
                            'quantity' => isset($line['quantity']) ? (int) $line['quantity'] : null,
                            'unit_price' => isset($line['unit_price']) ? (float) $line['unit_price'] : null,
                        ];
                    })
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
            'room_lines' => 'nullable|array|max:32',
            'room_lines.*.room_type' => ['required', 'string', Rule::in(['standard', 'family', 'deluxe'])],
            'room_lines.*.inventory_group_key' => 'required|string|max:512',
            'room_lines.*.quantity' => 'required|integer|min:1|max:50',
            'room_lines.*.unit_price' => 'required|numeric|min:0',
            'venues' => 'nullable|array',
            'venues.*' => ['required_with:venues', 'integer', 'distinct', Rule::exists('venues', 'id')],
            'venue_event_type' => [
                'nullable',
                'string',
                Rule::in([
                    BookingPricing::VENUE_EVENT_WEDDING,
                    BookingPricing::VENUE_EVENT_BIRTHDAY,
                    BookingPricing::VENUE_EVENT_SEMINAR,
                ]),
                Rule::requiredIf(fn () => is_array($this->venues) && count($this->venues) > 0),
            ],
            'check_in' => 'required|string',
            'check_out' => 'required|string',
            'days' => 'required|integer|min:1',
            'total_price' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'venues.*.exists' => 'Selected venue :input does not exist.',
        ];
    }
}
