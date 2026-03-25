<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
                'MWA-' . now()->year . '-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        });

        /**
         * Handle actions AFTER booking is created
         */
        static::created(function (Booking $booking) {
            $booking->generateQrCode();

            $booking->loadMissing('guest');
            if ($booking->guest && $booking->guest->email) {
                \Illuminate\Support\Facades\Mail::to($booking->guest->email)
                    ->send(new \App\Mail\BookingCreated($booking));
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
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_OCCUPIED = 'occupied';
    const STATUS_COMPLETED = 'completed';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_RESCHEDULE = 'reschedule';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_UNPAID => 'Unpaid',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_OCCUPIED => 'Occupied',
            self::STATUS_PAID => 'Paid',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public static function statusColors(): array
    {
        return [
            'primary' => self::STATUS_UNPAID,
            'success' => self::STATUS_CONFIRMED,
            'warning' => self::STATUS_OCCUPIED,
            'secondary' => self::STATUS_COMPLETED,
            'info' => self::STATUS_PAID,
            'danger' => self::STATUS_CANCELLED,
        ];
    }

    /* ================= BLOCKED DATE CONFLICTS ================= */

    /**
     * Scope: bookings that overlap a given date (any part of that day).
     * Excludes cancelled (and optionally completed) so staff see active bookings.
     */
    public function scopeOverlappingDate($query, $date): \Illuminate\Database\Eloquent\Builder
    {
        $date = \Illuminate\Support\Carbon::parse($date);
        $dateStart = $date->copy()->startOfDay();
        $dateEnd = $date->copy()->endOfDay();

        return $query
            ->whereNotIn('status', [self::STATUS_CANCELLED])
            ->where('check_in', '<=', $dateEnd)
            ->where('check_out', '>', $dateStart);
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
        if (!empty($this->qr_code)) {
            return;
        }

        $qrData = json_encode([
            'booking_id' => $this->id,
            'reference' => $this->reference_number,
            'guest_id' => $this->guest_id,
        ]);

        $path = 'qr/bookings/' . \Illuminate\Support\Str::uuid() . '.svg';

        \Illuminate\Support\Facades\Storage::disk('public')->put(
            $path,
            \SimpleSoftwareIO\QrCode\Facades\QrCode::size(300)->generate($qrData)
        );

        $this->updateQuietly([
            'qr_code' => $path,
        ]);
    }
}
