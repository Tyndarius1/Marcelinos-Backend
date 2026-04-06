<?php

namespace App\Observers;

use App\Events\AdminDashboardNotification;
use App\Events\BookingStatusUpdated;
use App\Events\FilamentNotificationSound;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\User;
use App\Support\ActivityLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class BookingObserver
{
    public function created(Booking $booking): void
    {
        Log::info('BookingObserver triggered', [
            'booking_id' => $booking->id,
            'reference_number' => $booking->reference_number,
        ]);

        $users = User::whereIn('role', ['admin', 'staff'])
            ->where('is_active', true)
            ->get();

        Log::info('Users found for booking notification', [
            'count' => $users->count(),
            'booking_id' => $booking->id,
        ]);

        if ($users->isNotEmpty()) {
            $booking->loadMissing('guest');
            $bookedByName = trim((string) ($booking->guest?->full_name ?? '')) ?: 'a guest';
            $bookingViewUrl = BookingResource::getUrl('view', ['record' => $booking]);

            $isSuspicious = $booking->no_of_days > 10;

            if ($isSuspicious) {
                $notification = Notification::make()
                    ->warning() // Sets the official warning theme styling
                    ->title('Suspicious Booking Alert')
                    ->body("{$bookedByName} created a suspicious booking for {$booking->no_of_days} days.")
                    ->icon('heroicon-o-exclamation-triangle')
                    ->actions([
                        Action::make('view')
                            ->label('Review Booking')
                            ->button()
                            ->color('danger')
                            ->markAsRead()
                            ->url(BookingResource::getUrl('view', ['record' => $booking])),
                    ])
                    ->persistent();
            } else {
                $notification = Notification::make()
                    ->success() // Sets the official success theme styling
                    ->title('New Booking Created')
                    ->body("{$bookedByName} created a booking.")
                    ->icon('heroicon-o-calendar-days')
                    ->actions([
                        Action::make('view')
                            ->label('View')
                            ->button()
                            ->color('success')
                            ->markAsRead()
                            ->url(BookingResource::getUrl('view', ['record' => $booking])),
                    ]);
            }

            foreach ($users as $user) {
                $notification->sendToDatabase($user)->broadcast($user);
                $this->dispatchNotificationSound($user);
            }
        }

        $this->safeBroadcast(
            fn (): mixed => BookingStatusUpdated::dispatch($booking),
            'BookingStatusUpdated',
            $booking,
            'created'
        );

        $this->safeBroadcast(
            fn (): mixed => AdminDashboardNotification::dispatch('booking.created', 'New Booking', [
                'reference' => $booking->reference_number,
                'booking_id' => $booking->id,
            ]),
            'AdminDashboardNotification',
            $booking,
            'created'
        );
    }

    public function updated(Booking $booking): void
    {
        if ($booking->wasChanged('status')) {

            $statusConfig = [
                'cancelled' => [
                    'title' => 'Booking Cancelled',
                    'body' => "Booking {$booking->reference_number} has been cancelled.",
                    'icon' => 'heroicon-o-x-circle',
                    'color' => 'danger',
                ],
                'completed' => [
                    'title' => 'Booking Completed',
                    'body' => "Booking {$booking->reference_number} has been completed.",
                    'icon' => 'heroicon-o-check-circle',
                    'color' => 'success',
                ],
                'rescheduled' => [
                    'title' => 'Booking Rescheduled',
                    'body' => "Booking {$booking->reference_number} has been rescheduled.",
                    'icon' => 'heroicon-o-arrow-path',
                    'color' => 'warning',
                ],
            ];

            if (array_key_exists($booking->status, $statusConfig)) {
                $users = User::whereIn('role', ['admin', 'staff'])
                    ->where('is_active', true)
                    ->get();

                $config = $statusConfig[$booking->status];

                foreach ($users as $user) {
                    Notification::make()
                        ->title($config['title'])
                        ->body($config['body'])
                        ->icon($config['icon'])
                        ->color($config['color'])
                        ->actions([
                            Action::make('view')
                                ->label('View')
                                ->button()->size('sm')
                                ->color($config['color'])
                                ->markAsRead()
                                ->url(BookingResource::getUrl('view', ['record' => $booking])),

                        ])
                        ->sendToDatabase($user)
                        ->broadcast($user);

                    $this->dispatchNotificationSound($user);
                }
            }

            // Only log if an authenticated user with staff/admin role is present
            $user = auth()->user();
            if ($user && in_array($user->role, ['admin', 'staff'])) {
                ActivityLogger::log(
                    category: 'booking',
                    event: 'booking.status_changed',
                    description: sprintf(
                        'Booking %s status changed from %s to %s.',
                        $booking->reference_number,
                        (string) $booking->getOriginal('status'),
                        (string) $booking->status,
                    ),
                    subject: $booking,
                    meta: [
                        'reference_number' => $booking->reference_number,
                        'old_status' => (string) $booking->getOriginal('status'),
                        'new_status' => (string) $booking->status,
                    ],
                    userId: $user->id,
                );
            }
        }

        $this->safeBroadcast(
            fn (): mixed => BookingStatusUpdated::dispatch($booking),
            'BookingStatusUpdated',
            $booking,
            'updated'
        );
    }

    public function deleted(Booking $booking): void
    {
        $this->safeBroadcast(
            fn () => BookingStatusUpdated::dispatch($booking),
            'BookingStatusUpdated',
            $booking,
            'deleted'
        );
    }

    private function safeBroadcast(callable $dispatch, string $eventName, Booking $booking, string $action): void
    {
        try {
            $dispatch();
        } catch (\Throwable $exception) {
            Log::warning("{$eventName} broadcast failed", [
                'booking_id' => $booking->id,
                'reference_number' => $booking->reference_number,
                'action' => $action,
                'error' => $this->normalizeBroadcastError($exception),
                'exception' => get_class($exception),
            ]);
        }
    }

    private function normalizeBroadcastError(\Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if (str_contains($message, '<!DOCTYPE html>')) {
            return 'Received HTML response instead of broadcast server response (likely Pusher endpoint misconfiguration).';
        }

        return $message;
    }

    private function dispatchNotificationSound(User $user): void
    {
        try {
            event(new FilamentNotificationSound($user));
        } catch (\Throwable $exception) {
            Log::debug('FilamentNotificationSound failed', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
