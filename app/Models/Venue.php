<?php

namespace App\Models;

use App\Eloquent\Relations\VenueReviewsRelation;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Venue extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $appends = [
        'featured_image_url',
        'gallery_urls',
    ];

    protected $fillable = ['name', 'description', 'capacity', 'price', 'status'];

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

    /**
     * Scope: only venues available in the given date range.
     * Same logic as Room::scopeAvailableBetween: exclude maintenance and those
     * with a non-cancelled booking overlapping the range.
     */
    public function scopeAvailableBetween($query, $checkIn, $checkOut, $excludeBookingId = null)
    {
        return $query->where('status', '!=', self::STATUS_MAINTENANCE)
            ->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut, $excludeBookingId) {
                $q->where('bookings.status', '!=', Booking::STATUS_CANCELLED)
                    ->where('bookings.check_in', '<', $checkOut)
                    ->where('bookings.check_out', '>', $checkIn);
                
                if ($excludeBookingId) {
                    $q->where('bookings.id', '!=', $excludeBookingId);
                }
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