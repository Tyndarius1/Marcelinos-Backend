<?php

namespace App\Observers;

use App\Events\AdminDashboardNotification;
use App\Events\BookingStatusUpdated;
use App\Models\Booking;
use App\Models\User;
use App\Support\ActivityLogger;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class BookingObserver
{
    public function created(Booking $booking): void
    {
        Log::info('BookingObserver triggered for booking: ' . $booking->id . ' with reference: ' . $booking->reference_number);

        $users = User::whereIn('role', ['admin', 'staff'])
            ->where('is_active', true)
            ->get();

        Log::info('Users found for notification: ' . $users->count());

        if ($users->isNotEmpty()) {
            Log::info('Sending notification to users');
            foreach ($users as $user) {
                Notification::make()
                    ->title('New Booking Created')
                    ->body("Booking {$booking->reference_number} was created.")
                    ->icon('heroicon-o-calendar-days')
                    ->color('success')
                    ->sendToDatabase($user);
            }
        }

        $this->safeBroadcast(
            fn (): mixed => BookingStatusUpdated::dispatch($booking),
            'BookingStatusUpdated'
        );

        $this->safeBroadcast(
            fn (): mixed => AdminDashboardNotification::dispatch('booking.created', 'New Booking', [
                'reference' => $booking->reference_number,
                'booking_id' => $booking->id,
            ]),
            'AdminDashboardNotification'
        );
    }

    public function updated(Booking $booking): void
    {
        if ($booking->wasChanged('status')) {
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
            );
        }

        $this->safeBroadcast(
            fn (): mixed => BookingStatusUpdated::dispatch($booking),
            'BookingStatusUpdated'
        );
    }

    public function deleted(Booking $booking): void
    {
        //
    }

    private function safeBroadcast(callable $dispatch, string $eventName): void
    {
        try {
            $dispatch();
        } catch (\Throwable $exception) {
            file_put_contents(
                storage_path('logs/laravel.log'),
                now()->toDateTimeString() . ' ' . $eventName . ' broadcast failed: ' . $exception->getMessage() . "\n",
                FILE_APPEND
            );
        }
    }
}

