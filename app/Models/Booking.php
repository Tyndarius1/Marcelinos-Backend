<?php

namespace App\Models;

use App\Mail\BookingCreated;
use App\Support\RoomInventoryGroupKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'guest_id',
        'reference_number',
        'qr_code',
        'check_in',
        'check_out',
        'total_price',
        'status',
        'no_of_days',
        'venue_event_type',
        'testimonial_feedback_sent_at',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'total_price' => 'decimal:2',
        'no_of_days' => 'integer',
        'testimonial_feedback_sent_at' => 'datetime',
    ];

    protected static function booted()
    {
        /**
         * Generate reference number before create
         */
        static::creating(function ($booking) {
            $booking->reference_number =
                'MWA-'.now()->year.'-'.str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        });

        /**
         * Handle actions AFTER booking is created
         */
        static::created(function (Booking $booking) {
            $booking->generateQrCode();

            $booking->loadMissing('guest');
            if ($booking->guest && $booking->guest->email) {
                Mail::to($booking->guest->email)
                    ->send(new BookingCreated($booking));
            }
        });

        /**
         * Send testimonial feedback email immediately when status changes to completed
         */
        // static::updated(function (Booking $booking) {
        //     if (
        //         $booking->status === Booking::STATUS_COMPLETED &&
        //         $booking->guest && $booking->guest->email &&
        //         !$booking->testimonial_feedback_sent_at
        //     ) {
        //         \Illuminate\Support\Facades\Mail::to($booking->guest->email)
        //             ->send(new \App\Mail\TestimonialFeedbackEmail($booking));
        //         $booking->updateQuietly(['testimonial_feedback_sent_at' => now()]);
        //     }
        // });
        // ...existing code...
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

    /* ================= STATUSES ================= */

    const STATUS_UNPAID = 'unpaid';

    const STATUS_PARTIAL = 'partial';

    const STATUS_OCCUPIED = 'occupied';

    const STATUS_COMPLETED = 'completed';

    const STATUS_PAID = 'paid';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_RESCHEDULED = 'rescheduled';

    const UNPAID_EXPIRY_DAYS = 3;

    public static function statusOptions(): array
    {
        return [
            self::STATUS_UNPAID => 'Unpaid',
            self::STATUS_PARTIAL => 'Partial',
            self::STATUS_OCCUPIED => 'Occupied',
            self::STATUS_PAID => 'Paid',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_RESCHEDULED => 'Rescheduled',
        ];
    }

    /**
     * The moment an unpaid booking should be auto-cancelled.
     */
    public function unpaidExpiresAt(?int $days = null): ?Carbon
    {
        if (! $this->created_at) {
            return null;
        }

        return $this->created_at->copy()->addDays($days ?? self::UNPAID_EXPIRY_DAYS);
    }

    /**
     * True when booking is still unpaid and already past the unpaid expiry window.
     */
    public function isExpiredUnpaid(?Carbon $at = null, ?int $days = null): bool
    {
        if ($this->status !== self::STATUS_UNPAID) {
            return false;
        }

        $expiresAt = $this->unpaidExpiresAt($days);
        if (! $expiresAt) {
            return false;
        }

        return $expiresAt->lte($at ?? now());
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

            $fresh->update(['status' => self::STATUS_CANCELLED]);
            $this->refresh();

            return true;
        });
    }

    public static function statusColors(): array
    {
        return [
            'primary' => self::STATUS_UNPAID,
            'info' => self::STATUS_PARTIAL,
            'success' => self::STATUS_PAID,
            'warning' => self::STATUS_OCCUPIED,
            'secondary' => self::STATUS_COMPLETED,
            'danger' => self::STATUS_CANCELLED,
            'default' => self::STATUS_RESCHEDULED,
        ];
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
            ->whereNotIn('status', [self::STATUS_CANCELLED])
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
            ->whereNotIn('status', [self::STATUS_CANCELLED])
            ->whereDate('check_in', '<=', $d)
            ->whereDate('check_out', '>', $d);
    }

    /**
     * Get bookings overlapping a date, with guest and assignable names for display.
     * Used by blocked-dates flow to show "contact customer first" info.
     *
     * @return array<int, array{id: int, reference_number: string, guest_name: string, email: string, contact_num: string, rooms: string, venues: string, check_in: string, check_out: string, status: string}>
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
                'status' => $b->status,
            ];
        })->values()->all();
    }

    /**
     * Bookings overlapping a calendar day that include a given room (for staff block warnings).
     *
     * @return array<int, array{id: int, reference_number: string, guest_name: string, email: string, contact_num: string, rooms: string, venues: string, check_in: string, check_out: string, status: string}>
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
                'status' => $b->status,
            ];
        })->values()->all();
    }

    /**
     * Bookings overlapping a calendar day that include a given venue (for staff block warnings).
     *
     * @return array<int, array{id: int, reference_number: string, guest_name: string, email: string, contact_num: string, rooms: string, venues: string, check_in: string, check_out: string, status: string}>
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
                'status' => $b->status,
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
            return;
        }

        $qrData = json_encode([
            'booking_id' => $this->id,
            'reference' => $this->reference_number,
            'guest_id' => $this->guest_id,
        ]);

        $path = 'qr/bookings/'.Str::uuid().'.svg';

        Storage::disk('public')->put(
            $path,
            QrCode::size(300)->generate($qrData)
        );

        $this->updateQuietly([
            'qr_code' => $path,
        ]);
    }
}
