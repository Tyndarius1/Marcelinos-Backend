<?php

namespace App\Observers;

use App\Events\BlockedDatesUpdated;
use App\Models\BlockedDate;
use Illuminate\Broadcasting\BroadcastException;

/**
 * Broadcasts so frontend stays up to date when blocked dates change.
 * Broadcast failures (e.g. Reverb not running) do not fail the request.
 */
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
        } catch (BroadcastException $e) {
            // Reverb/Pusher unreachable (e.g. local dev without server) – don't fail the request
            report($e);
        }
    }
}
