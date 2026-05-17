<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Concerns\InteractsWithBookingOperations;
use App\Models\Booking;
use App\Models\Guest;
use App\Filament\Resources\Bookings\Schemas\BookingForm;
use App\Models\BookingAssignmentAudit;
use App\Support\BookingFullBalancePayment;
use App\Support\GuestIdentity;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditBooking extends EditRecord
{
    use InteractsWithBookingOperations;

    protected static string $resource = BookingResource::class;

    protected bool $shouldRecordFullPaymentAfterSave = false;
    /** @var array<int> */
    protected array $pendingAssignedRooms = [];

    public function form(Schema $schema): Schema
    {
        $configured = BookingResource::form($schema);

        return $configured->components([
            $this->makeBookingOperationsSectionForEdit(),
            ...array_values($configured->getComponents()),
        ]);
    }

    public function getHeading(): string
    {
        return 'Edit booking';
    }

    public function getSubheading(): ?string
    {
        if (! $this->record instanceof Booking) {
            return null;
        }

        $displayName = $this->record->displayGuestName();
        $guestName = $displayName !== '—' ? $displayName : 'Unknown guest';

        return "{$this->record->reference_number} - {$guestName}";
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->shouldRecordFullPaymentAfterSave = false;

        $guestDataKeys = [
            'guest_first_name',
            'guest_middle_name',
            'guest_last_name',
            'guest_info_email',
            'guest_info_contact_num',
            'guest_gender',
            'guest_is_international',
            'guest_info_country',
            'guest_region',
            'guest_province',
            'guest_municipality',
            'guest_barangay',
        ];

        $incomingGuestData = Arr::only($data, $guestDataKeys);
        foreach ($guestDataKeys as $guestDataKey) {
            unset($data[$guestDataKey]);
        }

        $venues = $data['venues'] ?? [];
        if (! is_array($venues) || empty(array_filter($venues))) {
            $data['venue_event_type'] = null;
        }

        $record = $this->record;
        if ($record instanceof Booking) {
            $nextBookingStatus = (string) ($data['booking_status'] ?? $record->booking_status);
            $rooms = is_array($data['rooms'] ?? null) ? $data['rooms'] : [];
            $recordIsCancelled = (string) $record->booking_status === Booking::BOOKING_STATUS_CANCELLED;
            $requiresAssignedRooms = in_array($nextBookingStatus, [
                Booking::BOOKING_STATUS_OCCUPIED,
                Booking::BOOKING_STATUS_COMPLETED,
            ], true);

            // Allow flexible room assignment - staff can assign any available rooms
            // without strict type/bed spec matching. Room availability is still validated.
            // Note: Room lines remain for billing history, but don't constrain physical assignment.

            $incomingPaymentStatus = (string) ($data['payment_status'] ?? $record->payment_status);
            $wasAlreadyPaid = (string) $record->payment_status === Booking::PAYMENT_STATUS_PAID;
            $hasOutstandingBalance = (float) $record->balance > 0.009;

            // Ensure selected rooms are available for the date range
            if ($rooms !== []) {
                if (BookingForm::hasRoomConflicts($rooms, $data['check_in'] ?? null, $data['check_out'] ?? null, $record)) {
                    throw ValidationException::withMessages([
                        'rooms' => ['One or more selected rooms are not available for the chosen dates.'],
                    ]);
                }

                // Create an audit record if assigned rooms changed from previous assignment
                $record->loadMissing('rooms');
                $existing = $record->rooms->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                $incoming = array_values(array_map('intval', array_filter($rooms)));
                sort($existing);
                sort($incoming);
                if ($existing !== $incoming) {
                    BookingAssignmentAudit::create([
                        'booking_id' => $record->id,
                        'user_id' => Auth::id(),
                        'previous_rooms' => $existing === [] ? null : $existing,
                        'new_rooms' => $incoming === [] ? null : $incoming,
                        'reason' => 'Assigned rooms changed via admin edit',
                    ]);
                }
                // Hold pending rooms for atomic sync in afterSave
                $this->pendingAssignedRooms = $incoming;

                // Recompute totals server-side to keep consistency with UI
                $derived = BookingForm::syncDerivedState(array_merge($data, ['rooms' => $incoming]), $record);
                if (array_key_exists('total_price', $derived)) {
                    $data['total_price'] = $derived['total_price'];
                }
                if (array_key_exists('no_of_days', $derived)) {
                    $data['no_of_days'] = $derived['no_of_days'];
                }
            }

            $canAutoRecordFullPayment = ! in_array($nextBookingStatus, [
                Booking::BOOKING_STATUS_CANCELLED,
                Booking::BOOKING_STATUS_COMPLETED,
            ], true);

            if ($incomingPaymentStatus === Booking::PAYMENT_STATUS_PAID && ! $wasAlreadyPaid && $hasOutstandingBalance && $canAutoRecordFullPayment) {
                $assessment = BookingFullBalancePayment::assess($record);

                if (! $assessment['allowed']) {
                    throw ValidationException::withMessages([
                        'payment_status' => [$assessment['message'] ?? 'This booking cannot be marked as paid yet.'],
                    ]);
                }

                // Let the payment recorder create the payment row + final paid status after save.
                $this->shouldRecordFullPaymentAfterSave = true;
                unset($data['payment_status']);
            }

            // If staff changed dates/rooms/venues, total_price may change. When the payment status
            // field is left untouched, keep it consistent with the actual paid amount.
            $refundPipelineStatuses = [
                Booking::PAYMENT_STATUS_REFUND_PENDING,
                Booking::PAYMENT_STATUS_NON_REFUNDABLE,
                Booking::PAYMENT_STATUS_REFUNDED,
            ];
            if (array_key_exists('payment_status', $data)
                && (string) $incomingPaymentStatus === (string) $record->payment_status
                && ! in_array($incomingPaymentStatus, $refundPipelineStatuses, true)
                && ! in_array((string) $record->payment_status, $refundPipelineStatuses, true)) {
                $newTotal = array_key_exists('total_price', $data) ? (float) $data['total_price'] : (float) $record->total_price;
                $paid = (float) $record->total_paid;
                $computed = Booking::paymentStatusFromAmounts($newTotal, $paid);
                if ($computed !== $incomingPaymentStatus) {
                    $data['payment_status'] = $computed;
                }
            }

            if ($record->guest instanceof Guest) {
                $isInternational = (bool) ($incomingGuestData['guest_is_international'] ?? false);
                $country = trim((string) ($incomingGuestData['guest_info_country'] ?? ''));
                $contactNum = trim((string) ($incomingGuestData['guest_info_contact_num'] ?? ''));

                if ($isInternational && strcasecmp($country, 'Philippines') === 0) {
                    throw ValidationException::withMessages([
                        'guest_info_country' => ['Foreign guests cannot use Philippines as country.'],
                    ]);
                }

                if (! $isInternational && $contactNum === '') {
                    throw ValidationException::withMessages([
                        'guest_info_contact_num' => ['Contact number is required for local guests.'],
                    ]);
                }

                $addressParts = array_values(array_filter([
                    $isInternational ? null : trim((string) ($incomingGuestData['guest_barangay'] ?? '')),
                    $isInternational ? null : trim((string) ($incomingGuestData['guest_municipality'] ?? '')),
                    $isInternational ? null : trim((string) ($incomingGuestData['guest_province'] ?? '')),
                    $isInternational ? null : trim((string) ($incomingGuestData['guest_region'] ?? '')),
                    $isInternational ? ($country !== '' ? $country : null) : 'Philippines',
                ], fn ($value) => is_string($value) && trim($value) !== ''));

                $data['guest_name_snapshot'] = GuestIdentity::fullNameFromParts(
                    trim((string) ($incomingGuestData['guest_first_name'] ?? '')),
                    trim((string) ($incomingGuestData['guest_middle_name'] ?? '')),
                    trim((string) ($incomingGuestData['guest_last_name'] ?? '')),
                );
                $data['guest_email_snapshot'] = strtolower(trim((string) ($incomingGuestData['guest_info_email'] ?? $record->guest->email)));
                $data['guest_contact_snapshot'] = $contactNum;
                $data['guest_address_snapshot'] = $addressParts !== [] ? implode(', ', $addressParts) : null;
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // If there are pending assigned rooms, perform an atomic sync with locks.
        if (! empty($this->pendingAssignedRooms) && $this->record instanceof Booking) {
            try {
                DB::transaction(function () {
                    $booking = $this->record;
                    $roomIds = $this->pendingAssignedRooms;

                    // Lock the room rows to prevent concurrent assignment races
                    \App\Models\Room::whereIn('id', $roomIds)->lockForUpdate()->get();

                    // Re-check availability using authoritative DB state
                    if (BookingForm::hasRoomConflicts($roomIds, $booking->check_in, $booking->check_out, $booking)) {
                        throw ValidationException::withMessages([
                            'rooms' => ['One or more selected rooms are not available for the chosen dates.'],
                        ]);
                    }

                    // Sync assigned rooms atomically
                    $booking->rooms()->sync($roomIds);

                    // Recompute totals and payment status
                    $booking->refresh();
                    $nextStatus = Booking::paymentStatusFromAmounts((float) $booking->total_price, (float) $booking->total_paid);
                    if ($nextStatus !== $booking->payment_status) {
                        $booking->update(['payment_status' => $nextStatus]);
                    }
                });
            } catch (ValidationException $e) {
                Notification::make()
                    ->title('Rooms not assigned')
                    ->body(implode('\n', Arr::flatten(array_values($e->errors()))))
                    ->danger()
                    ->send();

                return;
            }
        }

        if (! $this->shouldRecordFullPaymentAfterSave || ! $this->record instanceof Booking) {
            return;
        }

        try {
            BookingFullBalancePayment::record($this->record);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('Booking saved, but payment was not recorded')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->record->refresh();

        Notification::make()
            ->title('Full payment recorded')
            ->body('The remaining balance was added to Payments and the booking is now marked as paid.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        if ($this->record->trashed()) {
            return [
                ViewAction::make(),
                RestoreAction::make(),
                TypedForceDeleteAction::make(fn (Booking $record): string => $record->reference_number),
            ];
        }

        return [
            ViewAction::make(),
            TypedDeleteAction::make(fn (Booking $record): string => $record->reference_number),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        $record = $this->getRecord();
        if ($record instanceof Booking) {
            $record->refresh();

            return BookingResource::calendarUrlForBooking($record);
        }

        return parent::getRedirectUrl();
    }
}
