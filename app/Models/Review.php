<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Room;
use App\Models\Venue;

class Review extends Model
{
    use HasFactory;

    // For compatibility with Filament/resources expecting this relationship
    public function reviewable()
    {
        return $this->morphTo();
    }

    /* ================= RATING ================= */
    public static function ratingOptions(): array
    {
        return [
            1 => '1',
            2 => '2',
            3 => '3',
            4 => '4',
            5 => '5',
        ];
    }



    protected $fillable = [
        'guest_id',
        'booking_id',
        'reviewable_type',
        'reviewable_id',
        'is_site_review',
        'rating',
        'title',
        'comment',
        'is_approved',
        'reviewed_at',
    ];

    protected $casts = [
        'is_site_review' => 'boolean',
        'is_approved' => 'boolean',
        'reviewed_at' => 'datetime',
        'rating' => 'integer',
    ];

    /* ================= RELATIONSHIPS ================= */

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }



    /* ================= SCOPES ================= */

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }



    
}
