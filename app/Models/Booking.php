<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Jobs\SendBookingConfirmation;

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
            SendBookingConfirmation::dispatch($booking);
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
}
