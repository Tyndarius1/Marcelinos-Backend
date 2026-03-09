<?php

namespace App\Providers;

use App\Http\Responses\LoginResponse;
use App\Http\Responses\LogoutResponse;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Models\Booking;
use App\Models\BlockedDate;
use App\Models\Gallery;
use App\Models\Review;
use App\Models\Room;
use App\Models\Venue;
use App\Observers\BlockedDateObserver;
use App\Observers\BookingObserver;
use App\Observers\GalleryObserver;
use App\Observers\ReviewObserver;
use App\Observers\RoomObserver;
use App\Observers\VenueObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Override Filament's auth responses to support a single login page.
     */
    public array $singletons = [
        LoginResponseContract::class => LoginResponse::class,
        LogoutResponseContract::class => LogoutResponse::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        $this->configureRateLimiting();

        Booking::observe(BookingObserver::class);
        Room::observe(RoomObserver::class);
        Venue::observe(VenueObserver::class);
        BlockedDate::observe(BlockedDateObserver::class);
        Gallery::observe(GalleryObserver::class);
        Review::observe(ReviewObserver::class);
    }

    /**
     * Configure API rate limiting.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('bookings', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('contact', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
