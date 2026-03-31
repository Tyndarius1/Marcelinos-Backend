<?php

namespace App\Observers;

use App\Events\AdminDashboardNotification;
use App\Events\BookingStatusUpdated;
use App\Models\Booking;
use App\Models\User;
use App\Support\ActivityLogger;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use App\Filament\Resources\Bookings\BookingResource;
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

            foreach ($users as $user) {
                Notification::make()
                    ->title('New Booking Created')
                    ->body("{$bookedByName} created a booking.")
                    ->icon('heroicon-o-calendar-days')
                    ->color('success')
                    ->url($bookingViewUrl)
                    ->actions([
                        Action::make('view')
                            ->label('View Booking')
                            ->button()
                            ->url($bookingViewUrl)
                    ])
                    ->sendToDatabase($user)
                    ->broadcast($user);
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

            if ($booking->status === 'cancelled') {
                $users = User::whereIn('role', ['admin', 'staff'])
                    ->where('is_active', true)
                    ->get();
                
                foreach ($users as $user) {
                    Notification::make()
                        ->title('Booking Cancelled')
                        ->body("Booking {$booking->reference_number} has been cancelled.")
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->actions([
                            Action::make('view')
                                ->label('View Booking')
                                ->button()
                                ->color('danger')
                                ->url(BookingResource::getUrl('view', ['record' => $booking]))
                        ])
                        ->sendToDatabase($user)
                        ->broadcast($user);
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
            return 'Received HTML response instead of broadcast server response (likely Reverb endpoint misconfiguration).';
        }

        return $message;
    }
}