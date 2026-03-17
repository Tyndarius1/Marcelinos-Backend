<?php

namespace App\Observers;

use App\Events\BlockedDatesUpdated;
use App\Models\BlockedDate;
use Illuminate\Support\Facades\Log;
use Throwable;

class BlockedDateObserver
{
    public function saved(BlockedDate $blockedDate): void
    {
        $this->safeBroadcast($blockedDate, 'saved');
    }

    public function deleted(BlockedDate $blockedDate): void
    {
        $this->safeBroadcast($blockedDate, 'deleted');
    }

    private function safeBroadcast(BlockedDate $blockedDate, string $action): void
    {
        try {
            BlockedDatesUpdated::dispatch();
        } catch (Throwable $exception) {
            Log::warning('BlockedDatesUpdated broadcast failed', [
                'blocked_date_id' => $blockedDate->id,
                'action' => $action,
                'error' => $this->normalizeBroadcastError($exception),
                'exception' => $exception::class,
            ]);
        }
    }

    private function normalizeBroadcastError(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if (str_contains($message, '<!DOCTYPE html>')) {
            return 'Received HTML response instead of broadcast server response (likely Reverb endpoint misconfiguration).';
        }

        return $message;
    }
}