<?php

namespace App\Providers;

use App\Filament\Resources\Amenities\Pages\ListAmenities;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Guests\Pages\ListGuests;
use App\Filament\Resources\Rooms\Pages\ListRooms;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Filament\Resources\Venues\Pages\ListVenues;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\LogoutResponse;
use App\Listeners\RecordBookingWizardInitialPayment;
use App\Models\ActivityLog;
use App\Models\Amenity;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Gallery;
use App\Models\Guest;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomBlockedDate;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBlockedDate;
use App\Observers\BlockedDateObserver;
use App\Observers\BookingObserver;
use App\Observers\GalleryObserver;
use App\Observers\ReviewObserver;
use App\Observers\RoomBlockedDateObserver;
use App\Observers\RoomObserver;
use App\Observers\VenueBlockedDateObserver;
use App\Observers\VenueObserver;
use App\Support\ActivityLogger;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Resources\Events\RecordCreated;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

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
        $this->registerTableTotalsHooks();
        $this->registerRoomCalendarToolbarButton();

        Booking::observe(BookingObserver::class);
        Room::observe(RoomObserver::class);
        Venue::observe(VenueObserver::class);
        BlockedDate::observe(BlockedDateObserver::class);
        RoomBlockedDate::observe(RoomBlockedDateObserver::class);
        VenueBlockedDate::observe(VenueBlockedDateObserver::class);
        Gallery::observe(GalleryObserver::class);
        Review::observe(ReviewObserver::class);

        $this->registerAuthActivityListeners();
        $this->registerModelActivityListeners();

        Event::listen(RecordCreated::class, RecordBookingWizardInitialPayment::class);
    }

    protected function registerTableTotalsHooks(): void
    {
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_START,
            fn (): string => '<div class="text-sm font-medium">Total Staff: '.User::query()->where('role', 'staff')->count().'</div>',
            scopes: [ListStaff::class],
        );

        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_START,
            fn (): string => '<div class="text-sm font-medium">Total Guests: '.Guest::query()->count().'</div>',
            scopes: [ListGuests::class],
        );

        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_START,
            fn (): string => '<div class="text-sm font-medium">Total Venues: '.Venue::query()->count().'</div>',
            scopes: [ListVenues::class],
        );

        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_START,
            fn (): string => '<div class="text-sm font-medium">Total Rooms: '.Room::query()->count().'</div>',
            scopes: [ListRooms::class],
        );

        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_START,
            fn (): string => '<div class="text-sm font-medium">Total Amenities: '.Amenity::query()->count().'</div>',
            scopes: [ListAmenities::class],
        );
    }

    protected function registerRoomCalendarToolbarButton(): void
    {
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_COLUMN_MANAGER_TRIGGER_BEFORE,
            fn (): View => view('filament.bookings.room-calendar-toolbar-button'),
            scopes: [ListBookings::class],
        );
    }

    protected function registerAuthActivityListeners(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            ActivityLogger::log(
                category: 'auth',
                event: 'user.login',
                description: 'logged in.',
                subject: $event->user,
                userId: $event->user?->id,
            );
        });

        Event::listen(Logout::class, function (Logout $event): void {
            ActivityLogger::log(
                category: 'auth',
                event: 'user.logout',
                description: 'logged out.',
                subject: $event->user,
                userId: $event->user?->id,
            );
        });
    }

    protected function registerModelActivityListeners(): void
    {
        Event::listen('eloquent.created: *', function (string $eventName, array $data): void {
            $this->logModelLifecycleEvent('created', $data[0] ?? null);
        });

        Event::listen('eloquent.updated: *', function (string $eventName, array $data): void {
            $this->logModelLifecycleEvent('updated', $data[0] ?? null);
        });

        Event::listen('eloquent.deleted: *', function (string $eventName, array $data): void {
            $this->logModelLifecycleEvent('deleted', $data[0] ?? null);
        });
    }

    protected function logModelLifecycleEvent(string $lifecycle, mixed $model): void
    {
        if (! $model instanceof Model) {
            return;
        }

        if ($model instanceof ActivityLog) {
            return;
        }

        if (! str_starts_with($model::class, 'App\\Models\\')) {
            return;
        }

        if (! auth()->check()) {
            return;
        }

        if (app()->runningInConsole()) {
            return;
        }

        if ($model instanceof Booking && $lifecycle === 'updated' && $model->wasChanged('status')) {
            // Booking status has a dedicated, clearer audit event.
            return;
        }

        $modelName = class_basename($model);
        $subjectLabel = $this->resolveSubjectLabel($model);

        $changes = [];
        if ($lifecycle === 'updated') {
            $changes = collect($model->getChanges())
                ->except(['updated_at', 'created_at'])
                ->reject(fn (mixed $newValue, string $field) => $this->valuesAreEquivalent($model->getOriginal($field), $newValue))
                ->all();

            if ($model::class === User::class) {
                $meaningfulUserFields = array_diff(array_keys($changes), [
                    'remember_token',
                ]);

                // Login/logout may touch auth tokens; do not treat those as admin edits.
                if (empty($meaningfulUserFields)) {
                    return;
                }
            }

            if (empty($changes)) {
                return;
            }

            if ($model instanceof Review && array_keys($changes) === ['is_approved']) {
                // ReviewObserver already writes a dedicated approval/unapproval audit event.
                return;
            }
        }

        $description = sprintf('%s %s: %s.', $modelName, $lifecycle, $subjectLabel);

        if ($lifecycle === 'updated') {
            $description = $this->buildUpdatedDescription($modelName, $subjectLabel, $changes, $model);
        }

        ActivityLogger::log(
            category: 'resource',
            event: sprintf('resource.%s', $lifecycle),
            description: $description,
            subject: $model,
            meta: [
                'model' => $model::class,
                'id' => $model->getKey(),
                'label' => $subjectLabel,
                'changes' => $changes,
            ],
        );
    }

    protected function buildUpdatedDescription(string $modelName, string $subjectLabel, array $changes, Model $model): string
    {
        $parts = [];

        foreach ($changes as $field => $newValue) {
            $oldValue = $model->getOriginal($field);

            if ($this->valuesAreEquivalent($oldValue, $newValue)) {
                continue;
            }

            $parts[] = sprintf(
                '%s from %s to %s',
                $this->humanizeFieldName($field),
                $this->stringifyValue($oldValue),
                $this->stringifyValue($newValue),
            );

            if (count($parts) >= 3) {
                break;
            }
        }

        if (empty($parts)) {
            return sprintf('%s updated: %s.', $modelName, $subjectLabel);
        }

        return sprintf('%s updated: %s (%s).', $modelName, $subjectLabel, implode('; ', $parts));
    }

    protected function valuesAreEquivalent(mixed $oldValue, mixed $newValue): bool
    {
        if ($oldValue === $newValue) {
            return true;
        }

        if (is_numeric($oldValue) && is_numeric($newValue)) {
            return abs((float) $oldValue - (float) $newValue) < 0.0000001;
        }

        return false;
    }

    protected function stringifyValue(mixed $value): string
    {
        if (is_null($value)) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            $stringValue = trim((string) $value);

            return $stringValue === '' ? 'empty' : $stringValue;
        }

        return json_encode($value) ?: 'value';
    }

    protected function humanizeFieldName(string $field): string
    {
        $label = str_replace('_', ' ', trim($field));

        // "is_site_review" -> "site review" for cleaner audit phrasing.
        if (str_starts_with($label, 'is ')) {
            $label = substr($label, 3);
        }

        return trim($label);
    }

    protected function resolveSubjectLabel(Model $model): string
    {
        $candidates = ['name', 'title', 'reference_number', 'email', 'full_name', 'date'];

        foreach ($candidates as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '#'.(string) $model->getKey();
    }

    /**
     * Configure API rate limiting.
     * All limiters return JSON for API consistency and include Retry-After when available.
     */
    protected function configureRateLimiting(): void
    {
        $jsonTooManyRequests = function (Request $request, array $headers) {
            return response()->json([
                'message' => 'Too many requests. Please slow down and try again later.',
            ], 429, $headers);
        };

        RateLimiter::for('api', function (Request $request) use ($jsonTooManyRequests) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip())
                ->response($jsonTooManyRequests);
        });

        RateLimiter::for('bookings', function (Request $request) use ($jsonTooManyRequests) {
            return Limit::perMinute(10)
                ->by($request->ip())
                ->response($jsonTooManyRequests);
        });

        RateLimiter::for('contact', function (Request $request) use ($jsonTooManyRequests) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response($jsonTooManyRequests);
        });

        RateLimiter::for('booking_otp', function (Request $request) use ($jsonTooManyRequests) {
            $booking = $request->route('booking');
            $ref = $booking instanceof Booking ? $booking->reference_number : (string) ($request->route('booking') ?? '');

            return Limit::perMinutes(15, 3)
                ->by($request->ip().':'.$ref)
                ->response($jsonTooManyRequests);
        });
    }
}
