<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Schemas\BookingCreateWizard;
use App\Filament\Resources\Bookings\Schemas\BookingForm;
use App\Models\Booking;
use App\Models\Guest;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CreateBooking extends CreateRecord
{
    use HasWizard;

    protected static string $resource = BookingResource::class;

    protected ?int $pendingPaymentAmount = null;

    protected function getSteps(): array
    {
        return BookingCreateWizard::steps();
    }

    protected function hasSkippableSteps(): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (BookingForm::hasRoomConflicts(
            $data['rooms'] ?? [],
            $data['check_in'] ?? null,
            $data['check_out'] ?? null,
            null,
        )) {
            throw ValidationException::withMessages([
                'data.rooms' => __('One or more selected rooms are not available for the chosen dates.'),
            ]);
        }

        $guestKeys = [
            'first_name',
            'middle_name',
            'last_name',
            'email',
            'contact_num',
            'gender',
            'is_international',
            'country',
            'region',
            'province',
            'municipality',
            'barangay',
        ];

        $guestData = Arr::only($data, $guestKeys);
        $guestData['is_international'] = (bool) ($guestData['is_international'] ?? false);
        if (! $guestData['is_international']) {
            $guestData['country'] = $guestData['country'] ?? 'Philippines';
        }

        $guest = Guest::query()->create($guestData);
        $data['guest_id'] = $guest->id;

        foreach ($guestKeys as $key) {
            unset($data[$key]);
        }

        foreach (['ph_region_code', 'ph_province_code', 'ph_municipality_code', 'ph_barangay_code'] as $phKey) {
            unset($data[$phKey]);
        }

        $total = (float) ($data['total_price'] ?? 0);
        $mode = $data['admin_payment_mode'] ?? 'full';
        $payAmount = $mode === 'full'
            ? $total
            : (float) ($data['admin_payment_amount'] ?? 0);
        $payAmount = max(0, $payAmount);
        if ($total > 0) {
            $payAmount = min($payAmount, $total);
        }

        $this->pendingPaymentAmount = (int) round($payAmount);

        unset($data['admin_payment_mode'], $data['admin_payment_amount']);

        $data['venue_event_type'] = null;

        $totalInt = (int) round($total);
        if ($total <= 0) {
            $data['status'] = Booking::STATUS_UNPAID;
        } elseif ($this->pendingPaymentAmount >= $totalInt && $totalInt > 0) {
            $data['status'] = Booking::STATUS_PAID;
        } elseif ($this->pendingPaymentAmount > 0) {
            $data['status'] = Booking::STATUS_CONFIRMED;
        } else {
            $data['status'] = Booking::STATUS_UNPAID;
        }

        return $data;
    }

    /**
     * Used by {@see RecordBookingWizardInitialPayment} after Filament fires {@see RecordCreated}.
     */
    public function getWizardPendingPaymentAmount(): ?int
    {
        return $this->pendingPaymentAmount;
    }
}
