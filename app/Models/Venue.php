<?php

namespace App\Models;

use App\Eloquent\Relations\VenueReviewsRelation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Venue extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $appends = [
        'featured_image_url',
        'gallery_urls',
    ];

    protected $fillable = [
        'name',
        'description',
        'capacity',
        'wedding_price',
        'birthday_price',
        'meeting_staff_price',
        'status',
    ];

    protected $casts = [
        'wedding_price' => 'decimal:2',
        'birthday_price' => 'decimal:2',
        'meeting_staff_price' => 'decimal:2',
    ];

    /* ================= STATUSES ================= */
    const STATUS_AVAILABLE = 'available';

    const STATUS_BOOKED = 'booked';

    const STATUS_MAINTENANCE = 'maintenance';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_AVAILABLE => 'Available',
            self::STATUS_BOOKED => 'Booked',
            self::STATUS_MAINTENANCE => 'Maintenance',
        ];
    }

    public static function statusColors(): array
    {
        return [
            'success' => self::STATUS_AVAILABLE,
            'danger' => self::STATUS_BOOKED,
            'secondary' => self::STATUS_MAINTENANCE,
        ];
    }

    /**
     * Define Media Collections
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured')
            ->singleFile();

        $this->addMediaCollection('gallery');
    }

    /**
     * Card-sized conversion for list/card views: smaller file, faster load.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')
            ->width(600)
            ->optimize()
            ->nonQueued();
    }

    public function getFeaturedImageUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('featured');

        return $media ? $this->resolveMediaUrl($media, 'card') : null;
    }

    public function getGalleryUrlsAttribute(): array
    {
        return $this->getMedia('gallery')
            ->map(fn (Media $media) => $this->resolveMediaUrl($media, 'card'))
            ->values()
            ->all();
    }

    private function resolveMediaUrl(Media $media, string $conversion = 'card'): string
    {
        $useConversion = $media->hasGeneratedConversion($conversion) ? $conversion : '';
        $lifetime = (int) config('media-library.temporary_url_default_lifetime', 60);

        if ($media->disk === 's3') {
            return $media->getTemporaryUrl(now()->addMinutes($lifetime), $useConversion);
        }

        return $media->getUrl($useConversion);
    }

    // General collection of images
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function bookings()
    {
        return $this->belongsToMany(Booking::class, 'booking_venue')->withTimestamps();
    }

    public function venueBlockedDates()
    {
        return $this->hasMany(VenueBlockedDate::class);
    }

    /**
     * Scope: only venues available in the given date range.
     * Excludes maintenance, staff-blocked calendar days (venue_blocked_dates),
     * and non-cancelled bookings overlapping the range.
     */
    public function scopeAvailableBetween($query, $checkIn, $checkOut, $excludeBookingId = null)
    {
        if (BlockedDate::overlapsRange($checkIn, $checkOut)) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where('status', '!=', self::STATUS_MAINTENANCE)
            ->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut, $excludeBookingId) {
                $q->where('bookings.status', '!=', Booking::STATUS_CANCELLED)
                    ->where('bookings.check_in', '<', $checkOut)
                    ->where('bookings.check_out', '>', $checkIn);

                if ($excludeBookingId) {
                    $q->where('bookings.id', '!=', $excludeBookingId);
                }
            })
            ->whereDoesntHave('venueBlockedDates', function ($q) use ($checkIn, $checkOut) {
                $q->overlappingBookingRange($checkIn, $checkOut);
            });
    }

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class);
    }

    /**
     * Reviews for this venue (via bookings that include this venue).
     * Used by Filament ReviewsRelationManager.
     */
    public function reviews()
    {
        return new VenueReviewsRelation(Review::query(), $this);
    }
}
