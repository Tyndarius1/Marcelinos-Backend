<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;

class AdminDashboard extends Dashboard
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.pages.admin-dashboard';
}
