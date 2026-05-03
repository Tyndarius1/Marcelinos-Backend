<?php

namespace App\Filament\Resources\Bookings\Concerns;

use App\Models\Booking;
use App\Support\BookingFullBalancePayment;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

trait HasBookingPayBalanceHeaderAction
{
    protected function makePayBalanceHeaderAction(): Action
    {
        return Action::make('payBalance')
            ->label(__('Settle remaining balance'))
            ->icon('heroicon-o-banknotes')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading(__('Mark booking as fully paid?'))
            ->modalDescription(__('Records one payment for the full remaining balance and sets payment to Paid. For partial cash amounts, use Payments instead.'))
            ->modalSubmitActionLabel(__('Yes, mark as paid'))
            ->successNotificationTitle(__('Remaining balance recorded. Booking is now paid.'))
            ->visible(fn (): bool => $this->shouldOfferPayBalanceAction())
            ->disabled(fn (): bool => $this->shouldOfferPayBalanceAction() && ! BookingFullBalancePayment::assess($this->getRecord())['allowed'])
            ->tooltip(fn (): ?string => $this->payBalanceBlockedTooltip())
            ->action(function (): void {
                $record = $this->getRecord();
                if (! $record instanceof Booking) {
                    return;
                }

                try {
                    BookingFullBalancePayment::record($record);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title(__('Cannot mark as paid'))
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    throw new Halt;
                }
            });
    }

    protected function shouldOfferPayBalanceAction(): bool
    {
        $record = $this->getRecord();
        if (! $record instanceof Booking || $record->trashed()) {
            return false;
        }

        if ($record->payment_status === Booking::PAYMENT_STATUS_PAID
            || in_array($record->booking_status, [
                Booking::BOOKING_STATUS_CANCELLED,
                Booking::BOOKING_STATUS_COMPLETED,
                Booking::BOOKING_STATUS_FLAGGED,
            ], true)) {
            return false;
        }

        return (float) $record->balance > 0.009;
    }

    protected function payBalanceBlockedTooltip(): ?string
    {
        if (! $this->shouldOfferPayBalanceAction()) {
            return null;
        }

        $record = $this->getRecord();
        if (! $record instanceof Booking) {
            return null;
        }

        $assessment = BookingFullBalancePayment::assess($record);
        if ($assessment['allowed']) {
            return null;
        }

        return $assessment['message']
            ?? match ($assessment['reason']) {
                BookingFullBalancePayment::REASON_NO_BALANCE => __('No remaining balance.'),
                default => __('This booking cannot be marked as paid yet.'),
            };
    }
}
