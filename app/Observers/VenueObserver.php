<?php

namespace App\Observers;

use App\Events\VenuesUpdated;
use App\Models\Venue;

class VenueObserver
{
    public function saved(Venue $venue): void
    {
        $this->safeBroadcast();
    }

    public function deleted(Venue $venue): void
    {
        $this->safeBroadcast();
    }

    private function safeBroadcast(): void
    {
        try {
            VenuesUpdated::dispatch();
        } catch (\Throwable $exception) {
            file_put_contents(
                storage_path('logs/laravel.log'),
                now()->toDateTimeString() . ' VenuesUpdated broadcast failed: ' . $exception->getMessage() . "\n",
                FILE_APPEND
            );
        }
    }
}