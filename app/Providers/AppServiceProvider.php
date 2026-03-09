<?php

namespace App\Providers;

use App\Http\Responses\LoginResponse;
use App\Http\Responses\LogoutResponse;
use App\Models\ActivityLog;
use App\Support\ActivityLogger;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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

        $this->registerAuthActivityListeners();
        $this->registerModelActivityListeners();
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
                ->all();

            if ($model::class === \App\Models\User::class) {
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

            if ($oldValue === $newValue) {
                continue;
            }

            $parts[] = sprintf(
                '%s from %s to %s',
                str_replace('_', ' ', $field),
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

    protected function stringifyValue(mixed $value): string
    {
        if (is_null($value)) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            $stringValue = trim((string) $value);
            return $stringValue === '' ? 'empty' : $stringValue;
        }

        return json_encode($value) ?: 'value';
    }

    protected function resolveSubjectLabel(Model $model): string
    {
        $candidates = ['name', 'title', 'reference_number', 'email', 'full_name'];

        foreach ($candidates as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '#' . (string) $model->getKey();
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
