<?php

namespace App\Observers;

use App\Events\GalleryUpdated;
use App\Models\Gallery;
use Illuminate\Support\Facades\Log;

class GalleryObserver
{
    public function saved(Gallery $gallery): void
    {
        $this->safeBroadcast($gallery, 'saved');
    }

    public function deleted(Gallery $gallery): void
    {
        $this->safeBroadcast($gallery, 'deleted');
    }

    private function safeBroadcast(Gallery $gallery, string $action): void
    {
        try {
            GalleryUpdated::dispatch();
        } catch (\Throwable $exception) {
            $message = trim($exception->getMessage());

            // Prevent huge HTML 404 responses from flooding the logs
            if (str_contains($message, '<!DOCTYPE html>')) {
                $message = 'Received HTML response instead of broadcast server response (likely Reverb endpoint misconfiguration).';
            }

            Log::warning('GalleryUpdated broadcast failed', [
                'gallery_id' => $gallery->id,
                'action' => $action,
                'error' => $message,
                'exception' => get_class($exception),
            ]);
        }
    }
}