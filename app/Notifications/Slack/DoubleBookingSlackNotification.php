<?php

namespace App\Notifications\Slack;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Queue\SerializesModels;

class DoubleBookingSlackNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<array{id: int, reference_number: string, guest_name: string, check_in: string, check_out: string, status: string}>  $conflicts
     */
    public function __construct(
        public Booking $booking,
        public array $conflicts,
    ) {}

    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $booking = $this->booking;
        $booking->loadMissing('guest');

        $guestName = $booking->displayGuestName();
        $checkIn = $booking->check_in?->timezone(Booking::timezoneManila())->format('M j, Y g:i A') ?? '—';
        $checkOut = $booking->check_out?->timezone(Booking::timezoneManila())->format('M j, Y g:i A') ?? '—';

        $lines = [];
        foreach ($this->conflicts as $c) {
            $lines[] = '• *'.($c['reference_number'] ?? '—').'* · '.$c['guest_name'].' · '.$c['check_in'].' → '.$c['check_out'].' · status `'.$c['status'].'`';
        }
        $conflictText = implode("\n", $lines);

        $fallback = '⚠️ Double booking: '.$booking->reference_number.' overlaps '.$guestName;

        $message = (new SlackMessage)
            ->username(config('app.name').' bookings')
            ->emoji(':warning:')
            ->text($fallback)
            ->headerBlock('⚠️ Double booking detected')
            ->sectionBlock(function ($block) use ($booking, $guestName, $checkIn, $checkOut, $conflictText): void {
                $block->text(
                    "*This booking*\n".
                    '`'.$booking->reference_number.'` · '.$guestName."\n".
                    $checkIn.' → '.$checkOut."\n\n".
                    "*Overlapping bookings*\n".$conflictText
                )->markdown();
            });

        $adminUrl = $this->adminBookingUrl();

        return $message
            ->when($adminUrl !== '', fn (SlackMessage $msg) => $msg->actionsBlock(function ($block) use ($adminUrl): void {
                $block->button('Open in admin')->url($adminUrl)->primary();
            }));
    }

    private function adminBookingUrl(): string
    {
        try {
            return BookingResource::getUrl('view', ['record' => $this->booking]);
        } catch (\Throwable) {
            return '';
        }
    }
}
