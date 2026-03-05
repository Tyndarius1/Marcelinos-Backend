<?php

namespace App\Models;

use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Eloquent\Relations\RoomReviewsRelation;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Room extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $appends = [
        'featured_image_url',
        'gallery_urls',
    ];

    protected $fillable = ['name', 'description',  'capacity', 'type', 'price', 'status'];

    /* ================= TYPES ================= */
    const TYPE_STANDARD = 'standard';
    const TYPE_FAMILY = 'family';
    const TYPE_DELUXE = 'deluxe';

    public static function typeOptions(): array
    {
        return [
            self::TYPE_STANDARD => 'Standard',
            self::TYPE_FAMILY => 'Family',
            self::TYPE_DELUXE => 'Deluxe',
        ];
    }

    /* ================= STATUSES ================= */
    const STATUS_AVAILABLE = 'available';
    const STATUS_MAINTENANCE = 'maintenance';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_AVAILABLE => 'Available',
            self::STATUS_MAINTENANCE => 'Maintenance',
        ];
    }

    public static function statusColors(): array
    {
        return [
            'success' => self::STATUS_AVAILABLE,
            'secondary' => self::STATUS_MAINTENANCE,
        ];
    }

    /**
     * Define Media Collections
     * This tells Spatie how to handle your "Featured" vs "Gallery" logic.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured')
            ->singleFile(); // Ensures only one featured image exists

        $this->addMediaCollection('gallery'); // Allows multiple images
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

    public function bookings()
    {
        return $this->belongsToMany(Booking::class, 'booking_room')->withTimestamps();
    }

    /**
     * Scope: only rooms not booked (by a non-cancelled booking) in the given date range AND not in maintenance.
     * Overlap: booking.check_in < $checkOut AND booking.check_out > $checkIn
     */
    public function scopeAvailableBetween($query, $checkIn, $checkOut)
    {
        return $query->where('status', '!=', self::STATUS_MAINTENANCE)
            ->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
                $q->where('bookings.status', '!=', Booking::STATUS_CANCELLED)
                    ->where('bookings.check_in', '<', $checkOut)
                    ->where('bookings.check_out', '>', $checkIn);
            });
    }

    // Removed the public function images() method because the Image model is gone.
    // Spatie uses $this->getMedia() instead.

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class);
    }

    /**
     * Reviews for this room (via bookings that include this room).
     * Used by Filament ReviewsRelationManager.
     */
    public function reviews()
    {
        return new RoomReviewsRelation(Review::query(), $this);
    }

}