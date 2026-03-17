<?php

namespace App\Observers;

use App\Events\RoomsUpdated;
use App\Models\Room;

class RoomObserver
{
    public function saved(Room $room): void
    {
        $this->safeBroadcast();
    }

    public function deleted(Room $room): void
    {
        $this->safeBroadcast();
    }

    private function safeBroadcast(): void
    {
        try {
            RoomsUpdated::dispatch();
        } catch (\Throwable $exception) {
            file_put_contents(
                storage_path('logs/laravel.log'),
                now()->toDateTimeString() . ' RoomsUpdated broadcast failed: ' . $exception->getMessage() . "\n",
                FILE_APPEND
            );
        }
    }
}