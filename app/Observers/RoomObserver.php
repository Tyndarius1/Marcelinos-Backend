<?php

namespace App\Observers;

use App\Events\RoomsUpdated;
use App\Models\Room;
use Illuminate\Support\Facades\Log;

/**
 * Broadcasts so frontend stays up to date in real time.
 * Fires on create, update, and delete so the client refetches rooms (Step1, homepage).
 */
class RoomObserver
{
    public function saved(Room $room): void
    {
        $this->broadcastRoomsUpdated();
    }

    public function deleted(Room $room): void
    {
        $this->broadcastRoomsUpdated();
    }

    private function broadcastRoomsUpdated(): void
    {
        try {
            RoomsUpdated::dispatch();
        } catch (\Throwable $exception) {
            Log::warning('RoomsUpdated broadcast failed', [
                'message' => $exception->getMessage(),
            ]);
            report($exception);
        }
    }
}
