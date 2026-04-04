<?php

namespace App\Providers\Filament;

use App\Filament\Livewire\DatabaseNotifications as AppDatabaseNotifications;
use App\Filament\Pages\AdminDashboard;
use App\Filament\Widgets\SessionsByCountryChart;
use App\Filament\Widgets\SessionsByDeviceChart;
use App\Http\Middleware\LogStaffPanelActions;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class StaffPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('staff')
            ->path('staff')
            ->databaseNotifications(true, AppDatabaseNotifications::class, false)
            ->databaseNotificationsPolling('1s')
            ->colors([
                'primary' => Color::Green,
                'gray' => Color::Slate,
            ])
            ->font('Inter')
            ->brandName('Marcelinos')
            ->brandLogoHeight('3.5rem')
            ->brandName(fn () => view('filament.admin.brand'))
            ->brandLogoHeight('2.5rem')
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                AdminDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                SessionsByCountryChart::class,
                SessionsByDeviceChart::class,
                // AccountWidget::class,
                // FilamentInfoWidget::class,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                LogStaffPanelActions::class,
            ]);
    }
}
