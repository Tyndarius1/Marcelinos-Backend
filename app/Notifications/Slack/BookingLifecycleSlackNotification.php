<?php

namespace App\Notifications\Slack;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\BookingRoomLine;
use App\Models\Payment;
use App\Models\Room;
use App\Support\BookingPricing;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class BookingLifecycleSlackNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $this->booking->loadMissing(['guest', 'rooms', 'venues', 'roomLines']);

        $booking = $this->booking;
        $eventIcon = $this->eventIcon();
        $header = $eventIcon.' '.$this->headerText();
        $guestName = $booking->displayGuestName();
        $checkIn = $this->formatBookingDateTimeManila($booking->check_in);
        $checkOut = $this->formatBookingDateTimeManila($booking->check_out);
        $total = number_format((float) $booking->total_price, 2).' PHP';

        $paymentLine = '';
        if (in_array($this->event, [Booking::PAYMENT_STATUS_PAID, Booking::PAYMENT_STATUS_PARTIAL], true)) {
            /** @var Payment|null $latest */
            $latest = $booking->payments()->latest()->first();
            if ($latest) {
                $paid = number_format((float) $latest->partial_amount, 2);
                $target = number_format((float) $latest->total_amount, 2);
                $provIcon = $this->paymentProviderStatusIcon($latest->provider_status);
                $paymentLine = "Provider: {$latest->provider} · Ref: {$latest->provider_ref} · Paid: {$paid} / {$target} PHP · Status: {$provIcon} {$latest->provider_status} · Fully paid: ".($latest->is_fullypaid ? '✅ yes' : '⏳ no');
            }
        }

        $roomsRequested = $this->formatRoomLinesForSlack($booking);
        $venuesDetail = $this->formatVenuesForSlack($booking);
        $venueEventDetail = $this->formatVenueEventTypeForSlack($booking);
        $assignedRooms = $this->formatAssignedRoomsForSlack($booking);

        $adminUrl = $this->adminBookingUrl();

        $fallback = "{$header} · {$booking->reference_number} · {$guestName}";

        $stay = Booking::bookingStatusOptions()[(string) $booking->booking_status] ?? (string) $booking->booking_status;
        $pay = Booking::paymentStatusOptions()[(string) $booking->payment_status] ?? (string) $booking->payment_status;
        $statusCell = $this->bookingStayIcon((string) $booking->booking_status).' '.$stay
            .' · '.$this->paymentStatusIcon((string) $booking->payment_status).' '.$pay;

        $plan = filled($booking->online_payment_plan) ? $booking->online_payment_plan : '—';
        $xendit = filled($booking->xendit_invoice_id) ? $booking->xendit_invoice_id : '—';
        $xenditAmountPaid = $this->formatXenditAmountPaidForSlack($booking);

        $message = (new SlackMessage)
            ->username(config('app.name').' bookings')
            ->emoji($eventIcon)
            ->text($fallback)
            ->headerBlock($header)
            ->usingBlockKitTemplate(json_encode([
                'blocks' => [
                    $this->bookingDetailsTableBlock(
                        (string) $booking->reference_number,
                        $guestName,
                        $checkIn,
                        $checkOut,
                        $statusCell,
                        $total,
                        $booking->payment_method ?: '—',
                        $plan,
                        $xendit,
                        $xenditAmountPaid,
                        $roomsRequested,
                        $venuesDetail,
                        $venueEventDetail,
                        $assignedRooms,
                    ),
                ],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        if ($paymentLine !== '') {
            $message->dividerBlock()->sectionBlock(function ($block) use ($paymentLine): void {
                $block->text("*💳 Latest payment*\n{$paymentLine}")->markdown();
            });
        }

        return $message
            ->contextBlock(function ($block) use ($booking): void {
                $block->text('Receipt token: '.substr((string) $booking->receipt_token, 0, 8).'… ID: '.$booking->id);
            })
            ->when($adminUrl !== '', fn (SlackMessage $msg) => $msg->actionsBlock(function ($block) use ($adminUrl): void {
                $block->button('Open in admin')->url($adminUrl)->primary();
            }));
    }

    /**
     * @return array{type: string, column_settings?: list<array<string, mixed>>, rows: list<list<array<string, mixed>>>}
     */
    private function bookingDetailsTableBlock(
        string $referenceNumber,
        string $guestName,
        string $checkIn,
        string $checkOut,
        string $statusCell,
        string $total,
        string $paymentMethod,
        string $plan,
        string $xendit,
        string $xenditAmountPaid,
        string $roomsRequested,
        string $venuesDetail,
        string $venueEventDetail,
        string $assignedRooms,
    ): array {
        $L = fn (string $text) => $this->slackTableRichTextCell($text, bold: true);
        $V = fn (string $text) => $this->slackTableRichTextCell($text);

        $rows = [
            [$L('Field'), $L('Value')],
            [$L('🔖 Reference'), $this->slackTableRichTextCell($referenceNumber, code: true)],
            [$L('👤 Guest'), $V($guestName)],
            [$L('🛬 Check-in (PH Time)'), $V($checkIn)],
            [$L('🛫 Check-out (PH Time)'), $V($checkOut)],
            [$L('📌 Stay / Payment'), $V($statusCell)],
            [$L('💰 Total'), $V($total)],
            [$L('💳 Payment method'), $V($paymentMethod)],
            [$L('🌐 Online payment plan'), $V($plan)],
            [$L('📄 Xendit invoice'), $V($xendit)],
            [$L('💰 Xendit amount paid'), $V($xenditAmountPaid)],
            [$L('🛏️ Rooms requested'), $V($roomsRequested)],
            [$L('🏢 Venues'), $V($venuesDetail)],
        ];

        if ($venueEventDetail !== '—') {
            $rows[] = [$L('🎪 Venue event type'), $V($venueEventDetail)];
        }

        if ($assignedRooms !== '—') {
            $rows[] = [$L('🔑 Assigned rooms'), $V($assignedRooms)];
        }

        return [
            'type' => 'table',
            'column_settings' => [
                ['is_wrapped' => true],
                ['is_wrapped' => true],
            ],
            'rows' => $rows,
        ];
    }

    private function formatBookingDateTimeManila(?Carbon $dateTime): string
    {
        if ($dateTime === null) {
            return '—';
        }

        return $dateTime->timezone(Booking::timezoneManila())->format('F j, Y g:i A');
    }

    private function formatRoomLinesForSlack(Booking $booking): string
    {
        if ($booking->roomLines->isEmpty()) {
            return '—';
        }

        return $booking->roomLines
            ->map(function (BookingRoomLine $line): string {
                $label = $line->displayLabel();
                $q = max(1, (int) $line->quantity);

                return $q > 1 ? "{$q}× {$label}" : $label;
            })
            ->implode("\n");
    }

    private function formatVenuesForSlack(Booking $booking): string
    {
        if ($booking->venues->isEmpty()) {
            return '—';
        }

        return $booking->venues
            ->pluck('name')
            ->map(fn (mixed $name) => trim((string) $name))
            ->filter()
            ->implode(', ');
    }

    private function formatVenueEventTypeForSlack(Booking $booking): string
    {
        $raw = $booking->venue_event_type;
        if (! filled($raw)) {
            return '—';
        }

        $options = BookingPricing::venueEventTypeOptions();
        $normalized = BookingPricing::normalizeVenueEventType($raw);

        return $options[$normalized] ?? ucfirst((string) $raw);
    }

    private function formatAssignedRoomsForSlack(Booking $booking): string
    {
        if ($booking->rooms->isEmpty()) {
            return '—';
        }

        $booking->rooms->loadMissing('bedSpecifications');

        return $booking->rooms
            ->map(fn (Room $room) => $room->adminSelectLabel())
            ->implode("\n");
    }

    /**
     * Resolves the guest amount tied to Xendit: webhook rows use provider "xendit" and
     * {@see Payment::provider_ref} = invoice id; Filament / wizard rows often omit
     * provider but still represent the Xendit charge. Next, the xendit_webhook_events table
     * stores Xendit's paid_amount. If nothing matches, we fall back to the invoice
     * amount implied by {@see Booking::online_payment_plan} (same rules as {@see \App\Http\Controllers\API\BookingController::createXenditInvoiceForBooking}).
     *
     * @return array{paid: float, denom: float, is_partial: bool, source_note: string}|null
     */
    private function resolveXenditPaidContext(Booking $booking): ?array
    {
        $invoiceId = trim((string) ($booking->xendit_invoice_id ?? ''));

        if ($invoiceId !== '') {
            /** @var Payment|null $byInvoiceRef */
            $byInvoiceRef = $booking->payments()
                ->where('provider_ref', $invoiceId)
                ->latest()
                ->first();
            if ($byInvoiceRef !== null) {
                return $this->xenditContextFromPayment($booking, $byInvoiceRef);
            }
        }

        /** @var Payment|null $explicit */
        $explicit = $booking->payments()
            ->where('provider', 'xendit')
            ->latest()
            ->first();
        if ($explicit !== null) {
            return $this->xenditContextFromPayment($booking, $explicit);
        }

        $isOnline = ($booking->payment_method ?? '') === 'online';
        if ($isOnline && $invoiceId !== '') {
            $payments = $booking->payments()->orderBy('id')->get();
            if ($payments->count() === 1) {
                /** @var Payment $only */
                $only = $payments->first();

                return $this->xenditContextFromPayment($booking, $only);
            }
        }

        if ($invoiceId !== '') {
            $fromWebhookEvents = $this->xenditPaidContextFromWebhookEventsTable($booking, $invoiceId);
            if ($fromWebhookEvents !== null) {
                return $fromWebhookEvents;
            }
        }

        if ($invoiceId !== '') {
            $planned = $this->plannedXenditInvoiceAmountPhp($booking);
            if ($planned !== null) {
                $bookingTotal = (float) $booking->total_price;
                if ($bookingTotal <= 0.0) {
                    return null;
                }

                $isPartial = $planned + 0.009 < $bookingTotal;

                return [
                    'paid' => $planned,
                    'denom' => $bookingTotal,
                    'is_partial' => $isPartial,
                    'source_note' => ' · from payment plan (no Xendit-linked payment row yet)',
                ];
            }
        }

        return null;
    }

    /**
     * Fallback when Payment rows are missing: uses paid_amount from persisted Xendit callbacks.
     *
     * @return array{paid: float, denom: float, is_partial: bool, source_note: string}|null
     */
    private function xenditPaidContextFromWebhookEventsTable(Booking $booking, string $invoiceId): ?array
    {
        $ref = trim((string) $booking->reference_number);

        $row = DB::table('xendit_webhook_events')
            ->where('invoice_id', $invoiceId)
            ->whereNotNull('paid_amount')
            ->where('paid_amount', '>', 0)
            ->when($ref !== '', function ($query) use ($ref): void {
                $query->where('external_id', $ref);
            })
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->first();

        if ($row === null && $ref !== '') {
            $row = DB::table('xendit_webhook_events')
                ->where('invoice_id', $invoiceId)
                ->whereNotNull('paid_amount')
                ->where('paid_amount', '>', 0)
                ->orderByDesc('received_at')
                ->orderByDesc('id')
                ->first();
        }

        if ($row === null) {
            return null;
        }

        $paid = (float) $row->paid_amount;
        if ($paid <= 0.0) {
            return null;
        }

        $bookingTotal = (float) $booking->total_price;
        $denom = $bookingTotal > 0.0 ? $bookingTotal : $paid;
        $isPartial = $bookingTotal > 0.0 && $paid + 0.009 < $bookingTotal;

        return [
            'paid' => $paid,
            'denom' => $denom,
            'is_partial' => $isPartial,
            'source_note' => ' · from Xendit webhook',
        ];
    }

    /**
     * @return array{paid: float, denom: float, is_partial: bool, source_note: string}
     */
    private function xenditContextFromPayment(Booking $booking, Payment $payment): array
    {
        $paid = (float) $payment->partial_amount;
        $denom = (float) $payment->total_amount;
        if ($denom <= 0.0) {
            $denom = (float) $booking->total_price;
        }

        $isPartial = $booking->payment_status === Booking::PAYMENT_STATUS_PARTIAL
            || ! $payment->is_fullypaid;

        return [
            'paid' => $paid,
            'denom' => $denom,
            'is_partial' => $isPartial,
            'source_note' => '',
        ];
    }

    /**
     * Mirrors {@see \App\Http\Controllers\API\BookingController::createXenditInvoiceForBooking} charge logic.
     */
    private function plannedXenditInvoiceAmountPhp(Booking $booking): ?float
    {
        $plan = (string) ($booking->online_payment_plan ?? '');
        $totalAmount = (float) $booking->total_price;
        if ($totalAmount <= 0.0) {
            return null;
        }

        $partialPercent = $this->extractPartialPercentageFromPlan($plan);
        if ($partialPercent !== null) {
            return max(1.0, (float) round($totalAmount * ($partialPercent / 100), 2));
        }

        if ($plan === 'full' || $plan === '') {
            return max(1.0, $totalAmount);
        }

        return null;
    }

    private function extractPartialPercentageFromPlan(string $plan): ?int
    {
        if (preg_match('/^partial_([1-9]|[1-9][0-9])$/', $plan, $matches) !== 1) {
            return null;
        }

        $percent = (int) ($matches[1] ?? 0);
        if ($percent <= 0 || $percent >= 100) {
            return null;
        }

        return $percent;
    }

    private function formatXenditAmountPaidForSlack(Booking $booking): string
    {
        $ctx = $this->resolveXenditPaidContext($booking);
        if ($ctx === null) {
            return '—';
        }

        $formattedPaid = number_format($ctx['paid'], 2).' PHP';

        if (! $ctx['is_partial']) {
            return $formattedPaid.$ctx['source_note'];
        }

        $denom = $ctx['denom'];
        if ($denom <= 0.0) {
            return $formattedPaid.$ctx['source_note'];
        }

        $pct = round(($ctx['paid'] / $denom) * 100, 1);

        return "{$formattedPaid} · {$pct}% of booking total".$ctx['source_note'];
    }

    /**
     * @return array{type: string, elements: list<array<string, mixed>>}
     */
    private function slackTableRichTextCell(string $text, bool $bold = false, bool $code = false): array
    {
        $style = [];
        if ($bold) {
            $style['bold'] = true;
        }
        if ($code) {
            $style['code'] = true;
        }

        $textElement = [
            'type' => 'text',
            'text' => $text,
        ];
        if ($style !== []) {
            $textElement['style'] = $style;
        }

        return [
            'type' => 'rich_text',
            'elements' => [
                [
                    'type' => 'rich_text_section',
                    'elements' => [$textElement],
                ],
            ],
        ];
    }

    private function headerText(): string
    {
        return match ($this->event) {
            'created' => 'New booking',
            'deleted' => 'Booking deleted',
            Booking::BOOKING_STATUS_CANCELLED => 'Booking cancelled',
            Booking::BOOKING_STATUS_RESCHEDULED => 'Booking rescheduled',
            Booking::BOOKING_STATUS_COMPLETED => 'Booking completed',
            Booking::PAYMENT_STATUS_PAID => 'Payment received (paid)',
            Booking::PAYMENT_STATUS_PARTIAL => 'Payment received (partial)',
            default => 'Booking update ('.$this->event.')',
        };
    }

    private function eventIcon(): string
    {
        return match ($this->event) {
            'created' => '✨',
            'deleted' => '🗑️',
            Booking::BOOKING_STATUS_CANCELLED => '🚫',
            Booking::BOOKING_STATUS_RESCHEDULED => '🔄',
            Booking::BOOKING_STATUS_COMPLETED => '🎉',
            Booking::PAYMENT_STATUS_PAID => '✅',
            Booking::PAYMENT_STATUS_PARTIAL => '🔶',
            default => '📣',
        };
    }

    private function bookingStayIcon(string $bookingStatus): string
    {
        return match ($bookingStatus) {
            Booking::BOOKING_STATUS_RESERVED => '📋',
            Booking::BOOKING_STATUS_OCCUPIED => '🛏️',
            Booking::BOOKING_STATUS_COMPLETED => '🏁',
            Booking::BOOKING_STATUS_CANCELLED => '❌',
            Booking::BOOKING_STATUS_RESCHEDULED => '🔄',
            default => '❔',
        };
    }

    private function paymentStatusIcon(string $paymentStatus): string
    {
        return match ($paymentStatus) {
            Booking::PAYMENT_STATUS_UNPAID => '⏳',
            Booking::PAYMENT_STATUS_PARTIAL => '🔶',
            Booking::PAYMENT_STATUS_PAID => '✅',
            default => '❔',
        };
    }

    private function paymentProviderStatusIcon(?string $status): string
    {
        $s = strtolower((string) $status);

        return match (true) {
            $s === '' => '🏷️',
            str_contains($s, 'paid') || str_contains($s, 'succeed') || str_contains($s, 'success') || $s === 'completed' || $s === 'settled' => '✅',
            str_contains($s, 'pend') || str_contains($s, 'await') || str_contains($s, 'process') => '⏳',
            str_contains($s, 'fail') || str_contains($s, 'cancel') || str_contains($s, 'expir') || str_contains($s, 'void') => '❌',
            str_contains($s, 'partial') => '🔶',
            default => '🏷️',
        };
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
