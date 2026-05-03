<?php

namespace App\Models;

use App\Mail\BookingCreated;
use App\Mail\TestimonialFeedbackEmail;
use App\Mail\VerifyBookingEmail;
use App\Support\RoomInventoryGroupKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'guest_id',
        'guest_name_snapshot',
        'guest_email_snapshot',
        'guest_contact_snapshot',
        'guest_address_snapshot',
        'reference_number',
        'receipt_token',
        // Guest billing access control: store ONLY hashed token (SHA-256 hex, 64 chars).
        'access_token',
        'token_expires_at',
        'qr_code',
        'check_in',
        'check_out',
        'total_price',
        'special_discount_type',
        'special_discount_target',
        'special_discount_value',
        'special_discount_reason_code',
        'special_discount_note',
        'special_discount_original_total_price',
        'special_discount_amount_applied',
        'special_discounted_by_user_id',
        'special_discounted_at',
        'special_discount_last_modified_by_user_id',
        'special_discount_last_modified_at',
        'booking_status',
        'payment_status',
        'has_damage_claim',
        'damage_settlement_status',
        'damage_settlement_notes',
        'damage_settlement_marked_by',
        'damage_settlement_marked_at',
        'payment_method',
        'online_payment_plan',
        'xendit_invoice_id',
        'xendit_invoice_url',
        'no_of_days',
        'venue_event_type',
        'reminder_sent',
        'reminder_sent_at',
        'reminder_sms_sent',
        'reminder_sms_sent_at',
        'reminder_sms_error',
        'testimonial_feedback_sent_at',
        'refund_alert_sent_at',
        'refund_guest_notice_sent_at',
        'refund_guest_confirmation_sent_at',
        'email_verified_at',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'total_price' => 'decimal:2',
        'special_discount_value' => 'decimal:2',
        'special_discount_original_total_price' => 'decimal:2',
        'special_discount_amount_applied' => 'decimal:2',
        'special_discounted_at' => 'datetime',
        'special_discount_last_modified_at' => 'datetime',
        'no_of_days' => 'integer',
        'reminder_sent' => 'boolean',
        'reminder_sent_at' => 'datetime',
        'reminder_sms_sent' => 'boolean',
        'reminder_sms_sent_at' => 'datetime',
        'testimonial_feedback_sent_at' => 'datetime',
        'refund_alert_sent_at' => 'datetime',
        'refund_guest_notice_sent_at' => 'datetime',
        'refund_guest_confirmation_sent_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'token_expires_at' => 'datetime',
        'has_damage_claim' => 'boolean',
        'damage_settlement_marked_at' => 'datetime',
    ];

    /**
     * Never expose guest billing token hash/expiry via JSON API responses.
     * (Access control is always enforced by the API server.)
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'access_token',
        'token_expires_at',
    ];

    protected static function booted()
    {
        /**
         * Generate reference number before create
         */
        static::creating(function ($booking) {
            $booking->reference_number =
                'MWA-'.now()->year.'-'.str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
            if (! Str::isUuid((string) $booking->receipt_token)) {
                $booking->receipt_token = (string) Str::uuid();
            }
            if (empty($booking->booking_status)) {
                $booking->booking_status = self::BOOKING_STATUS_RESERVED;
            }
            if (empty($booking->payment_status)) {
                $booking->payment_status = self::PAYMENT_STATUS_UNPAID;
            }
        });

        /**
         * Handle actions AFTER booking is created
         */
        static::created(function (Booking $booking) {
            $billingToken = $booking->generateBillingAccessToken();

            if ($booking->booking_status === self::BOOKING_STATUS_PENDING_VERIFICATION) {
                $booking->loadMissing('guest');
                $email = $booking->guest?->email;
                if ($email) {
                    $hours = max(1, (int) config('booking.pending_verification_url_ttl_hours', 72));
                    $verifyUrl = URL::temporarySignedRoute(
                        'bookings.verify-email',
                        now()->addHours($hours),
                        [
                            'booking' => $booking->id,
                            // Pass raw billing token so the controller can redirect
                            // to the new guest billing statement route after verification.
                            'billing_token' => $billingToken,
                        ],
                    );
                    Mail::to($email)->send(new VerifyBookingEmail($booking, $verifyUrl, $billingToken));
                }

                return;
            }

            $booking->generateQrCode();

            $booking->loadMissing('guest');
            if ($booking->guest && $booking->guest->email) {
                $mail = Mail::to($booking->guest->email);
                $bookingCcAddress = config('mail.booking_cc_address');

                if (filled($bookingCcAddress)) {
                    $mail->cc($bookingCcAddress);
                }

                $mail->send(new BookingCreated($booking, $billingToken));
            }
        });

        /**
         * Send testimonial feedback email when stay is completed and fully paid (paid + completed).
         */
        static::updated(function (Booking $booking) {
            if ($booking->testimonial_feedback_sent_at !== null) {
                return;
            }

            $origBooking = $booking->getOriginal('booking_status');
            $origPayment = $booking->getOriginal('payment_status');
            $wasEligible = $origBooking === self::BOOKING_STATUS_COMPLETED
                && $origPayment === self::PAYMENT_STATUS_PAID;

            if (! $booking->isEligibleForTestimonialFeedback() || $wasEligible) {
                return;
            }

            $booking->loadMissing('guest');
            $email = $booking->guest?->email;
            if (! $email) {
                return;
            }

            try {
                Mail::to($email)->send(new TestimonialFeedbackEmail($booking));
            } catch (\Throwable $e) {
                Log::error('Failed sending testimonial feedback', [
                    'booking_id' => $booking->id,
                    'reference_number' => $booking->reference_number,
                    'guest_email' => $email,
                    'error' => $e->getMessage(),
                ]);

                return;
            }

            $booking->updateQuietly(['testimonial_feedback_sent_at' => now()]);
        });
    }

    /**
     * Generate a raw billing token for guest links, then persist ONLY its hashed version.
     *
     * - Raw token length: 64 hex chars (32 bytes), >= 64 requirement.
     * - Stored value: SHA-256 hash (64 hex chars).
     * - Expiry is stored in `token_expires_at` (nullable when TTL <= 0).
     */
    public function generateBillingAccessToken(): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $rawToken);

        $ttlHours = (int) config('booking.billing_statement_url_ttl_hours', 24);
        $expiresAt = $ttlHours > 0 ? now()->addHours($ttlHours) : null;

        $this->forceFill([
            'access_token' => $hash,
            'token_expires_at' => $expiresAt,
        ])->saveQuietly();

        if ($this->id !== null) {
            Cache::put($this->billingTokenCacheKey(), $rawToken, $expiresAt);
        }

        return $rawToken;
    }

    public function billingTokenCacheKey(): string
    {
        return 'booking.billing_token.'.(int) $this->id;
    }

    /* ================= RELATIONSHIPS ================= */

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'booking_room')->withTimestamps();
    }

    public function roomChecklists()
    {
        return $this->hasMany(RoomChecklist::class);
    }

    public function bookingInspection()
    {
        return $this->hasOne(BookingInspection::class);
    }

    /**
     * Guest-selected room type + bed-spec lines (no specific room until staff assigns).
     */
    public function roomLines()
    {
        return $this->hasMany(BookingRoomLine::class);
    }

    /**
     * Ensures assigned physical rooms exactly fulfill each requested type + bed-spec line (billing statement).
     *
     * @param  array<int|string>  $roomIds
     *
     * @throws ValidationException
     */
    public static function validateAssignedRoomsFulfillRoomLines(Booking $booking, array $roomIds): void
    {
        $booking->loadMissing('roomLines');
        if ($booking->roomLines->isEmpty()) {
            return;
        }

        $roomIds = array_values(array_unique(array_filter(array_map('intval', $roomIds))));
        $expectedTotal = (int) $booking->roomLines->sum('quantity');

        $rooms = Room::query()
            ->whereIn('id', $roomIds)
            ->with(['bedSpecifications'])
            ->get();

        if (count($rooms) !== count($roomIds)) {
            throw ValidationException::withMessages([
                'rooms' => ['One or more selected rooms are invalid or missing.'],
            ]);
        }

        if (count($rooms) !== $expectedTotal) {
            throw ValidationException::withMessages([
                'rooms' => ["Assign exactly {$expectedTotal} physical room(s) to match the guest billing ({$expectedTotal} slot(s) requested)."],
            ]);
        }

        foreach ($booking->roomLines->groupBy(fn (BookingRoomLine $l) => $l->room_type."\0".$l->inventory_group_key) as $group) {
            $line = $group->first();
            $need = (int) $group->sum('quantity');
            $have = $rooms->filter(function (Room $room) use ($line) {
                return $room->type === $line->room_type
                    && RoomInventoryGroupKey::forRoom($room) === $line->inventory_group_key;
            })->count();

            if ($have !== $need) {
                $label = $line->displayLabel();
                throw ValidationException::withMessages([
                    'rooms' => ["Guest requested {$need} × {$label}. You assigned {$have} matching room(s)."],
                ]);
            }
        }
    }

    /**
     * Guest billing includes room lines → staff must assign matching physical rooms before check-in.
     */
    public function expectsRoomAssignments(): bool
    {
        if (! $this->relationLoaded('roomLines')) {
            if (! $this->exists) {
                return false;
            }
            $this->loadMissing('roomLines');
        }

        return $this->roomLines->isNotEmpty();
    }

    /**
     * Booking was sold with a venue package (see API create / Filament) → at least one venue must stay attached.
     */
    public function expectsVenueAssignments(): bool
    {
        return filled($this->venue_event_type);
    }

    /**
     * Whether rooms (if required) and venues (if required) satisfy rules for transitioning to occupied.
     */
    public function assignmentsSatisfiedForOccupied(): bool
    {
        try {
            $this->assertAssignmentsSatisfiedForOccupied();

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * Ensures physical rooms match room lines and venue package has at least one venue before marking occupied.
     *
     * @throws ValidationException
     */
    public function assertAssignmentsSatisfiedForOccupied(): void
    {
        if (! $this->relationLoaded('roomLines') && $this->exists) {
            $this->loadMissing('roomLines');
        }
        if (! $this->relationLoaded('venues')) {
            if ($this->exists) {
                $this->loadMissing('venues');
            } else {
                $this->setRelation('venues', collect());
            }
        }
        if (! $this->relationLoaded('rooms')) {
            if ($this->exists) {
                $this->loadMissing(['rooms.bedSpecifications']);
            } else {
                $this->setRelation('rooms', collect());
            }
        } elseif ($this->rooms instanceof Collection) {
            $this->rooms->loadMissing('bedSpecifications');
        }

        if ($this->expectsRoomAssignments()) {
            $roomIds = $this->rooms->pluck('id')->all();
            self::validateAssignedRoomsFulfillRoomLines($this, $roomIds);
        }

        if ($this->expectsVenueAssignments() && $this->venues->isEmpty()) {
            throw ValidationException::withMessages([
                'venues' => ['Assign at least one venue before check-in.'],
            ]);
        }
    }

    public function venues()
    {
        return $this->belongsToMany(Venue::class, 'booking_venue')->withTimestamps();
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function damageSettlementMarker()
    {
        return $this->belongsTo(User::class, 'damage_settlement_marked_by');
    }

    /* ================= BOOKING (STAY) STATUS ================= */

    /** Awaiting guest email confirmation; does not block public availability (see {@see availabilityBlockingStatuses()}). */
    const BOOKING_STATUS_PENDING_VERIFICATION = 'pending_verification';

    const BOOKING_STATUS_RESERVED = 'reserved';

    const BOOKING_STATUS_OCCUPIED = 'occupied';

    const BOOKING_STATUS_COMPLETED = 'completed';

    /** Checkout finished with inventory issues (photo-backed inspection). */
    const BOOKING_STATUS_FLAGGED = 'flagged';

    const BOOKING_STATUS_CANCELLED = 'cancelled';

    const BOOKING_STATUS_RESCHEDULED = 'rescheduled';

    /* ================= PAYMENT STATUS ================= */

    const PAYMENT_STATUS_UNPAID = 'unpaid';

    const PAYMENT_STATUS_PARTIAL = 'partial';

    const PAYMENT_STATUS_PAID = 'paid';

    const PAYMENT_STATUS_REFUND_PENDING = 'refund_pending';

    const PAYMENT_STATUS_NON_REFUNDABLE = 'non_refundable';

    const PAYMENT_STATUS_REFUNDED = 'refunded';

    /* ================= DAMAGE SETTLEMENT STATUS ================= */

    const DAMAGE_SETTLEMENT_STATUS_NONE = 'none';

    const DAMAGE_SETTLEMENT_STATUS_PENDING = 'pending';

    const DAMAGE_SETTLEMENT_STATUS_SETTLED = 'settled';

    /**
     * Testimonial email / review eligibility: fully paid stay marked completed.
     */
    public function isEligibleForTestimonialFeedback(): bool
    {
        return $this->booking_status === self::BOOKING_STATUS_COMPLETED
            && $this->payment_status === self::PAYMENT_STATUS_PAID;
    }

    const UNPAID_EXPIRY_DAYS = 3;

    /**
     * Show the billing-statement 3-day down payment notice only when check-in is
     * at least this many calendar days after the booking date (day granularity).
     * Same-day / next-day / short-lead bookings use other payment arrangements.
     */
    const DOWN_PAYMENT_NOTICE_MIN_LEAD_DAYS = 4;

    /** Unpaid settlement / auto-cancel deadline on the check-in calendar day (Asia/Manila). */
    const CHECK_IN_UNPAID_SETTLEMENT_HOUR = 21;

    public static function timezoneManila(): string
    {
        return 'Asia/Manila';
    }

    /**
     * Whether the booking check-in date is today in Asia/Manila.
     */
    public function isCheckInTodayManila(?Carbon $at = null): bool
    {
        if (! $this->check_in) {
            return false;
        }

        $at = $at ?? now();
        $tz = self::timezoneManila();
        $checkInDay = $this->check_in->copy()->timezone($tz)->startOfDay();
        $today = $at->copy()->timezone($tz)->startOfDay();

        return $checkInDay->equalTo($today);
    }

    /**
     * Check-in calendar date in Manila is strictly after "today" in Manila (receipt: messenger instructions).
     */
    public function isCheckInStrictlyAfterTodayManila(?Carbon $at = null): bool
    {
        if (! $this->check_in) {
            return false;
        }
        $at = $at ?? now();
        $tz = self::timezoneManila();
        $checkInDay = $this->check_in->copy()->timezone($tz)->startOfDay();
        $today = $at->copy()->timezone($tz)->startOfDay();

        return $checkInDay->gt($today);
    }

    /**
     * Whether the billing statement should show Messenger settlement (30% deposit via Messenger).
     *
     * Used when check-in calendar date in Manila is strictly after "today" — same unified 9:00 PM check-in-day
     * deadline still applies for unpaid auto-cancel and {@see unpaidExpiresAt}.
     */
    public function useMessengerDepositInstructions(?Carbon $at = null): bool
    {
        return $this->isCheckInStrictlyAfterTodayManila($at);
    }

    /**
     * 9:00 PM Asia/Manila unpaid settlement deadline:
     * - check-in is on/earlier than booking day: 9:00 PM on check-in day
     * - check-in is after booking day: 9:00 PM on the day after booking day
     */
    public function unpaidSettlementDeadlineManila(?Carbon $at = null): ?Carbon
    {
        if (! $this->check_in) {
            return null;
        }
        $tz = self::timezoneManila();
        $anchor = ($this->created_at ?? $at ?? now())->copy()->timezone($tz);
        $checkInDay = $this->check_in->copy()->timezone($tz)->startOfDay();
        $bookingDay = $anchor->copy()->startOfDay();
        $targetDay = $checkInDay->gt($bookingDay)
            ? $bookingDay->copy()->addDay()
            : $checkInDay;

        return $targetDay->setTime(
            self::CHECK_IN_UNPAID_SETTLEMENT_HOUR,
            0,
            0,
        );
    }

    /**
     * Stay statuses that consume inventory in public availability (rooms/venues/capacity).
     *
     * @return list<string>
     */
    public static function availabilityBlockingStatuses(): array
    {
        return [
            self::BOOKING_STATUS_RESERVED,
            self::BOOKING_STATUS_OCCUPIED,
            self::BOOKING_STATUS_COMPLETED,
            self::BOOKING_STATUS_FLAGGED,
            self::BOOKING_STATUS_RESCHEDULED,
        ];
    }

    public static function bookingStatusOptions(): array
    {
        return [
            self::BOOKING_STATUS_PENDING_VERIFICATION => 'Pending email verification',
            self::BOOKING_STATUS_RESERVED => 'Reserved',
            self::BOOKING_STATUS_OCCUPIED => 'Occupied',
            self::BOOKING_STATUS_COMPLETED => 'Completed',
            self::BOOKING_STATUS_FLAGGED => 'Flagged (checkout issues)',
            self::BOOKING_STATUS_CANCELLED => 'Cancelled',
            self::BOOKING_STATUS_RESCHEDULED => 'Rescheduled',
        ];
    }

    /**
     * Stay has ended through checkout (completed or flagged after inspection).
     */
    public function isStayCheckoutClosed(): bool
    {
        return in_array((string) $this->booking_status, [
            self::BOOKING_STATUS_COMPLETED,
            self::BOOKING_STATUS_FLAGGED,
        ], true);
    }

    public static function paymentStatusOptions(): array
    {
        return [
            self::PAYMENT_STATUS_UNPAID => 'Unpaid',
            self::PAYMENT_STATUS_PARTIAL => 'Partial',
            self::PAYMENT_STATUS_PAID => 'Paid',
            self::PAYMENT_STATUS_REFUND_PENDING => 'Refund Pending',
            self::PAYMENT_STATUS_NON_REFUNDABLE => 'Non-refundable',
            self::PAYMENT_STATUS_REFUNDED => 'Refunded',
        ];
    }

    public static function damageSettlementStatusOptions(): array
    {
        return [
            self::DAMAGE_SETTLEMENT_STATUS_NONE => 'None',
            self::DAMAGE_SETTLEMENT_STATUS_PENDING => 'Pending Settlement',
            self::DAMAGE_SETTLEMENT_STATUS_SETTLED => 'Settled',
        ];
    }

    /**
     * Payment settlement deadline on the receipt: 9:00 PM Asia/Manila on the check-in calendar day
     * (same moment as unpaid auto-cancel). Null when the booking has no check-in datetime.
     *
     * @param  int|null  $days  Ignored; retained for call-site compatibility.
     */
    public function unpaidExpiresAt(?int $days = null): ?Carbon
    {
        return $this->unpaidSettlementDeadlineManila();
    }

    /**
     * Whether the receipt should show the 3-day down payment policy
     * (advance bookings only — not instant or next-day stays).
     */
    public function downPaymentNoticeApplies(): bool
    {
        if (! $this->check_in || ! $this->created_at) {
            return false;
        }

        $checkInDay = $this->check_in->copy()->startOfDay();
        $createdDay = $this->created_at->copy()->startOfDay();

        if ($checkInDay->lt($createdDay)) {
            return false;
        }

        $leadDays = $createdDay->diffInDays($checkInDay);

        return $leadDays >= self::DOWN_PAYMENT_NOTICE_MIN_LEAD_DAYS;
    }

    /**
     * True when booking is still unpaid and the evaluation time is at or after 9:00 PM (Manila)
     * on the check-in calendar day.
     *
     * @param  int|null  $days  Ignored; retained for call-site compatibility.
     */
    public function isExpiredUnpaid(?Carbon $at = null, ?int $days = null): bool
    {
        if ($this->payment_status !== self::PAYMENT_STATUS_UNPAID
            || $this->booking_status !== self::BOOKING_STATUS_RESERVED) {
            return false;
        }

        if (! $this->check_in) {
            return false;
        }

        $at = $at ?? now();
        $deadline = $this->unpaidSettlementDeadlineManila($at);

        return $deadline !== null && $at->gte($deadline);
    }

    /**
     * Cancel this booking if it exceeded the unpaid expiry window.
     * Returns true when status was changed.
     */
    public function expireIfUnpaidExceededRule(?Carbon $at = null, ?int $days = null): bool
    {
        if (! $this->isExpiredUnpaid($at, $days)) {
            return false;
        }

        return DB::transaction(function () use ($at, $days): bool {
            $fresh = self::query()->lockForUpdate()->find($this->id);
            if (! $fresh || ! $fresh->isExpiredUnpaid($at, $days)) {
                return false;
            }

            $fresh->update(['booking_status' => self::BOOKING_STATUS_CANCELLED]);
            $this->refresh();

            return true;
        });
    }

    /**
     * @return array<string, string> color => booking_status value
     */
    public static function bookingStatusColors(): array
    {
        return [
            'info' => self::BOOKING_STATUS_PENDING_VERIFICATION,
            'primary' => self::BOOKING_STATUS_RESERVED,
            'warning' => self::BOOKING_STATUS_OCCUPIED,
            'secondary' => self::BOOKING_STATUS_COMPLETED,
            'gray' => self::BOOKING_STATUS_FLAGGED,
            'danger' => self::BOOKING_STATUS_CANCELLED,
            'default' => self::BOOKING_STATUS_RESCHEDULED,
        ];
    }

    /**
     * @return array<string, string> color => payment_status value
     */
    public static function paymentStatusColors(): array
    {
        return [
            'primary' => self::PAYMENT_STATUS_UNPAID,
            'info' => self::PAYMENT_STATUS_PARTIAL,
            'success' => self::PAYMENT_STATUS_PAID,
            'warning' => self::PAYMENT_STATUS_REFUND_PENDING,
            'danger' => self::PAYMENT_STATUS_NON_REFUNDABLE,
            'gray' => self::PAYMENT_STATUS_REFUNDED,
        ];
    }

    /**
     * @return array<string, string> color => damage_settlement_status value
     */
    public static function damageSettlementStatusColors(): array
    {
        return [
            'gray' => self::DAMAGE_SETTLEMENT_STATUS_NONE,
            'danger' => self::DAMAGE_SETTLEMENT_STATUS_PENDING,
            'success' => self::DAMAGE_SETTLEMENT_STATUS_SETTLED,
        ];
    }

    /**
     * Resolve payment status from recorded payments versus current booking total.
     */
    public static function paymentStatusFromAmounts(float $totalPrice, float $totalPaid): string
    {
        $totalPrice = max(0, $totalPrice);
        $totalPaid = max(0, $totalPaid);

        if ($totalPaid <= 0.009) {
            return self::PAYMENT_STATUS_UNPAID;
        }

        if ($totalPaid > ($totalPrice + 0.009)) {
            return self::PAYMENT_STATUS_REFUND_PENDING;
        }

        if ($totalPaid < ($totalPrice - 0.009)) {
            return self::PAYMENT_STATUS_PARTIAL;
        }

        return self::PAYMENT_STATUS_PAID;
    }

    /* ================= BLOCKED DATE CONFLICTS ================= */

    /**
     * Scope: bookings that overlap a given date (any part of that day).
     * Excludes cancelled (and optionally completed) so staff see active bookings.
     */
    public function scopeOverlappingDate($query, $date): Builder
    {
        $date = Carbon::parse($date);
        $dateStart = $date->copy()->startOfDay();
        $dateEnd = $date->copy()->endOfDay();

        return $query
            ->whereNotIn('booking_status', [self::BOOKING_STATUS_CANCELLED])
            ->where('check_in', '<=', $dateEnd)
            ->where('check_out', '>', $dateStart);
    }

    /**
     * Scope: bookings that occupy the lodging night for a calendar date (checkout day excluded).
     * Matches the room calendar: check-in Mar 29, check-out Mar 30 morning counts only Mar 29.
     */
    public function scopeOverlappingLodgingNight($query, $date): Builder
    {
        $d = Carbon::parse($date)->toDateString();

        return $query
            ->whereNotIn('booking_status', [self::BOOKING_STATUS_CANCELLED])
            ->whereDate('check_in', '<=', $d)
            ->whereDate('check_out', '>', $d);
    }

    /**
     * Scope: bookings whose calendar span includes this date (check-in date through check-out date, inclusive).
     * Used by the staff booking calendar grid and day-detail modal so the check-out day is included in the range
     * (distinct from {@see scopeOverlappingLodgingNight}, which excludes the check-out night).
     */
    public function scopeOverlappingCalendarInclusiveDisplay($query, $date): Builder
    {
        $d = Carbon::parse($date)->toDateString();

        return $query
            ->whereNotIn('booking_status', [self::BOOKING_STATUS_CANCELLED])
            ->whereDate('check_in', '<=', $d)
            ->whereDate('check_out', '>=', $d);
    }

    /**
     * Get bookings overlapping a date, with guest and assignable names for display.
     * Used by blocked-dates flow to show "contact customer first" info.
     *
     * @return array<int, array{id: int, reference_number: string, guest_name: string, email: string, contact_num: string, rooms: string, venues: string, check_in: string, check_out: string, booking_status: string, payment_status: string}>
     */
    public static function getConflictsForDate($date): array
    {
        $bookings = self::overlappingDate($date)
            ->with(['guest', 'rooms', 'venues'])
            ->orderBy('check_in')
            ->get();

        return $bookings->map(function (Booking $b) {
            return [
                'id' => $b->id,
                'reference_number' => $b->reference_number,
                'guest_name' => $b->guest?->full_name ?? '—',
                'email' => $b->guest?->email ?? '—',
                'contact_num' => $b->guest?->contact_num ?? '—',
                'rooms' => $b->rooms->pluck('name')->join(', ') ?: '—',
                'venues' => $b->venues->pluck('name')->join(', ') ?: '—',
                'check_in' => $b->check_in?->format('M j, Y g:i A') ?? '—',
                'check_out' => $b->check_out?->format('M j, Y g:i A') ?? '—',
                'booking_status' => $b->booking_status,
                'payment_status' => $b->payment_status,
            ];
        })->values()->all();
    }

    /**
     * Bookings overlapping a calendar day that include a given room (for staff block warnings).
     *
     * @return array<int, array{id: int, reference_number: string, guest_name: string, email: string, contact_num: string, rooms: string, venues: string, check_in: string, check_out: string, booking_status: string, payment_status: string}>
     */
    public static function getConflictsForRoomOnDate(int $roomId, $date): array
    {
        $bookings = self::overlappingDate($date)
            ->whereHas('rooms', fn ($q) => $q->where('rooms.id', $roomId))
            ->with(['guest', 'rooms', 'venues'])
            ->orderBy('check_in')
            ->get();

        return $bookings->map(function (Booking $b) {
            return [
                'id' => $b->id,
                'reference_number' => $b->reference_number,
                'guest_name' => $b->guest?->full_name ?? '—',
                'email' => $b->guest?->email ?? '—',
                'contact_num' => $b->guest?->contact_num ?? '—',
                'rooms' => $b->rooms->pluck('name')->join(', ') ?: '—',
                'venues' => $b->venues->pluck('name')->join(', ') ?: '—',
                'check_in' => $b->check_in?->format('M j, Y g:i A') ?? '—',
                'check_out' => $b->check_out?->format('M j, Y g:i A') ?? '—',
                'booking_status' => $b->booking_status,
                'payment_status' => $b->payment_status,
            ];
        })->values()->all();
    }

    /**
     * Bookings overlapping a calendar day that include a given venue (for staff block warnings).
     *
     * @return array<int, array{id: int, reference_number: string, guest_name: string, email: string, contact_num: string, rooms: string, venues: string, check_in: string, check_out: string, booking_status: string, payment_status: string}>
     */
    public static function getConflictsForVenueOnDate(int $venueId, $date): array
    {
        $bookings = self::overlappingDate($date)
            ->whereHas('venues', fn ($q) => $q->where('venues.id', $venueId))
            ->with(['guest', 'rooms', 'venues'])
            ->orderBy('check_in')
            ->get();

        return $bookings->map(function (Booking $b) {
            return [
                'id' => $b->id,
                'reference_number' => $b->reference_number,
                'guest_name' => $b->guest?->full_name ?? '—',
                'email' => $b->guest?->email ?? '—',
                'contact_num' => $b->guest?->contact_num ?? '—',
                'rooms' => $b->rooms->pluck('name')->join(', ') ?: '—',
                'venues' => $b->venues->pluck('name')->join(', ') ?: '—',
                'check_in' => $b->check_in?->format('M j, Y g:i A') ?? '—',
                'check_out' => $b->check_out?->format('M j, Y g:i A') ?? '—',
                'booking_status' => $b->booking_status,
                'payment_status' => $b->payment_status,
            ];
        })->values()->all();
    }

    /* ================= PAYMENT HELPERS ================= */

    /**
     * Get the total amount paid so far for this booking.
     */
    public function getTotalPaidAttribute(): int|float
    {
        return $this->payments()->sum('partial_amount');
    }

    /**
     * Get the remaining balance for this booking.
     */
    public function getBalanceAttribute(): int|float
    {
        return max(0, $this->total_price - $this->total_paid);
    }

    /**
     * Generate and save QR Code for the booking.
     */
    public function generateQrCode(): void
    {
        if (! empty($this->qr_code)) {
            // Older QR files may exist but not be valid SVG. If the stored file doesn't
            // look like an SVG, regenerate it.
            if (Storage::disk('public')->exists($this->qr_code)) {
                $existing = (string) Storage::disk('public')->get($this->qr_code);
                if (str_contains($existing, '<svg')) {
                    return;
                }
            } else {
                return;
            }
        }

        $qrData = json_encode([
            // Keep both key names for backward compatibility with any existing scanners.
            'booking_id' => $this->id,
            'reference' => $this->reference_number,
            'reference_number' => $this->reference_number,
            'guest_id' => $this->guest_id,
        ]);

        $path = 'qr/bookings/'.Str::uuid().'.svg';

        Storage::disk('public')->put(
            $path,
            QrCode::format('svg')->size(300)->generate($qrData)
        );

        $this->updateQuietly([
            'qr_code' => $path,
        ]);
    }

    /**
     * Whether the booking check-out date is today in Asia/Manila.
     */
    public function isCheckOutTodayManila(?Carbon $at = null): bool
    {
        if (! $this->check_out) {
            return false;
        }

        $at = $at ?? now();
        $tz = self::timezoneManila();
        $checkOutDay = $this->check_out->copy()->timezone($tz)->startOfDay();
        $today = $at->copy()->timezone($tz)->startOfDay();

        return $checkOutDay->equalTo($today);
    }

    /**
     * Whether the booking check-out calendar day is still in the future in Asia/Manila.
     */
    public function isCheckOutAfterTodayManila(?Carbon $at = null): bool
    {
        if (! $this->check_out) {
            return false;
        }

        $at = $at ?? now();
        $tz = self::timezoneManila();
        $checkOutDay = $this->check_out->copy()->timezone($tz)->startOfDay();
        $today = $at->copy()->timezone($tz)->startOfDay();

        return $checkOutDay->gt($today);
    }

    /**
     * Admin/staff checkout eligibility: occupied stay and payment already at least partial.
     */
    public function canAdminCheckout(?Carbon $at = null): bool
    {
        if ($this->trashed()) {
            return false;
        }

        if ($this->booking_status !== self::BOOKING_STATUS_OCCUPIED) {
            return false;
        }

        if (! in_array((string) $this->payment_status, [self::PAYMENT_STATUS_PARTIAL, self::PAYMENT_STATUS_PAID], true)) {
            return false;
        }

        if (! $this->check_out) {
            return false;
        }

        $at = $at ?? now();
        $tz = self::timezoneManila();
        $checkOutDay = $this->check_out->copy()->timezone($tz)->startOfDay();
        $today = $at->copy()->timezone($tz)->startOfDay();

        return $checkOutDay->gte($today);
    }

    public function adminCheckoutActionLabel(?Carbon $at = null): string
    {
        return $this->isCheckOutTodayManila($at) ? 'Checkout' : 'Checkout Early';
    }
}
