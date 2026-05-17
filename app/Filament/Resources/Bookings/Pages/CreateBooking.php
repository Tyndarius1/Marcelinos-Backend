<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Schemas\BookingCreateWizard;
use App\Filament\Resources\Bookings\Schemas\BookingForm;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Support\RoomInventoryGroupKey;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CreateBooking extends CreateRecord
{
    use HasWizard {
        getWizardComponent as traitGetWizardComponent;
    }

    protected static string $resource = BookingResource::class;

    protected ?int $pendingPaymentAmount = null;

    protected function getSteps(): array
    {
        return BookingCreateWizard::steps();
    }

    public function getWizardComponent(): Component
    {
        return $this->traitGetWizardComponent()
            ->persistStepInQueryString('step');
    }

    protected function hasSkippableSteps(): bool
    {
        return false;
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

        if (BookingForm::hasVenueConflicts(
            $data['venues'] ?? [],
            $data['check_in'] ?? null,
            $data['check_out'] ?? null,
            null,
            is_string($data['venue_event_type'] ?? null) ? $data['venue_event_type'] : null,
        )) {
            throw ValidationException::withMessages([
                'data.venues' => __('One or more selected venues are not available for the chosen dates.'),
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
            'booking_source',
            'is_manual_booking',
            'allow_manual_email_match',
            'guest_status',
            'existing_guest_id',
            'existing_guest_id_picker',
            'edit_returning_guest',
        ];

        $guestData = Arr::only($data, $guestKeys);
        $guestStatus = (string) ($data['guest_status'] ?? 'new');
        $existingGuestId = $data['existing_guest_id'] ?? null;
        if ($existingGuestId === null && isset($data['existing_guest_id_picker'])) {
            $existingGuestId = $data['existing_guest_id_picker'];
        }
        $existingGuestId = is_numeric($existingGuestId) ? (int) $existingGuestId : null;
        $editReturningGuest = (bool) ($data['edit_returning_guest'] ?? false);

        if ($guestStatus === 'returning' && $existingGuestId) {
            $existing = Guest::query()->find($existingGuestId);
            if (! $existing) {
                throw ValidationException::withMessages([
                    'data.email' => __('Selected returning guest was not found. Please search again.'),
                ]);
            }

            if ($editReturningGuest) {
                $patch = Arr::only($data, [
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
                ]);
                $existing->fill($patch);
                $existing->save();
                $existing->refresh();
            }

            $data['guest_id'] = $existing->id;
            $snapshots = Guest::bookingSnapshotAttributesFromSource(array_merge(
                $existing->only([
                    'first_name',
                    'middle_name',
                    'last_name',
                    'email',
                    'contact_num',
                    'barangay',
                    'municipality',
                    'province',
                    'region',
                    'country',
                ]),
                Arr::only($data, [
                    'first_name',
                    'middle_name',
                    'last_name',
                    'email',
                    'contact_num',
                    'barangay',
                    'municipality',
                    'province',
                    'region',
                    'country',
                ]),
            ));
            $data['guest_name_snapshot'] = $snapshots['guest_name_snapshot'];
            $data['guest_email_snapshot'] = $snapshots['guest_email_snapshot'];
            $data['guest_contact_snapshot'] = $snapshots['guest_contact_snapshot'];
            $data['guest_address_snapshot'] = $snapshots['guest_address_snapshot'];

            foreach ($guestKeys as $key) {
                unset($data[$key]);
            }

            foreach (['ph_region_code', 'ph_province_code', 'ph_municipality_code', 'ph_barangay_code'] as $phKey) {
                unset($data[$phKey]);
            }

            // Continue with booking fields.
            goto after_guest_resolution;
        }

        $guestData['is_international'] = (bool) ($guestData['is_international'] ?? false);
        if (! $guestData['is_international']) {
            $guestData['country'] = $guestData['country'] ?? 'Philippines';
        } else {
            $guestData['contact_num'] = trim((string) ($guestData['contact_num'] ?? ''));
            $country = trim((string) ($guestData['country'] ?? ''));

            if (strcasecmp($country, 'Philippines') === 0) {
                throw ValidationException::withMessages([
                    'data.country' => __('Foreign guests cannot use Philippines as country.'),
                ]);
            }
        }

        if ($guestData['is_international'] && ($guestData['contact_num'] ?? null) === '') {
            // guests.contact_num is non-nullable; keep empty string for foreign guests without phone.
            $guestData['contact_num'] = '';
        }

        $guest = Guest::store($guestData);
        $data['guest_id'] = $guest->id;
        $snapshots = Guest::bookingSnapshotAttributesFromSource($guestData);
        $data['guest_name_snapshot'] = $snapshots['guest_name_snapshot'];
        $data['guest_email_snapshot'] = $snapshots['guest_email_snapshot'];
        $data['guest_contact_snapshot'] = $snapshots['guest_contact_snapshot'];
        $data['guest_address_snapshot'] = $snapshots['guest_address_snapshot'];

        foreach ($guestKeys as $key) {
            unset($data[$key]);
        }

        foreach (['ph_region_code', 'ph_province_code', 'ph_municipality_code', 'ph_barangay_code'] as $phKey) {
            unset($data[$phKey]);
        }

        after_guest_resolution:
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
        unset($data['bed_specification_id']);
        unset($data['booking_type']);

        $venueIds = array_filter((array) ($data['venues'] ?? []));
        if ($venueIds === []) {
            $data['venue_event_type'] = null;
        }

        $totalInt = (int) round($total);
        $data['booking_status'] = Booking::BOOKING_STATUS_RESERVED;
        $data['payment_method'] = 'cash';
        $data['online_payment_plan'] = '';
        if ($total <= 0) {
            $data['payment_status'] = Booking::PAYMENT_STATUS_UNPAID;
        } elseif ($this->pendingPaymentAmount >= $totalInt && $totalInt > 0) {
            $data['payment_status'] = Booking::PAYMENT_STATUS_PAID;
        } elseif ($this->pendingPaymentAmount > 0) {
            $data['payment_status'] = Booking::PAYMENT_STATUS_PARTIAL;
        } else {
            $data['payment_status'] = Booking::PAYMENT_STATUS_UNPAID;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        if (! $record instanceof Booking) {
            return;
        }

        $record->loadMissing(['rooms.bedSpecifications']);
        if ($record->rooms->isEmpty()) {
            return;
        }

        // Build guest-style room lines from the assigned rooms (type + bed spec group).
        $groups = [];
        foreach ($record->rooms as $room) {
            $key = $room->type."\0".RoomInventoryGroupKey::forRoom($room);
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'room_type' => $room->type,
                    'inventory_group_key' => RoomInventoryGroupKey::forRoom($room),
                    'quantity' => 0,
                    'sum_price' => 0.0,
                ];
            }
            $groups[$key]['quantity']++;
            $groups[$key]['sum_price'] += (float) ($room->price ?? 0);
        }

        foreach ($groups as $g) {
            $qty = max(1, (int) $g['quantity']);
            $unit = (float) $g['sum_price'] / $qty; // keep totals consistent with selected rooms sum
            $record->roomLines()->create([
                'room_type' => $g['room_type'],
                'inventory_group_key' => $g['inventory_group_key'],
                'quantity' => $qty,
                'unit_price_per_night' => $unit,
            ]);
        }
    }

    /**
     * Used by {@see RecordBookingWizardInitialPayment} after Filament fires {@see RecordCreated}.
     */
    public function getWizardPendingPaymentAmount(): ?int
    {
        return $this->pendingPaymentAmount;
    }

    protected function getRedirectUrl(): string
    {
        $record = $this->getRecord();
        if ($record instanceof Booking) {
            $record->refresh();

            return BookingResource::calendarUrlForBooking($record);
        }

        return parent::getRedirectUrl();
    }
}
