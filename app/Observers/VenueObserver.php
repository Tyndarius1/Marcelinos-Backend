<?php

namespace App\Observers;

use App\Events\VenuesUpdated;
use App\Models\Venue;
use Illuminate\Support\Facades\Log;

class VenueObserver
{
    public function saved(Venue $venue): void
    {
        $this->safeBroadcast();
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
            $message = trim($exception->getMessage());

            // Prevent huge HTML 404 pages from flooding logs.
            if (str_contains($message, '<!DOCTYPE html>')) {
                $message = 'Received HTML error page instead of broadcast response (likely wrong Reverb/Pusher endpoint).';
            }

            Log::warning('VenuesUpdated broadcast failed', [
                'error' => $message,
                'exception' => get_class($exception),
            ]);
        }
    }
}