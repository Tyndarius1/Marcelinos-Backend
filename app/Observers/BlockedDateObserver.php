<?php

namespace App\Observers;

use App\Events\BlockedDatesUpdated;
use App\Models\BlockedDate;

class BlockedDateObserver
{
    public function saved(BlockedDate $blockedDate): void
    {
        $this->safeBroadcast();
    }

    public function deleted(BlockedDate $blockedDate): void
    {
        $this->safeBroadcast();
    }

    private function safeBroadcast(): void
    {
        try {
            BlockedDatesUpdated::dispatch();
        } catch (\Throwable $exception) {
            file_put_contents(
                storage_path('logs/laravel.log'),
                now()->toDateTimeString() . ' BlockedDatesUpdated broadcast failed: ' . $exception->getMessage() . "\n",
                FILE_APPEND
            );
        }
    }
}