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
            return 'Received HTML response instead of broadcast server response (likely Reverb endpoint misconfiguration).';
        }

        return $message;
    }
}