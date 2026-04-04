<?php

namespace App\Filament\Livewire;

use Filament\Livewire\DatabaseNotifications as FilamentDatabaseNotifications;
use Illuminate\Contracts\View\View;

/**
 * Uses a local Blade view so we can add Livewire's wire:poll.keep-alive modifier.
 * Without keep-alive, Livewire throttles polling ~95% while the tab is in the background,
 * which delays badge updates and the notification sound fallback.
 */
class DatabaseNotifications extends FilamentDatabaseNotifications
{
    public function render(): View
    {
        return view('filament.livewire.database-notifications');
    }
}
