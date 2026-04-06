<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    public function getHeading(): string
    {
        return 'Edit booking';
    }

    public function getSubheading(): ?string
    {
        if (! $this->record instanceof Booking) {
            return null;
        }

        $guestName = $this->record->guest?->full_name ?: 'Unknown guest';

        return "{$this->record->reference_number} - {$guestName}";
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $venues = $data['venues'] ?? [];
        if (! is_array($venues) || empty(array_filter($venues))) {
            $data['venue_event_type'] = null;
        }

        $record = $this->record;
        if ($record instanceof Booking) {
            $nextStatus = (string) ($data['status'] ?? $record->status);
            $rooms = is_array($data['rooms'] ?? null) ? $data['rooms'] : [];
            $requiresAssignedRooms = in_array($nextStatus, [
                Booking::STATUS_OCCUPIED,
                Booking::STATUS_COMPLETED,
            ], true);

            // Allow status/payment updates on frontend-created bookings that do not
            // yet have physical room assignment. Enforce assignment once operation
            // moves to occupied/completed.
            if ($requiresAssignedRooms || $rooms !== []) {
                Booking::validateAssignedRoomsFulfillRoomLines($record, $rooms);
            }
        }

        return $data;
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
}
