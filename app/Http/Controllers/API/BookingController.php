<?php

namespace App\Http\Controllers\API;

use App\Events\BookingCancelled;
use App\Events\BookingEmailVerified;
use App\Events\BookingRescheduled;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\StoreBookingRequest;
use App\Models\Booking;
use App\Models\BookingRoomLine;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Venue;
use App\Services\BookingActionOtpService;
use App\Support\BookingDuplicateGuard;
use App\Support\BookingPricing;
use App\Support\BookingSpecialDiscount;
use App\Support\CancellationPolicy;
use App\Support\RoomInventoryGroupAvailability;
use App\Support\RoomInventoryGroupKey;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BookingController extends Controller
{
    public function __construct(
        private BookingActionOtpService $bookingActionOtpService,
        private BookingDuplicateGuard $bookingDuplicateGuard,
    ) {}

    /**
     * Send email OTP for cancel or reschedule.
     */
    public function sendBookingOtp(Request $request, Booking $booking)
    {
        if ($this->expireIfNeeded($booking)) {
            return response()->json([
                'message' => 'Booking expired after 3 days without payment and was cancelled.',
            ], 422);
        }

        $request->validate([
            'purpose' => 'required|in:cancel,reschedule',
        ]);

        $purpose = (string) $request->input('purpose');

        if ($purpose === BookingActionOtpService::PURPOSE_CANCEL) {
            $canCancel = $booking->booking_status === Booking::BOOKING_STATUS_PENDING_VERIFICATION
                || $booking->booking_status === Booking::BOOKING_STATUS_RESCHEDULED
                || (
                    $booking->booking_status === Booking::BOOKING_STATUS_RESERVED
                    && in_array($booking->payment_status, [
                        Booking::PAYMENT_STATUS_UNPAID,
                        Booking::PAYMENT_STATUS_PARTIAL,
                        Booking::PAYMENT_STATUS_PAID,
                        Booking::PAYMENT_STATUS_REFUND_PENDING,
                        Booking::PAYMENT_STATUS_NON_REFUNDABLE,
                        Booking::PAYMENT_STATUS_REFUNDED,
                    ], true)
                );

            if (! $canCancel) {
                return response()->json([
                    'message' => 'Booking cannot be cancelled in its current state.',
                ], 422);
            }
        } else {
            if ($booking->booking_status === Booking::BOOKING_STATUS_PENDING_VERIFICATION) {
                return response()->json([
                    'message' => 'Confirm your booking by email before rescheduling.',
                ], 422);
            }

            if (in_array($booking->booking_status, [Booking::BOOKING_STATUS_CANCELLED, Booking::BOOKING_STATUS_COMPLETED], true)) {
                return response()->json([
                    'message' => 'Cannot reschedule this booking.',
                ], 422);
            }
        }

        $this->bookingActionOtpService->send($booking, $purpose);

        return response()->json([
            'message' => 'Verification code sent.',
        ]);
    }

    /**
     * Display all bookings (paginated).
     */
    public function index(Request $request)
    {
        try {
            Gate::authorize('viewAny', Booking::class);
            $perPage = min((int) $request->query('per_page', 15), 50);

            $bookings = Booking::with(['guest', 'rooms', 'venues', 'roomLines'])
                ->orderByDesc('created_at')
                ->paginate($perPage);

            return response()->json($bookings, 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error retrieving bookings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a booking by opaque receipt token (public receipt URL — non-guessable).
     */
    public function showByReceiptToken(string $token)
    {
        try {
            $booking = $this->findReceiptBooking($token);

            if (! $booking) {
                return response()->json([
                    'message' => 'Booking not found',
                ], 404);
            }

            return $this->jsonReceiptForBooking($booking);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error retrieving booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a booking by reference number (legacy links and testimonial flow).
     */
    public function showByReferenceNumber(string $reference)
    {
        try {
            $booking = $this->findReceiptBooking($reference);

            if (! $booking) {
                return response()->json([
                    'message' => 'Booking not found',
                ], 404);
            }

            return $this->jsonReceiptForBooking($booking);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error retrieving booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function downloadBillingStatementPdf(string $token)
    {
        try {
            $booking = $this->findReceiptBooking($token);

            if (! $booking) {
                return response()->json([
                    'message' => 'Booking not found',
                ], 404);
            }

            if ($response = $this->rejectIfPendingVerification($booking)) {
                return $response;
            }

            $statement = $this->buildBillingStatementData($booking);

            $pdf = Pdf::loadView('billing-statements.step5', $statement)
                ->setPaper('a4', 'portrait');

            $filename = sprintf(
                'marcelinos-billing-statement-%s-%s.pdf',
                Str::slug((string) $booking->reference_number),
                now()->format('Ymd-His'),
            );

            return $pdf->download($filename);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error generating billing statement PDF',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a guest billing statement by booking id.
     *
     * Access is granted only when:
     * - `?token=` matches the stored SHA-256 hash in `access_token` using `hash_equals()`
     * - optional `token_expires_at` is not in the past
     *
     * Returns 403 on any access failure.
     */
    public function showBillingByAccessToken(int $id, Request $request): JsonResponse
    {
        $rawToken = $request->query('token');
        if (! is_string($rawToken) || trim($rawToken) === '') {
            return response()->json([
                'message' => 'Invalid or expired billing link.',
                'error' => 'billing_token_invalid',
            ], 403);
        }

        $booking = Booking::query()->with(['guest', 'rooms', 'venues', 'roomLines', 'payments'])
            ->find($id);

        if (! $booking) {
            return response()->json([
                'message' => 'Invalid or expired billing link.',
                'error' => 'billing_token_invalid',
            ], 403);
        }

        $storedHash = (string) ($booking->access_token ?? '');
        $computedHash = hash('sha256', $rawToken);

        if ($storedHash === '' || ! hash_equals($storedHash, $computedHash)) {
            return response()->json([
                'message' => 'Invalid or expired billing link.',
                'error' => 'billing_token_invalid',
            ], 403);
        }

        if ($booking->token_expires_at !== null && $booking->token_expires_at->isPast()) {
            return response()->json([
                'message' => 'Billing link expired.',
                'error' => 'token_expired',
            ], 403);
        }

        if ($response = $this->rejectIfPendingVerification($booking)) {
            return $response;
        }

        return $this->jsonReceiptForBooking($booking);
    }

    /**
     * Confirm a successful online payment from receipt return flow.
     * Uses opaque receipt token only and updates booking status to paid/partial.
     */
    public function confirmReceiptPayment(Request $request, string $token): JsonResponse
    {
        if (! Str::isUuid($token)) {
            return response()->json([
                'message' => 'Invalid receipt token.',
            ], 422);
        }

        $booking = Booking::with(['guest', 'rooms', 'venues', 'roomLines'])
            ->where('receipt_token', $token)
            ->first();

        if (! $booking) {
            return response()->json([
                'message' => 'Booking not found',
            ], 404);
        }

        if ($response = $this->rejectIfPendingVerification($booking)) {
            return $response;
        }

        $validated = $request->validate([
            'payment_mode' => ['nullable', 'string', 'regex:/^(full|partial_([1-9]|[1-9][0-9]))$/'],
        ]);

        if (in_array($booking->booking_status, [Booking::BOOKING_STATUS_CANCELLED, Booking::BOOKING_STATUS_COMPLETED], true)) {
            return response()->json([
                'message' => 'Booking cannot be updated for payment in its current state.',
            ], 422);
        }

        $paymentMode = (string) ($validated['payment_mode'] ?? 'full');
        if ($paymentMode !== 'full' && ! $this->isAllowedPartialPlan($paymentMode)) {
            return response()->json([
                'message' => 'Selected partial payment option is not allowed.',
            ], 422);
        }
        if (empty($booking->payment_method)) {
            $booking->update(['payment_method' => 'online']);
        }
        if (empty($booking->online_payment_plan) && $paymentMode !== '') {
            $booking->update(['online_payment_plan' => $paymentMode]);
        }

        // Persist a payment row immediately so receipts can display "Amount paid" even
        // if webhook delivery is delayed. Webhook will upsert the same provider_ref.
        $invoiceId = trim((string) ($booking->xendit_invoice_id ?? ''));
        $chargeAmount = $this->plannedPaymentAmountForMode($booking, $paymentMode);
        $this->upsertConfirmedPaymentRecord($booking, $invoiceId, $chargeAmount);

        $nextPayment = $this->extractPartialPercentage($paymentMode) !== null
            ? Booking::PAYMENT_STATUS_PARTIAL
            : Booking::PAYMENT_STATUS_PAID;

        if ($booking->payment_status !== $nextPayment) {
            $booking->update(['payment_status' => $nextPayment]);
        }
        Cache::forget($this->pendingOnlinePaymentCacheKey((int) $booking->id));

        return response()->json([
            'success' => true,
            'booking' => $booking->fresh(['guest', 'rooms', 'venues', 'roomLines']),
        ]);
    }

    private function jsonReceiptForBooking(Booking $booking): JsonResponse
    {
        $this->expireIfNeeded($booking);

        $hasTestimonial = $booking->reviews()->exists();

        $pendingVerification = $booking->booking_status === Booking::BOOKING_STATUS_PENDING_VERIFICATION;

        if (! $pendingVerification) {
            $this->ensureBookingQrExists($booking);
        }

        $filename = $booking->qr_code ? basename($booking->qr_code) : null;

        $bookingPayload = $booking->fresh(['guest', 'rooms', 'venues', 'roomLines']);
        $amountPaid = (float) $bookingPayload->total_paid;
        $balance = max(0, (float) $bookingPayload->balance);
        $amountDueNow = $this->resolveAmountDueNow($bookingPayload);
        $discountTarget = BookingSpecialDiscount::resolveDiscountTarget($bookingPayload, (string) ($bookingPayload->special_discount_target ?? null));
        $billingStatementPdfUrl = $this->billingStatementPdfUrl($bookingPayload);

        if ($billingStatementPdfUrl !== null) {
            $bookingPayload->setAttribute('billing_statement_pdf_url', $billingStatementPdfUrl);
        }

        $paymentSettings = $this->paymentSettingsConfig();
        $partialOptions = $paymentSettings['partial_payment_options'] ?? [];
        $downPaymentPercent = isset($partialOptions[0]) ? (int) $partialOptions[0] : 30;

        return response()->json([
            'booking' => $bookingPayload,
            'discount' => [
                'has_special_discount' => BookingSpecialDiscount::hasDiscount($bookingPayload),
                'target' => $discountTarget,
                'target_label' => BookingSpecialDiscount::targetLabel($discountTarget),
                'amount' => (float) ($bookingPayload->special_discount_amount_applied ?? 0),
                'gross_total' => (float) BookingSpecialDiscount::grossTotal($bookingPayload),
            ],
            'payment' => [
                'method' => (string) ($bookingPayload->payment_method ?? 'cash'),
                'plan' => (string) ($bookingPayload->online_payment_plan ?? ''),
                'invoice_id' => (string) ($bookingPayload->xendit_invoice_id ?? ''),
                'invoice_url' => (string) ($bookingPayload->xendit_invoice_url ?? ''),
                'can_retry' => $this->canRetryOnlinePayment($bookingPayload),
                'amount_paid' => $amountPaid,
                'balance' => $balance,
                'amount_due_now' => $amountDueNow,
            ],
            'cancellation_policy' => [
                'fee_percent' => CancellationPolicy::feePercent(),
            ],
            'cancellation_refund' => CancellationPolicy::cancelledBookingRefundTransparency($bookingPayload),
            'unpaid_expires_at' => $bookingPayload->unpaidExpiresAt()?->toIso8601String(),
            'unpaid_expiry_days' => Booking::UNPAID_EXPIRY_DAYS,
            'use_messenger_deposit_instructions' => $bookingPayload->useMessengerDepositInstructions(),
            'down_payment_notice_applies' => $bookingPayload->downPaymentNoticeApplies(),
            'down_payment_notice_min_lead_days' => Booking::DOWN_PAYMENT_NOTICE_MIN_LEAD_DAYS,
            'down_payment_percent' => $downPaymentPercent,
            'qr_code_url' => $filename ? url("/qr-image/{$filename}") : null,
            'billing_token' => $bookingPayload->id !== null
                ? Cache::get('booking.billing_token.'.(int) $bookingPayload->id)
                : null,
            'billing_statement_pdf_url' => $billingStatementPdfUrl,
            'has_testimonial' => $hasTestimonial,
            'email_verification_required' => $pendingVerification,
        ], 200);
    }

    private function billingStatementPdfUrl(Booking $booking): ?string
    {
        $referenceNumber = trim((string) $booking->reference_number);

        if ($referenceNumber === '') {
            return null;
        }

        $ttlHours = max(1, (int) config('booking.billing_statement_url_ttl_hours', 24));

        return URL::temporarySignedRoute(
            'billing-statements.pdf',
            now()->addHours($ttlHours),
            ['booking' => $referenceNumber],
        );
    }

    /**
     * Build the data array used by the billing statement PDF.
     * The PDF should always reflect server-side booking state, not editable DOM state.
     */
    private function buildBillingStatementData(Booking $booking): array
    {
        $booking->loadMissing(['guest', 'rooms.bedSpecifications', 'venues', 'roomLines', 'payments']);

        $logoPath = public_path('brand-logo.webp');
        $logoSrc = null;
        if (is_file($logoPath)) {
            $logoSrc = 'data:image/webp;base64,'.base64_encode((string) file_get_contents($logoPath));
        }

        $guest = $booking->guest;
        $guestName = $guest
            ? trim(implode(' ', array_filter([
                $guest->first_name,
                $guest->middle_name,
                $guest->last_name,
            ])))
            : '—';

        $guestAddressParts = $guest
            ? array_filter([
                $guest->street,
                $guest->barangay,
                $guest->municipality,
                $guest->province,
                $guest->region,
                $guest->country,
            ])
            : [];
        $guestAddress = ! empty($guestAddressParts) ? implode(', ', $guestAddressParts) : '—';

        $billingUnits = max(
            1,
            (int) ($booking->no_of_days ?: ($booking->check_in && $booking->check_out
                ? $booking->check_in->diffInDays($booking->check_out)
                : 1))
        );
        $billingUnitLabel = $billingUnits === 1 ? 'night/day' : 'nights/days';

        $roomItems = $booking->roomLines->isNotEmpty()
            ? $booking->roomLines->map(function (BookingRoomLine $line) use ($billingUnits): array {
                $unitPrice = (float) $line->unit_price_per_night;

                return [
                    'label' => $line->displayLabel(),
                    'quantity' => (int) $line->quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $unitPrice * max(1, (int) $line->quantity) * $billingUnits,
                ];
            })->values()->all()
            : $booking->rooms->map(function (Room $room) use ($billingUnits): array {
                $room->loadMissing(['bedSpecifications']);
                $unitPrice = (float) $room->price;

                return [
                    'label' => trim($room->name).' ('.$room->typeDashBedSummary().')',
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                    'line_total' => $unitPrice * $billingUnits,
                ];
            })->values()->all();

        $venueEventType = $this->normalizeVenueEventType((string) ($booking->venue_event_type ?? 'wedding'));
        $venueItems = $booking->venues->map(function (Venue $venue) use ($billingUnits, $venueEventType): array {
            $unitPrice = $this->venueUnitPrice($venue, $venueEventType);

            return [
                'label' => $venue->name,
                'event_type' => $this->venueEventTypeLabel($venueEventType),
                'capacity' => (int) $venue->capacity,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $billingUnits,
            ];
        })->values()->all();

        $roomSubtotal = array_reduce($roomItems, fn (float $sum, array $item): float => $sum + (float) $item['line_total'], 0.0);
        $venueSubtotal = array_reduce($venueItems, fn (float $sum, array $item): float => $sum + (float) $item['line_total'], 0.0);
        $computedSubtotal = $roomSubtotal + $venueSubtotal;

        $grandTotal = (float) $booking->total_price;
        $originalTotal = (float) ($booking->special_discount_original_total_price ?? 0);
        if ($originalTotal <= 0) {
            $originalTotal = max($computedSubtotal, $grandTotal);
        }

        $discountApplied = (float) ($booking->special_discount_amount_applied ?? max(0, $originalTotal - $grandTotal));
        $discountTarget = BookingSpecialDiscount::resolveDiscountTarget($booking, (string) ($booking->special_discount_target ?? null));
        $discountTargetLabel = BookingSpecialDiscount::targetLabel($discountTarget);
        $amountPaid = (float) $booking->total_paid;
        $balance = (float) $booking->balance;

        $paymentSettings = $this->paymentSettingsConfig();
        $partialOptions = $paymentSettings['partial_payment_options'] ?? [];
        $downPaymentPercent = isset($partialOptions[0]) ? (int) $partialOptions[0] : 30;
        $downPaymentAmount = max(0.0, (float) round($grandTotal * ($downPaymentPercent / 100), 2));
        $unpaidExpiresAt = $booking->unpaidExpiresAt();
        $depositDueLabel = $unpaidExpiresAt
            ? $unpaidExpiresAt->timezone('Asia/Manila')->format('F j, Y g:i A')
            : '—';

        $messengerBase = $this->messengerChatUrl();
        $messengerMessage = implode("\n", [
            "Hello Marcelino's Resort Hotel!",
            '',
            "I would like to settle my {$downPaymentPercent}% deposit for this booking.",
            'Reference No.: '.((string) $booking->reference_number ?: '—'),
            'Guest Name: '.($guestName !== '' ? $guestName : '—'),
            'Check-in: '.($booking->check_in ? $booking->check_in->timezone('Asia/Manila')->format('F j, Y g:i A') : '—'),
            'Check-out: '.($booking->check_out ? $booking->check_out->timezone('Asia/Manila')->format('F j, Y g:i A') : '—'),
            'Reservation Total: ₱'.number_format($grandTotal, 2),
            "Deposit Amount ({$downPaymentPercent}%): ₱".number_format($downPaymentAmount, 2),
            '',
            'Thank you!',
        ]);
        $messengerLink = $messengerBase !== ''
            ? $messengerBase.(str_contains($messengerBase, '?') ? '&' : '?').'text='.rawurlencode($messengerMessage)
            : null;

        $bookingStatusLower = strtolower((string) $booking->booking_status);
        $paymentStatusLower = strtolower((string) $booking->payment_status);
        $showMessengerDepositBlock = in_array($bookingStatusLower, ['reserved', 'rescheduled'], true)
            && $paymentStatusLower === Booking::PAYMENT_STATUS_UNPAID;

        $qrCodeDataUri = null;
        if ($booking->qr_code && Storage::disk('public')->exists($booking->qr_code)) {
            $qrCodePath = $booking->qr_code;
            $mimeType = Storage::disk('public')->mimeType($qrCodePath) ?: 'image/svg+xml';
            $qrCodeDataUri = 'data:'.$mimeType.';base64,'.base64_encode((string) Storage::disk('public')->get($qrCodePath));
        }

        return [
            'booking' => $booking,
            'guestName' => $guestName,
            'guestAddress' => $guestAddress,
            'bookingTypeLabel' => $this->bookingTypeLabel($booking),
            'bookingStatusLabel' => Booking::bookingStatusOptions()[(string) $booking->booking_status] ?? ucfirst((string) $booking->booking_status),
            'paymentStatusLabel' => Booking::paymentStatusOptions()[(string) $booking->payment_status] ?? ucfirst((string) $booking->payment_status),
            'paymentMethodLabel' => $this->paymentMethodLabel((string) ($booking->payment_method ?? 'cash'), (string) ($booking->online_payment_plan ?? '')),
            'venueEventTypeLabel' => $this->venueEventTypeLabel($venueEventType),
            'billingUnits' => $billingUnits,
            'billingUnitLabel' => $billingUnitLabel,
            'roomItems' => $roomItems,
            'venueItems' => $venueItems,
            'roomSubtotal' => $roomSubtotal,
            'venueSubtotal' => $venueSubtotal,
            'computedSubtotal' => $computedSubtotal,
            'originalTotal' => $originalTotal,
            'discountApplied' => $discountApplied,
            'discountTarget' => $discountTarget,
            'discountTargetLabel' => $discountTargetLabel,
            'grandTotal' => $grandTotal,
            'amountPaid' => $amountPaid,
            'balance' => $balance,
            'issuedAt' => $booking->created_at?->timezone('Asia/Manila') ?? now(),
            'checkIn' => $booking->check_in,
            'checkOut' => $booking->check_out,
            'qrCodeDataUri' => $qrCodeDataUri,
            'logoSrc' => $logoSrc,
            'downPaymentPercent' => $downPaymentPercent,
            'downPaymentAmount' => $downPaymentAmount,
            'depositDueLabel' => $depositDueLabel,
            'showMessengerDepositBlock' => $showMessengerDepositBlock,
            'messengerLink' => $messengerLink,
            'cancellationRefund' => CancellationPolicy::cancelledBookingRefundTransparency($booking),
        ];
    }

    private function messengerChatUrl(): string
    {
        $configured = trim((string) env('FRONTEND_MESSENGER_CHAT_URL', ''));

        return $configured !== ''
            ? $configured
            : 'https://m.me/61557457680496';
    }

    private function bookingTypeLabel(Booking $booking): string
    {
        $hasRooms = $booking->rooms->isNotEmpty() || $booking->roomLines->isNotEmpty();
        $hasVenues = $booking->venues->isNotEmpty();

        if ($hasRooms && $hasVenues) {
            return 'Room + Venue';
        }

        if ($hasVenues) {
            return 'Venue Booking';
        }

        if ($hasRooms) {
            return 'Room Booking';
        }

        return 'Booking';
    }

    private function paymentMethodLabel(string $method, string $plan): string
    {
        $normalizedMethod = strtolower(trim($method));

        if ($normalizedMethod === 'online') {
            if (preg_match('/^partial_(\d+)$/', $plan, $matches)) {
                return 'Online (Partial '.$matches[1].'%)';
            }

            return 'Online (Full)';
        }

        return $normalizedMethod !== ''
            ? ucwords(str_replace('_', ' ', $normalizedMethod))
            : 'Cash';
    }

    private function normalizeVenueEventType(string $eventType): string
    {
        $normalized = strtolower(trim($eventType));

        return match ($normalized) {
            'seminar', 'meeting', 'meeting_staff' => 'meeting_staff',
            'birthday' => 'birthday',
            'wedding' => 'wedding',
            'others' => 'others',
            default => 'wedding',
        };
    }

    private function venueEventTypeLabel(string $eventType): string
    {
        return match ($this->normalizeVenueEventType($eventType)) {
            'birthday' => 'Birthday',
            'meeting_staff' => 'Meeting / Staff',
            'others' => 'Others',
            default => 'Birthday',
        };
    }

    private function venueUnitPrice(Venue $venue, string $eventType): float
    {
        return match ($this->normalizeVenueEventType($eventType)) {
            'birthday' => (float) $venue->birthday_price,
            'meeting_staff' => (float) $venue->meeting_staff_price,
            default => (float) $venue->wedding_price,
        };
    }

    /**
     * Resolve a public booking identifier used by receipt pages.
     * Supports both receipt token (UUID) and legacy reference number.
     */
    private function findReceiptBooking(string $identifier): ?Booking
    {
        return Booking::with(['guest', 'rooms', 'venues', 'roomLines'])
            ->where('receipt_token', $identifier)
            ->orWhere('reference_number', $identifier)
            ->first();
    }

    private function rejectIfPendingVerification(Booking $booking): ?JsonResponse
    {
        if ($booking->booking_status === Booking::BOOKING_STATUS_PENDING_VERIFICATION) {
            return response()->json([
                'message' => 'Please confirm your booking using the link sent to your email.',
                'error' => 'email_verification_required',
            ], 403);
        }

        return null;
    }

    /**
     * Signed link from {@see VerifyBookingEmail}: activate hold, optional Xendit invoice.
     */
    public function verifyEmail(Request $request, Booking $booking): JsonResponse|RedirectResponse
    {
        if ($booking->booking_status === Booking::BOOKING_STATUS_CANCELLED) {
            return response()->json([
                'message' => 'This booking is no longer active.',
            ], 410);
        }

        if ($booking->booking_status === Booking::BOOKING_STATUS_RESERVED && $booking->email_verified_at !== null) {
            $paymentUrl = (string) ($booking->xendit_invoice_url ?? '');

            return $this->verificationSuccessResponse(
                $booking->fresh(['guest', 'rooms', 'venues', 'roomLines']),
                is_string($paymentUrl) && $paymentUrl !== '' ? $paymentUrl : null,
            );
        }

        if ($booking->booking_status !== Booking::BOOKING_STATUS_PENDING_VERIFICATION) {
            return response()->json([
                'message' => 'This booking does not require email verification.',
            ], 422);
        }

        $booking->load(['guest', 'roomLines', 'venues']);
        $guest = $booking->guest;
        if (! $guest) {
            return response()->json([
                'message' => 'Guest record missing.',
            ], 422);
        }

        $checkIn = $booking->check_in;
        $checkOut = $booking->check_out;
        $hasRoomLines = $booking->roomLines->isNotEmpty();
        $venueIds = $booking->venues->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        if ($hasRoomLines) {
            $roomLinesPayload = $booking->roomLines->map(fn ($line) => [
                'room_type' => $line->room_type,
                'inventory_group_key' => $line->inventory_group_key,
                'quantity' => (int) $line->quantity,
                'unit_price' => (float) $line->unit_price_per_night,
            ])->all();
            $roomLineError = $this->validateGuestRoomLines($roomLinesPayload, $checkIn, $checkOut, $booking->id);
            if ($roomLineError !== null) {
                $booking->delete();

                return response()->json([
                    'message' => 'These dates are no longer available for your selected rooms. Please start a new booking.',
                    'error' => 'availability_lost',
                ], 409);
            }
        }

        if ($venueIds !== []) {
            $availableVenueIds = Venue::whereIn('id', $venueIds)
                ->availableBetween(
                    $checkIn,
                    $checkOut,
                    $booking->id,
                    $booking->venue_event_type,
                    $booking->venues->isNotEmpty(),
                )
                ->pluck('id')
                ->all();

            if (count(array_diff($venueIds, $availableVenueIds)) > 0) {
                $booking->delete();

                return response()->json([
                    'message' => 'These dates are no longer available for your selected venues. Please start a new booking.',
                    'error' => 'availability_lost',
                ], 409);
            }
        }

        $booking->update([
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'email_verified_at' => now(),
        ]);

        $booking->refresh();
        $booking->generateQrCode();

        // Broadcast email verification event to frontend
        BookingEmailVerified::dispatch($booking);

        $fresh = $booking->fresh(['guest', 'rooms', 'venues', 'roomLines']);

        $paymentUrl = $this->provisionOnlineInvoiceForPublicBooking($booking->fresh(['guest']), $guest);

        return $this->verificationSuccessResponse(
            $booking->fresh(['guest', 'rooms', 'venues', 'roomLines']),
            $paymentUrl,
        );
    }

    private function verificationSuccessResponse(Booking $booking, ?string $paymentUrl = null): JsonResponse|RedirectResponse
    {
        if (request()->expectsJson()) {
            return response()->json([
                'message' => 'Email verified. Your booking is confirmed.',
                'booking' => $booking,
                'payment_url' => $paymentUrl,
                'email_verification_required' => false,
            ]);
        }

        if (is_string($paymentUrl) && trim($paymentUrl) !== '') {
            return redirect()->away($paymentUrl);
        }

        $base = rtrim((string) config('app.frontend_url'), '/');
        $billingToken = (string) request()->query('billing_token', '');

        if ($billingToken === '') {
            // Backward-compatible fallback for any verification links that
            // don't include the billing token query param.
            $receiptToken = (string) $booking->receipt_token;
            return redirect()->away("{$base}/booking-receipt/{$receiptToken}?verified=1");
        }

        return redirect()->away(
            "{$base}/billing/{$booking->id}?token=".urlencode($billingToken)."&verified=1"
        );
    }

    private function provisionOnlineInvoiceForPublicBooking(Booking $booking, Guest $guest): ?string
    {
        if ((string) ($booking->payment_method ?? '') !== 'online') {
            return null;
        }

        $plan = (string) ($booking->online_payment_plan ?? 'full');
        if ($plan !== 'full' && ! $this->isAllowedPartialPlan($plan)) {
            return null;
        }

        if (! filter_var(env('PAYMENT_ONLINE_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $invoice = $this->createXenditInvoiceForBooking($booking, $guest, $plan);
        $paymentUrl = $invoice['invoice_url'] ?? null;

        if (! is_string($paymentUrl) || trim($paymentUrl) === '') {
            return null;
        }

        $booking->update([
            'xendit_invoice_id' => (string) ($invoice['id'] ?? ''),
            'xendit_invoice_url' => $paymentUrl,
        ]);

        Cache::put($this->pendingOnlinePaymentCacheKey((int) $booking->id), true, now()->addHours(2));

        return $paymentUrl;
    }

    public function store(StoreBookingRequest $request)
    {
        $validated = $request->validated();

        $roomIds = isset($validated['room_ids']) && is_array($validated['room_ids'])
            ? collect($validated['room_ids'])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all()
            : [];
        $roomLines = isset($validated['room_lines']) && is_array($validated['room_lines'])
            ? $validated['room_lines']
            : [];
        $hasRoomLines = count($roomLines) > 0;
        $hasVenues = isset($validated['venues']) && is_array($validated['venues']) && count($validated['venues']) > 0;

        if (! $hasRoomLines && ! $hasVenues) {
            return response()->json([
                'message' => 'Must select at least one room type or one venue.',
                'error' => 'accommodation_required',
            ], 422);
        }

        try {
            $checkInDate = Carbon::createFromFormat('M d, Y', $validated['check_in'])->startOfDay();
            $checkOutDate = Carbon::createFromFormat('M d, Y', $validated['check_out'])->startOfDay();

            if ($checkOutDate->lt($checkInDate)) {
                return response()->json([
                    'message' => 'Invalid date range',
                    'error' => 'Check-out cannot be before check-in',
                ], 422);
            }

            $hasRoomComponent = $hasRoomLines;
            [$checkIn, $checkOut] = $this->bookingWindowForStorage($hasRoomComponent, $checkInDate, $checkOutDate);

            $venueIds = $hasVenues
                ? collect($validated['venues'])
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->values()
                    ->all()
                : [];

            $venueEventForDuplicate = $hasVenues
                ? ($validated['venue_event_type'] ?? BookingPricing::VENUE_EVENT_WEDDING)
                : null;
            $this->bookingDuplicateGuard->assertNotIdenticalActiveBooking(
                (string) $request->input('email', ''),
                $checkIn,
                $checkOut,
                (int) $validated['days'],
                $roomLines,
                $venueIds,
                $venueEventForDuplicate,
                (string) ($validated['payment_method'] ?? 'cash'),
                (string) ($validated['online_payment_plan'] ?? ''),
            );

            if ($hasRoomLines && $roomIds !== []) {
                $availableRoomIds = Room::query()
                    ->whereIn('id', $roomIds)
                    ->availableBetween($checkIn, $checkOut, null)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $conflictingRoomIds = array_values(array_diff($roomIds, $availableRoomIds));

                if ($conflictingRoomIds !== []) {
                    $conflictingRooms = Room::whereIn('id', $conflictingRoomIds)
                        ->get(['id', 'name']);

                    return response()->json([
                        'message' => 'One or more selected rooms are no longer available for the chosen dates. Please choose another room and try again.',
                        'error' => 'room_unavailable',
                        'conflicts' => [
                            'rooms' => $conflictingRooms
                                ->map(fn ($room) => ['id' => $room->id, 'name' => $room->name])
                                ->values()
                                ->all(),
                        ],
                    ], 422);
                }
            }

            if ($hasRoomLines) {
                $roomLineError = $this->validateGuestRoomLines($roomLines, $checkIn, $checkOut, null);
                if ($roomLineError !== null) {
                    return $roomLineError;
                }
            }

            if ($hasVenues) {
                $venueEventForAvailability = BookingPricing::normalizeVenueEventType($validated['venue_event_type'] ?? null);
                $availableVenueIds = Venue::whereIn('id', $venueIds)
                    ->availableBetween($checkIn, $checkOut, null, $venueEventForAvailability, true)
                    ->pluck('id')
                    ->all();

                $conflictingVenueIds = array_values(array_diff($venueIds, $availableVenueIds));

                if (! empty($conflictingVenueIds)) {
                    $conflictingVenues = Venue::whereIn('id', $conflictingVenueIds)
                        ->get(['id', 'name']);

                    return response()->json([
                        'message' => 'Facility conflict: one or more selected venues are already booked for the selected dates.',
                        'error' => 'date_range_conflict',
                        'conflicts' => [
                            'venues' => $conflictingVenues
                                ->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])
                                ->values()
                                ->all(),
                        ],
                    ], 422);
                }
            }

            $guest = Guest::store($request);

            $venueEventType = $hasVenues
                ? ($validated['venue_event_type'] ?? BookingPricing::VENUE_EVENT_WEDDING)
                : null;

            $expectedTotal = BookingPricing::expectedTotalFromRoomLines(
                (int) $validated['days'],
                $roomLines,
                $hasVenues ? Venue::whereIn('id', $venueIds)->get() : collect(),
                $venueEventType,
            );

            if (! BookingPricing::totalsMatch($expectedTotal, (float) $validated['total_price'])) {
                return response()->json([
                    'message' => 'Total price does not match the selected room types, venues, and event type.',
                    'error' => 'price_mismatch',
                ], 422);
            }

            $paymentMethod = (string) ($validated['payment_method'] ?? 'cash');
            $onlinePaymentPlan = (string) ($validated['online_payment_plan'] ?? 'full');

            if ($paymentMethod === 'online') {
                if ($onlinePaymentPlan !== 'full' && ! $this->isAllowedPartialPlan($onlinePaymentPlan)) {
                    return response()->json([
                        'message' => 'Selected partial payment option is not allowed by admin settings.',
                        'error' => 'invalid_partial_payment_plan',
                    ], 422);
                }

                $paymentConfigEnabled = filter_var(env('PAYMENT_ONLINE_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
                if (! $paymentConfigEnabled) {
                    return response()->json([
                        'message' => 'Online payment is currently disabled by admin settings.',
                        'error' => 'online_payment_disabled',
                    ], 422);
                }
            }

            $booking = DB::transaction(function () use (
                $guest,
                $request,
                $validated,
                $checkIn,
                $checkOut,
                $roomLines,
                $venueIds,
                $venueEventType,
                $expectedTotal
            ) {
                $snapshots = Guest::bookingSnapshotAttributesFromSource($request);

                $booking = Booking::create([
                    'guest_id' => $guest->id,
                    'guest_name_snapshot' => $snapshots['guest_name_snapshot'],
                    'guest_email_snapshot' => $snapshots['guest_email_snapshot'],
                    'guest_contact_snapshot' => $snapshots['guest_contact_snapshot'],
                    'guest_address_snapshot' => $snapshots['guest_address_snapshot'],
                    'reference_number' => $validated['reference_number'] ?? null,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'no_of_days' => $validated['days'],
                    'venue_event_type' => $venueEventType,
                    'total_price' => $expectedTotal,
                    'booking_status' => Booking::BOOKING_STATUS_PENDING_VERIFICATION,
                    'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
                    'payment_method' => (string) ($validated['payment_method'] ?? 'cash'),
                    'online_payment_plan' => (string) ($validated['online_payment_plan'] ?? ''),
                ]);

                foreach ($roomLines as $line) {
                    $booking->roomLines()->create([
                        'room_type' => $line['room_type'],
                        'inventory_group_key' => $line['inventory_group_key'],
                        'quantity' => (int) $line['quantity'],
                        'unit_price_per_night' => (float) $line['unit_price'],
                    ]);
                }

                if (! empty($venueIds)) {
                    $booking->venues()->attach($venueIds);
                }

                return $booking->fresh(['guest', 'rooms', 'venues', 'roomLines']);
            });

            return response()->json([
                'message' => 'Booking created successfully. Please check your email to confirm.',
                'guest' => $guest,
                'booking' => $booking,
                'total_price' => $expectedTotal,
                'payment_url' => null,
                'email_verification_required' => true,
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to create booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Room stays: check-in 12:00 PM, check-out 10:00 AM (local) on the selected calendar dates.
     * Venue-only: full-day window (start of first day → end of last day) for availability overlap.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function bookingWindowForStorage(bool $hasRoomComponent, Carbon $checkInDate, Carbon $checkOutDate): array
    {
        if ($hasRoomComponent) {
            return [
                $checkInDate->copy()->setTime(12, 0, 0),
                $checkOutDate->copy()->setTime(10, 0, 0),
            ];
        }

        return [
            $checkInDate->copy()->startOfDay(),
            $checkOutDate->copy()->endOfDay(),
        ];
    }

    /**
     * Validate each room line against catalogue (type + spec key + rate) and remaining capacity
     * for the stay window (room_lines + assigned rooms on other bookings). Staff still pick concrete rooms.
     */
    private function validateGuestRoomLines(array $roomLines, Carbon $checkIn, Carbon $checkOut, ?int $excludeBookingId): ?JsonResponse
    {
        $typePool = collect($roomLines)
            ->pluck('room_type')
            ->filter(fn ($type) => is_string($type) && $type !== '')
            ->unique()
            ->values()
            ->all();

        $roomsByType = Room::query()
            ->whereIn('type', $typePool)
            ->where('status', '!=', Room::STATUS_MAINTENANCE)
            ->with(['bedSpecifications'])
            ->get()
            ->groupBy('type');

        foreach ($roomLines as $line) {
            $type = $line['room_type'];
            $key = $line['inventory_group_key'];
            $submittedUnit = (float) $line['unit_price'];

            $candidates = ($roomsByType->get($type) ?? collect())
                ->filter(fn (Room $r) => RoomInventoryGroupKey::forRoom($r) === $key);

            if ($candidates->isEmpty()) {
                return response()->json([
                    'message' => 'One or more room selections do not match available inventory.',
                    'error' => 'invalid_room_line',
                ], 422);
            }

            $unitMatchesAnyCandidate = $candidates->contains(
                fn (Room $r) => BookingPricing::totalsMatch((float) $r->price, $submittedUnit),
            );

            if (! $unitMatchesAnyCandidate) {
                return response()->json([
                    'message' => 'Room line price does not match current rates.',
                    'error' => 'price_mismatch',
                ], 422);
            }
        }

        $remainingMap = RoomInventoryGroupAvailability::remainingForRangeMap($checkIn, $checkOut, $excludeBookingId);
        $requestedTotals = [];
        foreach ($roomLines as $line) {
            $c = RoomInventoryGroupAvailability::compositeKey($line['room_type'], $line['inventory_group_key']);
            $requestedTotals[$c] = ($requestedTotals[$c] ?? 0) + (int) $line['quantity'];
        }
        foreach ($requestedTotals as $composite => $qty) {
            $rem = $remainingMap[$composite] ?? 0;
            if ($qty > $rem) {
                [$type, $invKey] = explode("\0", $composite, 2);

                return response()->json([
                    'message' => 'Facility conflict: not enough available rooms for one or more selected room types on the selected dates.',
                    'error' => 'date_range_conflict',
                    'conflicts' => [
                        'room_lines' => [
                            [
                                'room_type' => $type,
                                'inventory_group_key' => $invKey,
                                'requested' => $qty,
                                'available' => $rem,
                            ],
                        ],
                    ],
                ], 422);
            }
        }

        return null;
    }

    private function expectedTotalForBooking(Booking $booking, int $nights): float
    {
        $nights = max(1, $nights);
        if ($booking->rooms->isNotEmpty()) {
            return BookingPricing::expectedTotal(
                $nights,
                $booking->rooms,
                $booking->venues,
                $booking->venue_event_type,
            );
        }
        if ($booking->roomLines->isNotEmpty()) {
            return BookingPricing::expectedTotalFromRoomLines(
                $nights,
                $booking->roomLines,
                $booking->venues,
                $booking->venue_event_type,
            );
        }

        return BookingPricing::expectedTotal(
            $nights,
            collect(),
            $booking->venues,
            $booking->venue_event_type,
        );
    }

    /**
     * Display a specific booking.
     */
    public function show($id)
    {
        try {
            $booking = Booking::with(['guest', 'rooms', 'venues', 'roomLines'])->find($id);

            if (! $booking) {
                return response()->json([
                    'message' => 'Booking not found',
                ], 404);
            }

            Gate::authorize('view', $booking);

            $this->expireIfNeeded($booking);

            return response()->json($booking, 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error retrieving booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $booking = Booking::with(['guest', 'rooms', 'venues', 'roomLines'])->find($id);

            if (! $booking) {
                return response()->json([
                    'message' => 'Booking not found',
                ], 404);
            }

            Gate::authorize('update', $booking);

            if ($this->expireIfNeeded($booking)) {
                return response()->json([
                    'message' => 'Booking expired after 3 days without payment and was cancelled.',
                ], 422);
            }

            $validated = $request->validate([
                'booking_status' => 'sometimes|string|in:'.implode(',', [
                    Booking::BOOKING_STATUS_PENDING_VERIFICATION,
                    Booking::BOOKING_STATUS_RESERVED,
                    Booking::BOOKING_STATUS_OCCUPIED,
                    Booking::BOOKING_STATUS_COMPLETED,
                    Booking::BOOKING_STATUS_CANCELLED,
                    Booking::BOOKING_STATUS_RESCHEDULED,
                ]),
                'payment_status' => 'sometimes|string|in:'.implode(',', [
                    Booking::PAYMENT_STATUS_UNPAID,
                    Booking::PAYMENT_STATUS_PARTIAL,
                    Booking::PAYMENT_STATUS_PAID,
                    Booking::PAYMENT_STATUS_REFUND_PENDING,
                    Booking::PAYMENT_STATUS_NON_REFUNDABLE,
                    Booking::PAYMENT_STATUS_REFUNDED,
                ]),
            ]);

            if (! empty($validated['booking_status'])) {
                if ($validated['booking_status'] === Booking::BOOKING_STATUS_OCCUPIED) {
                    $booking->loadMissing(['rooms.bedSpecifications', 'venues', 'roomLines']);
                    $booking->assertAssignmentsSatisfiedForOccupied();
                }
            }

            $updates = array_filter([
                'booking_status' => $validated['booking_status'] ?? null,
                'payment_status' => $validated['payment_status'] ?? null,
            ], fn ($v) => $v !== null);

            if ($updates !== []) {
                $booking->update($updates);
            }

            $booking->refresh()->load(['guest', 'rooms', 'venues']);

            return response()->json([
                'message' => 'Booking updated successfully',
                'booking' => $booking,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error updating booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $booking = Booking::find($id);

            if (! $booking) {
                return response()->json([
                    'message' => 'Booking not found',
                ], 404);
            }

            Gate::authorize('delete', $booking);

            $booking->delete();

            return response()->json([
                'message' => 'Booking deleted successfully',
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error deleting booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function cancel(Request $request, Booking $booking)
    {
        if ($this->expireIfNeeded($booking)) {
            return response()->json([
                'message' => 'Booking already expired and has been cancelled automatically.',
            ], 422);
        }

        $request->validate([
            'otp' => 'required|string',
        ]);

        try {
            $canCancel = $booking->booking_status === Booking::BOOKING_STATUS_PENDING_VERIFICATION
                || $booking->booking_status === Booking::BOOKING_STATUS_RESCHEDULED
                || (
                    $booking->booking_status === Booking::BOOKING_STATUS_RESERVED
                    && in_array($booking->payment_status, [
                        Booking::PAYMENT_STATUS_UNPAID,
                        Booking::PAYMENT_STATUS_PARTIAL,
                        Booking::PAYMENT_STATUS_PAID,
                        Booking::PAYMENT_STATUS_REFUND_PENDING,
                        Booking::PAYMENT_STATUS_NON_REFUNDABLE,
                        Booking::PAYMENT_STATUS_REFUNDED,
                    ], true)
                );

            if (! $canCancel) {
                return response()->json([
                    'message' => 'Booking cannot be cancelled in its current state.',
                ], 422);
            }

            if (! $this->bookingActionOtpService->verifyAndConsume(
                $booking->reference_number,
                BookingActionOtpService::PURPOSE_CANCEL,
                (string) $request->input('otp'),
            )) {
                return response()->json([
                    'message' => 'Invalid or expired verification code.',
                ], 422);
            }

            $cancellation = CancellationPolicy::breakdownForCancelledBooking(
                (float) $booking->total_price,
                (float) $booking->total_paid
            );

            $updates = [
                'booking_status' => Booking::BOOKING_STATUS_CANCELLED,
            ];

            $paymentStatus = (string) $booking->payment_status;
            if (in_array($paymentStatus, [
                Booking::PAYMENT_STATUS_PARTIAL,
                Booking::PAYMENT_STATUS_PAID,
            ], true)) {
                $updates['payment_status'] = ((float) ($cancellation['amount_to_refund'] ?? 0) > 0.009)
                    ? Booking::PAYMENT_STATUS_REFUND_PENDING
                    : Booking::PAYMENT_STATUS_NON_REFUNDABLE;
            }

            $booking->update($updates);

            broadcast(new BookingCancelled($booking))->toOthers();

            return response()->json([
                'message' => 'Booking cancelled successfully.',
                'booking' => $booking,
                'cancellation' => $cancellation,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error cancelling booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function reschedule(Request $request, $reference)
    {
        $request->validate([
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'otp' => 'required|string',
        ]);

        $booking = Booking::where('reference_number', $reference)->firstOrFail();

        if ($this->expireIfNeeded($booking)) {
            return response()->json([
                'message' => 'Booking expired after 3 days without payment and cannot be rescheduled.',
            ], 422);
        }

        if ($booking->booking_status === Booking::BOOKING_STATUS_PENDING_VERIFICATION) {
            return response()->json([
                'message' => 'Confirm your booking by email before rescheduling.',
            ], 422);
        }

        if (in_array($booking->booking_status, [Booking::BOOKING_STATUS_CANCELLED, Booking::BOOKING_STATUS_COMPLETED], true)) {
            return response()->json([
                'message' => 'Cannot reschedule this booking',
            ], 422);
        }

        $booking->loadMissing(['rooms', 'venues', 'roomLines']);

        $checkInDate = Carbon::parse($request->check_in)->startOfDay();
        $checkOutDate = Carbon::parse($request->check_out)->startOfDay();

        $hasRoomComponent = $booking->rooms->isNotEmpty() || $booking->roomLines->isNotEmpty();
        [$checkIn, $checkOut] = $this->bookingWindowForStorage($hasRoomComponent, $checkInDate, $checkOutDate);

        $roomIds = $booking->rooms->pluck('id')->toArray();
        if (! empty($roomIds)) {
            $availableRoomIds = Room::whereIn('id', $roomIds)
                ->availableBetween($checkIn, $checkOut, $booking->id)
                ->pluck('id')
                ->toArray();

            if (count($availableRoomIds) !== count($roomIds)) {
                return response()->json([
                    'message' => 'One or more currently booked rooms are not available for the new dates',
                ], 422);
            }
        } elseif ($booking->roomLines->isNotEmpty()) {
            $roomLineError = $this->validateGuestRoomLines(
                $booking->roomLines->map(fn ($l) => [
                    'room_type' => $l->room_type,
                    'inventory_group_key' => $l->inventory_group_key,
                    'quantity' => $l->quantity,
                    'unit_price' => (float) $l->unit_price_per_night,
                ])->all(),
                $checkIn,
                $checkOut,
                $booking->id,
            );
            if ($roomLineError !== null) {
                return $roomLineError;
            }
        }

        $venueIds = $booking->venues->pluck('id')->toArray();
        if (! empty($venueIds)) {
            $availableVenueIds = Venue::whereIn('id', $venueIds)
                ->availableBetween(
                    $checkIn,
                    $checkOut,
                    $booking->id,
                    $booking->venue_event_type,
                    $booking->venues->isNotEmpty(),
                )
                ->pluck('id')
                ->toArray();

            if (count($availableVenueIds) !== count($venueIds)) {
                return response()->json([
                    'message' => 'One or more currently booked venues are not available for the new dates',
                ], 422);
            }
        }

        if (! $this->bookingActionOtpService->verifyAndConsume(
            $booking->reference_number,
            BookingActionOtpService::PURPOSE_RESCHEDULE,
            (string) $request->input('otp'),
        )) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        $nights = max(1, (int) $checkInDate->diffInDays($checkOutDate));

        $newTotal = $this->expectedTotalForBooking($booking, $nights);
        $amountPaid = (float) $booking->total_paid;
        $nextPaymentStatus = Booking::paymentStatusFromAmounts($newTotal, $amountPaid);

        $booking->update([
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'total_price' => $newTotal,
            'payment_status' => $nextPaymentStatus,
            'booking_status' => Booking::BOOKING_STATUS_RESCHEDULED,
        ]);

        broadcast(new BookingRescheduled($booking))->toOthers();

        return response()->json([
            'message' => 'Booking rescheduled successfully',
            'booking' => $booking,
        ]);
    }

    public function paymentStatusByReceiptToken(string $token): JsonResponse
    {
        if (! Str::isUuid($token)) {
            return response()->json([
                'message' => 'Invalid receipt token.',
            ], 422);
        }

        $booking = Booking::query()->where('receipt_token', $token)->first();

        if (! $booking) {
            return response()->json([
                'message' => 'Booking not found',
            ], 404);
        }

        if ($response = $this->rejectIfPendingVerification($booking)) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'booking_status' => (string) $booking->booking_status,
                'payment_status' => (string) $booking->payment_status,
                'payment_method' => (string) ($booking->payment_method ?? 'cash'),
                'online_payment_plan' => (string) ($booking->online_payment_plan ?? ''),
                'invoice_id' => (string) ($booking->xendit_invoice_id ?? ''),
                'invoice_url' => (string) ($booking->xendit_invoice_url ?? ''),
                'can_retry' => $this->canRetryOnlinePayment($booking),
            ],
        ]);
    }

    public function retryOnlinePaymentByReceiptToken(string $token): JsonResponse
    {
        if (! Str::isUuid($token)) {
            return response()->json([
                'message' => 'Invalid receipt token.',
            ], 422);
        }

        $booking = Booking::with('guest')->where('receipt_token', $token)->first();

        if (! $booking) {
            return response()->json([
                'message' => 'Booking not found',
            ], 404);
        }

        if ($response = $this->rejectIfPendingVerification($booking)) {
            return $response;
        }

        if (! $this->canRetryOnlinePayment($booking)) {
            return response()->json([
                'message' => 'Payment retry is not allowed for this booking state.',
            ], 422);
        }

        $plan = (string) ($booking->online_payment_plan ?: 'full');
        $guest = $booking->guest;
        if (! $guest) {
            return response()->json([
                'message' => 'Guest not found for this booking.',
            ], 422);
        }

        $overrideAmount = null;
        if ((string) $booking->payment_status === Booking::PAYMENT_STATUS_PARTIAL) {
            $overrideAmount = max(1, (float) $booking->balance);
        }

        $invoice = $this->createXenditInvoiceForBooking($booking, $guest, $plan, $overrideAmount);
        $paymentUrl = $invoice['invoice_url'] ?? null;

        if (! is_string($paymentUrl) || trim($paymentUrl) === '') {
            return response()->json([
                'message' => 'Unable to create a new payment invoice.',
            ], 502);
        }

        $booking->update([
            'xendit_invoice_id' => (string) ($invoice['id'] ?? ''),
            'xendit_invoice_url' => $paymentUrl,
            'payment_method' => 'online',
        ]);

        Cache::put($this->pendingOnlinePaymentCacheKey((int) $booking->id), true, now()->addHours(2));

        return response()->json([
            'success' => true,
            'payment_url' => $paymentUrl,
            'booking' => $booking->fresh(),
        ]);
    }

    private function ensureBookingQrExists(Booking $booking): void
    {
        if ($booking->qr_code && Storage::disk('public')->exists($booking->qr_code)) {
            return;
        }

        $this->generateBookingQr($booking, $booking->qr_code ? basename($booking->qr_code) : null);
    }

    private function generateBookingQr(Booking $booking, ?string $filename = null): string
    {
        $payload = json_encode([
            'booking_id' => $booking->id,
            // Keep both key names for backward compatibility with existing scanners.
            'reference_number' => $booking->reference_number,
            'reference' => $booking->reference_number,
            'guest_id' => $booking->guest_id,
        ]);

        $filename = $filename ?: Str::uuid().'.svg';
        $path = 'qr/bookings/'.$filename;

        $svg = QrCode::format('svg')->size(300)->generate($payload);

        Storage::disk('public')->put($path, $svg);

        if ($booking->qr_code !== $path) {
            $booking->update([
                'qr_code' => $path,
            ]);
        }

        return $path;
    }

    private function expireIfNeeded(Booking $booking): bool
    {
        if (Cache::has($this->pendingOnlinePaymentCacheKey((int) $booking->id))) {
            return false;
        }

        return $booking->expireIfUnpaidExceededRule();
    }

    private function pendingOnlinePaymentCacheKey(int $bookingId): string
    {
        return "booking_online_payment_pending_{$bookingId}";
    }

    private function canRetryOnlinePayment(Booking $booking): bool
    {
        if ((string) ($booking->payment_method ?? '') !== 'online') {
            return false;
        }

        return in_array((string) $booking->payment_status, [
            Booking::PAYMENT_STATUS_UNPAID,
            Booking::PAYMENT_STATUS_PARTIAL,
        ], true);
    }

    /**
     * @return array{invoice_url?: string}
     */
    private function createXenditInvoiceForBooking(
        Booking $booking,
        Guest $guest,
        string $paymentPlan,
        ?float $overrideAmount = null
    ): array {
        $secretKey = trim((string) config('services.xendit.secret_key'));
        if ($secretKey === '') {
            return [];
        }

        $totalAmount = (float) $booking->total_price;
        $partialPercent = $this->extractPartialPercentage($paymentPlan);
        $chargeAmount = $partialPercent !== null
            ? max(1, (float) round($totalAmount * ($partialPercent / 100), 2))
            : max(1, $totalAmount);
        if ($overrideAmount !== null) {
            $chargeAmount = max(1, (float) round($overrideAmount, 2));
        }

        $frontendBase = rtrim((string) config('app.frontend_url'), '/');
        $receiptToken = (string) $booking->receipt_token;
        $successQuery = $partialPercent !== null
            ? '?payment=success&payment_mode='.rawurlencode($paymentPlan)
            : '?payment=success&payment_mode=full';
        $failureQuery = '?payment=failed';

        $successRedirect = "{$frontendBase}/booking-receipt/{$receiptToken}{$successQuery}";
        $failureRedirect = "{$frontendBase}/booking-receipt/{$receiptToken}{$failureQuery}";

        $payload = [
            'external_id' => $booking->reference_number,
            'amount' => $chargeAmount,
            'payer_email' => (string) ($guest->email ?? ''),
            'description' => "Booking {$booking->reference_number} ({$paymentPlan})",
            'success_redirect_url' => $successRedirect,
            'failure_redirect_url' => $failureRedirect,
            'currency' => 'PHP',
            'metadata' => [
                'reference_number' => $booking->reference_number,
                'receipt_token' => $booking->receipt_token,
                'payment_mode' => $paymentPlan,
                'full_amount' => $totalAmount,
                'override_amount' => $overrideAmount,
            ],
        ];

        $response = Http::withBasicAuth($secretKey, '')
            ->timeout(20)
            ->post((string) config('services.xendit.invoice_url'), $payload);

        if (! $response->successful()) {
            return [];
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }

    private function extractPartialPercentage(string $plan): ?int
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

    private function resolveAmountDueNow(Booking $booking): float
    {
        $totalAmount = (float) $booking->total_price;
        $balance = max(0, (float) $booking->balance);
        $plan = (string) ($booking->online_payment_plan ?? '');
        $paymentStatus = (string) ($booking->payment_status ?? '');
        $partialPercent = $this->extractPartialPercentage($plan);

        if ($paymentStatus === Booking::PAYMENT_STATUS_PARTIAL) {
            return $balance;
        }

        if ($partialPercent !== null) {
            return max(0, (float) round($totalAmount * ($partialPercent / 100), 2));
        }

        return max(0, $totalAmount);
    }

    private function plannedPaymentAmountForMode(Booking $booking, string $paymentMode): float
    {
        $totalAmount = (float) $booking->total_price;
        $balance = max(0, (float) $booking->balance);
        $partialPercent = $this->extractPartialPercentage($paymentMode);

        if ((string) $booking->payment_status === Booking::PAYMENT_STATUS_PARTIAL) {
            return $balance;
        }

        if ($partialPercent !== null) {
            return max(0, (float) round($totalAmount * ($partialPercent / 100), 2));
        }

        return max(0, $totalAmount);
    }

    private function upsertConfirmedPaymentRecord(Booking $booking, string $invoiceId, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $totalAmount = (int) round((float) $booking->total_price);
        $partialAmount = (int) round($amount);
        $isFullyPaid = $totalAmount > 0 && $partialAmount >= $totalAmount;

        // Best-effort: prefer linking to invoice id; if missing, still store the payment.
        if ($invoiceId !== '') {
            $existing = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('provider', 'xendit')
                ->where('provider_ref', $invoiceId)
                ->first();

            if ($existing) {
                $existing->update([
                    'total_amount' => $totalAmount,
                    'partial_amount' => $partialAmount,
                    'is_fullypaid' => $isFullyPaid,
                    'provider_status' => 'confirmed',
                ]);

                return;
            }
        }

        $booking->payments()->create([
            'total_amount' => $totalAmount,
            'partial_amount' => $partialAmount,
            'is_fullypaid' => $isFullyPaid,
            'provider' => $invoiceId !== '' ? 'xendit' : null,
            'provider_ref' => $invoiceId !== '' ? $invoiceId : null,
            'provider_status' => 'confirmed',
        ]);
    }

    /**
     * @return array{partial_payment_options: array<int>, allow_custom_partial_payment: bool}
     */
    private function paymentSettingsConfig(): array
    {
        $cached = Cache::get('payment_settings_config');
        if (is_array($cached)) {
            $options = collect($cached['partial_payment_options'] ?? [])
                ->map(fn ($v): int => (int) $v)
                ->filter(fn (int $v): bool => $v > 0 && $v < 100)
                ->unique()
                ->sort()
                ->values()
                ->all();

            return [
                'partial_payment_options' => $options !== [] ? $options : [30],
                'allow_custom_partial_payment' => (bool) ($cached['allow_custom_partial_payment'] ?? false),
            ];
        }

        $options = collect(explode(',', (string) env('PAYMENT_PARTIAL_OPTIONS', '10,20,30')))
            ->map(fn (string $v): int => (int) trim($v))
            ->filter(fn (int $v): bool => $v > 0 && $v < 100)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'partial_payment_options' => $options !== [] ? $options : [30],
            'allow_custom_partial_payment' => filter_var(env('PAYMENT_PARTIAL_ALLOW_CUSTOM', false), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function isAllowedPartialPlan(string $plan): bool
    {
        $percent = $this->extractPartialPercentage($plan);
        if ($percent === null) {
            return false;
        }

        $settings = $this->paymentSettingsConfig();
        if ($settings['allow_custom_partial_payment']) {
            return true;
        }

        return in_array($percent, $settings['partial_payment_options'], true);
    }
}
