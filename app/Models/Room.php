<?php

namespace App\Models;

use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Eloquent\Relations\RoomReviewsRelation;
use App\Models\Review;
use App\Support\RoomInventoryGroupKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Room extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $appends = [
        'featured_image_url',
        'gallery_urls',
    ];

    protected $fillable = ['name', 'description',  'capacity', 'type', 'price', 'status',];

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

    /**
     * Human-readable type label (Standard / Family / Deluxe).
     */
    public function typeDisplayLabel(): string
    {
        return self::typeOptions()[$this->type] ?? ucfirst((string) $this->type);
    }

    /**
     * "Standard - 1 Double Bed" style summary (matches guest UI / billing inner part).
     */
    public function typeDashBedSummary(): string
    {
        $this->loadMissing(['bedSpecifications', 'bedModifiers']);
        $typeLabel = $this->typeDisplayLabel();
        $bed = RoomInventoryGroupKey::bedSpecificationLine($this);
        if ($bed !== null) {
            return $typeLabel.' - '.$bed;
        }
        $desc = trim((string) $this->description);
        if ($desc !== '') {
            return $typeLabel.' - '.$desc;
        }

        return $typeLabel;
    }

    /**
     * Filament / admin selects: "Room 101 (Standard - 1 Double Bed)".
     */
    public function adminSelectLabel(): string
    {
        return trim((string) $this->name).' ('.$this->typeDashBedSummary().')';
    }

    /**
     * Room IDs matching guest-requested booking lines (same type + inventory group key).
     *
     * @param  Collection<int, BookingRoomLine>  $lines
     * @return array<int>
     */
    public static function idsMatchingBookingRoomLines(Collection $lines): array
    {
        if ($lines->isEmpty()) {
            return [];
        }

        $types = $lines->pluck('room_type')->unique()->values()->all();

        return static::query()
            ->where('status', '!=', self::STATUS_MAINTENANCE)
            ->whereIn('type', $types)
            ->with(['bedSpecifications', 'bedModifiers'])
            ->get()
            ->filter(function (Room $room) use ($lines) {
                $key = RoomInventoryGroupKey::forRoom($room);
                foreach ($lines as $line) {
                    if ($room->type === $line->room_type && $key === $line->inventory_group_key) {
                        return true;
                    }
                }

                return false;
            })
            ->pluck('id')
            ->all();
    }

    /**
     * IDs allowed in Filament "Assigned rooms" when the booking has requested room lines.
     * Returns matching inventory plus rooms already attached so labels do not disappear.
     * Null = no filter (show all non-maintenance rooms), e.g. admin-created bookings without lines.
     *
     * @return array<int>|null
     */
    public static function idsEligibleForBookingAssignment(Booking $booking): ?array
    {
        $booking->loadMissing(['roomLines', 'rooms']);

        if ($booking->roomLines->isEmpty()) {
            return null;
        }

        $matched = self::idsMatchingBookingRoomLines($booking->roomLines);
        $assigned = $booking->rooms->pluck('id')->all();

        return array_values(array_unique(array_merge($matched, $assigned)));
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

    public function roomBlockedDates()
    {
        return $this->hasMany(RoomBlockedDate::class);
    }

    /**
     * Scope: only rooms not booked (by a non-cancelled booking) in the given date range AND not in maintenance.
     * Overlap: booking.check_in < $checkOut AND booking.check_out > $checkIn
     * Also excludes staff-blocked calendar days on this room (room_blocked_dates).
     */
    public function scopeAvailableBetween($query, $checkIn, $checkOut, $excludeBookingId = null)
    {
        return $query->where('status', '!=', self::STATUS_MAINTENANCE)
            ->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut, $excludeBookingId) {
                $q->where('bookings.status', '!=', Booking::STATUS_CANCELLED)
                    ->when($excludeBookingId, fn ($q2) => $q2->where('bookings.id', '!=', $excludeBookingId))
                    ->where('bookings.check_in', '<', $checkOut)
                    ->where('bookings.check_out', '>', $checkIn);
            })
            ->whereDoesntHave('roomBlockedDates', function ($q) use ($checkIn, $checkOut) {
                $q->overlappingBookingRange($checkIn, $checkOut);
                
                // if ($excludeBookingId) {
                //     $q->where('bookings.id', '!=', $excludeBookingId);
                // }
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

    public function bedSpecifications()
    {
        return $this->belongsToMany(\App\Models\BedSpecification::class, 'bed_specification_room');
    }

    public function bedModifiers()
    {
        return $this->belongsToMany(\App\Models\BedModifier::class, 'bed_modifier_room');
    }
}