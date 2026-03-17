<?php

namespace App\Observers;

use App\Events\GalleryUpdated;
use App\Models\Gallery;
use Throwable;

class GalleryObserver
{
    public function saved(Gallery $gallery): void
    {
        $this->dispatchGalleryUpdated();
    }

    public function deleted(Gallery $gallery): void
    {
        $this->dispatchGalleryUpdated();
    }

    private function dispatchGalleryUpdated(): void
    {
        try {
            GalleryUpdated::dispatch();
        } catch (Throwable $exception) {
            file_put_contents(
                storage_path('logs/laravel.log'),
                now()->toDateTimeString() . ' GalleryUpdated dispatch failed: ' . $exception->getMessage() . "\n",
                FILE_APPEND
            );
        }
    }
}