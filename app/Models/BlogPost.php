<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BlogPost extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'embed_src',
        'iframe_width',
        'iframe_height',
        'meta_description',
        'meta_keywords',
        'og_image',
        'excerpt',
        'published_at',
        'sort_order',
    ];

    protected $appends = [
        'featured_image_url',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'iframe_width' => 'integer',
            'iframe_height' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured')
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')
            ->width(800)
            ->optimize()
            ->nonQueued();
    }

    public function getFeaturedImageUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('featured');

        return $media ? $this->resolveMediaUrl($media, 'card') : null;
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

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * If the admin pasted full &lt;iframe&gt; HTML, extract src and optional width/height.
     * Otherwise returns trimmed text as embed_src and null dimensions.
     *
     * @return array{embed_src: string, iframe_width: ?int, iframe_height: ?int}
     */
    public static function parseEmbedFieldInput(?string $value): array
    {
        $raw = trim((string) $value);
        $result = [
            'embed_src' => $raw,
            'iframe_width' => null,
            'iframe_height' => null,
        ];

        if ($raw === '' || ! str_contains($raw, '<iframe')) {
            return $result;
        }

        if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $raw, $m)) {
            $result['embed_src'] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (preg_match('/\bwidth\s*=\s*["\']?(\d+)["\']?/i', $raw, $w)) {
            $result['iframe_width'] = (int) $w[1];
        }

        if (preg_match('/\bheight\s*=\s*["\']?(\d+)["\']?/i', $raw, $h)) {
            $result['iframe_height'] = (int) $h[1];
        }

        return $result;
    }

    public static function slugFromTitle(string $title): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'post';
        }

        return $base;
    }

    /** Ensure slug is unique; appends -2, -3, … if needed. */
    public static function uniqueSlug(string $base, ?int $exceptId = null): string
    {
        $candidate = $base !== '' ? $base : 'post';
        $suffix = 2;

        while (
            static::query()
                ->when($exceptId !== null, fn (Builder $q) => $q->where('id', '!=', $exceptId))
                ->where('slug', $candidate)
                ->exists()
        ) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
