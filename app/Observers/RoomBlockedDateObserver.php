<?php

namespace App\Observers;

use App\Events\BlockedDatesUpdated;
use App\Events\RoomsUpdated;
use App\Models\RoomBlockedDate;
use Illuminate\Support\Facades\Log;
use Throwable;

class RoomBlockedDateObserver
{
    public function saved(RoomBlockedDate $roomBlockedDate): void
    {
        $this->safeBroadcast($roomBlockedDate, 'saved');
    }

    public function deleted(RoomBlockedDate $roomBlockedDate): void
    {
        $this->safeBroadcast($roomBlockedDate, 'deleted');
    }

    private function safeBroadcast(RoomBlockedDate $roomBlockedDate, string $action): void
    {
        try {
            BlockedDatesUpdated::dispatch();
        } catch (Throwable $exception) {
            Log::warning('BlockedDatesUpdated broadcast failed (room block)', [
                'room_blocked_date_id' => $roomBlockedDate->id,
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            RoomsUpdated::dispatch();
        } catch (Throwable $exception) {
            Log::warning('RoomsUpdated broadcast failed (room block)', [
                'room_blocked_date_id' => $roomBlockedDate->id,
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
