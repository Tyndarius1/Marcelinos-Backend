<?php

namespace App\Observers;

use App\Events\GalleryUpdated;
use App\Models\Gallery;

class GalleryObserver
{
    public function saved(Gallery $gallery): void
    {
        $this->safeBroadcast();
    }

    public function deleted(Gallery $gallery): void
    {
        $this->safeBroadcast();
    }

    private function safeBroadcast(): void
    {
        try {
            GalleryUpdated::dispatch();
        } catch (\Throwable $exception) {
            file_put_contents(
                storage_path('logs/laravel.log'),
                now()->toDateTimeString() . ' GalleryUpdated broadcast failed: ' . $exception->getMessage() . "\n",
                FILE_APPEND
            );
        }
    }
}