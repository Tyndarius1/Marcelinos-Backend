<?php

namespace App\Observers;

use App\Events\AdminDashboardNotification;
use App\Events\BookingStatusUpdated;
use App\Events\FilamentNotificationSound;
use App\Filament\Resources\Bookings\BookingResource;
use App\Jobs\SyncBookingToGoogleSheet;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Notifications\Slack\BookingLifecycleSlackNotification;
use App\Services\RefundNotificationService;
use App\Support\ActivityLogger;
use App\Support\BookingDoubleBookAlert;
use App\Support\BookingLifecycleActions;
use App\Support\SlackBookingAlerts;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BookingObserver
{
    public function created(Booking $booking): void
    {
        Log::info('BookingObserver triggered', [
            'booking_id' => $booking->id,
            'reference_number' => $booking->reference_number,
        ]);

        if ($booking->booking_status === Booking::BOOKING_STATUS_PENDING_VERIFICATION) {
            Log::info('Booking pending email verification — staff alerts deferred', [
                'booking_id' => $booking->id,
            ]);

            return;
        }

        $this->dispatchNewBookingStaffAlerts($booking);

        SyncBookingToGoogleSheet::dispatch(
            bookingId: (int) $booking->id,
            referenceNumber: (string) $booking->reference_number,
        );
    }

    /**
     * Filament DB notifications, Slack, double-book check, and realtime events for a newly active public booking.
     */
    private function dispatchNewBookingStaffAlerts(Booking $booking): void
    {
        $users = User::whereIn('role', ['admin', 'staff'])
            ->where('is_active', true)
            ->get();

        Log::info('Users found for booking notification', [
            'count' => $users->count(),
            'booking_id' => $booking->id,
        ]);

        if ($users->isNotEmpty()) {
            $booking->loadMissing('guest');
            $bookedByName = ($booking->displayGuestName() !== '—' ? $booking->displayGuestName() : '') ?: 'a guest';

            $isSuspicious = $booking->no_of_days > 10;

            if ($isSuspicious) {
                $notification = Notification::make()
                    ->warning()
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
                    ->success()
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

        SlackBookingAlerts::notify(new BookingLifecycleSlackNotification($booking, 'created'));

        BookingDoubleBookAlert::scheduleCheckAfterSave($booking);
    }

    public function updated(Booking $booking): void
    {
        if ($booking->wasChanged('booking_status')
            && (string) $booking->booking_status === Booking::BOOKING_STATUS_COMPLETED) {
            $this->ensureCompletionRoomChecklists($booking);
        }

        if ($booking->wasChanged('booking_status')
            && (string) $booking->getOriginal('booking_status') === Booking::BOOKING_STATUS_PENDING_VERIFICATION
            && (string) $booking->booking_status === Booking::BOOKING_STATUS_RESERVED) {
            $this->dispatchNewBookingStaffAlerts($booking);
        }

        $user = auth()->user();
        $isStaffOrAdmin = $user && in_array($user->role, ['admin', 'staff'], true);

        if ($isStaffOrAdmin) {
            $changes = $this->collectBookingChanges($booking);

            if (! empty($changes)) {
                ActivityLogger::log(
                    category: 'booking',
                    event: 'booking.updated',
                    description: sprintf(
                        '%s updated booking %s (%s).',
                        $user->name,
                        $booking->reference_number,
                        $this->formatBookingChangesForDescription($changes),
                    ),
                    subject: $booking,
                    meta: [
                        'reference_number' => $booking->reference_number,
                        'changed_by_user_id' => (int) $user->id,
                        'changed_by_user_name' => (string) $user->name,
                        'changes' => $changes,
                    ],
                    userId: (int) $user->id,
                );
            }
        }

        if ($booking->wasChanged('booking_status') || $booking->wasChanged('payment_status')) {

            $bookingStatusConfig = [
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

            if (array_key_exists((string) $booking->booking_status, $bookingStatusConfig)) {
                $users = User::whereIn('role', ['admin', 'staff'])
                    ->where('is_active', true)
                    ->get();

                $config = $bookingStatusConfig[(string) $booking->booking_status];

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

            if (array_key_exists((string) $booking->booking_status, $bookingStatusConfig)) {
                SlackBookingAlerts::notify(new BookingLifecycleSlackNotification($booking, (string) $booking->booking_status));
            }

            if (in_array((string) $booking->payment_status, [Booking::PAYMENT_STATUS_PAID, Booking::PAYMENT_STATUS_PARTIAL], true)) {
                SlackBookingAlerts::notify(new BookingLifecycleSlackNotification($booking, (string) $booking->payment_status));
            }

            // Only log if an authenticated user with staff/admin role is present
            if ($isStaffOrAdmin) {
                ActivityLogger::log(
                    category: 'booking',
                    event: 'booking.status_changed',
                    description: sprintf(
                        '%s changed booking %s (stay %s → %s, payment %s → %s).',
                        $user->name,
                        $booking->reference_number,
                        (string) $booking->getOriginal('booking_status'),
                        (string) $booking->booking_status,
                        (string) $booking->getOriginal('payment_status'),
                        (string) $booking->payment_status,
                    ),
                    subject: $booking,
                    meta: [
                        'reference_number' => $booking->reference_number,
                        'changed_by_user_id' => (int) $user->id,
                        'changed_by_user_name' => (string) $user->name,
                        'old_booking_status' => (string) $booking->getOriginal('booking_status'),
                        'new_booking_status' => (string) $booking->booking_status,
                        'old_payment_status' => (string) $booking->getOriginal('payment_status'),
                        'new_payment_status' => (string) $booking->payment_status,
                    ],
                    userId: $user->id,
                );
            }
        }

        if ($booking->wasChanged('payment_status')) {
            app(RefundNotificationService::class)->handleRefundPipelinePaymentStatusTransition($booking);
        }

        $this->safeBroadcast(
            fn (): mixed => BookingStatusUpdated::dispatch($booking),
            'BookingStatusUpdated',
            $booking,
            'updated'
        );

        BookingDoubleBookAlert::scheduleCheckAfterSave($booking);

        SyncBookingToGoogleSheet::dispatch(
            bookingId: (int) $booking->id,
            referenceNumber: (string) $booking->reference_number,
        );
    }

    private function ensureCompletionRoomChecklists(Booking $booking): void
    {
        try {
            BookingLifecycleActions::ensureCompletionRoomChecklists($booking);
        } catch (\Throwable $e) {
            Log::warning('Failed generating room completion checklist', [
                'booking_id' => $booking->id,
                'reference_number' => $booking->reference_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function collectBookingChanges(Booking $booking): array
    {
        $changes = [];

        foreach ($booking->getChanges() as $field => $newValue) {
            if (in_array($field, ['updated_at', 'created_at'], true)) {
                continue;
            }

            $oldValue = $booking->getOriginal($field);

            if ((string) $oldValue === (string) $newValue) {
                continue;
            }

            $changes[$field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $changes;
    }

    private function formatBookingChangesForDescription(array $changes): string
    {
        $parts = [];

        foreach ($changes as $field => $values) {
            $parts[] = sprintf(
                '%s: %s -> %s',
                Str::headline((string) $field),
                $this->stringifyChangeValue($values['old'] ?? null),
                $this->stringifyChangeValue($values['new'] ?? null),
            );
        }

        return implode('; ', array_slice($parts, 0, 3));
    }

    private function stringifyChangeValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            $text = trim((string) $value);

            return $text === '' ? 'empty' : $text;
        }

        $encoded = json_encode($value);

        return $encoded === false ? 'value' : $encoded;
    }

    public function deleted(Booking $booking): void
    {
        $this->safeBroadcast(
            fn () => BookingStatusUpdated::dispatch($booking),
            'BookingStatusUpdated',
            $booking,
            'deleted'
        );

        SlackBookingAlerts::notify(new BookingLifecycleSlackNotification($booking, 'deleted'));

        SyncBookingToGoogleSheet::dispatch(
            bookingId: (int) $booking->id,
            referenceNumber: (string) $booking->reference_number,
            removeOnly: true,
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
