<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Models\BlogPost;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateBlogPost extends CreateRecord
{
    protected static string $resource = BlogPostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $parsed = BlogPost::parseEmbedFieldInput($data['embed_src'] ?? '');
        $data['embed_src'] = $parsed['embed_src'];
        if ($parsed['iframe_width'] !== null) {
            $data['iframe_width'] = $parsed['iframe_width'];
        }
        if ($parsed['iframe_height'] !== null) {
            $data['iframe_height'] = $parsed['iframe_height'];
        }

        $base = Str::slug($data['slug'] ?? '');
        if ($base === '') {
            $base = BlogPost::slugFromTitle($data['title'] ?? '');
        }
        $data['slug'] = BlogPost::uniqueSlug($base);

        $data = $this->fillMetaDescriptionFromExcerpt($data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fillMetaDescriptionFromExcerpt(array $data): array
    {
        if (blank(trim((string) ($data['meta_description'] ?? '')))) {
            $data['meta_description'] = str()->limit((string) ($data['excerpt'] ?? ''), 320, '');
        }

        return $data;
    }
}
