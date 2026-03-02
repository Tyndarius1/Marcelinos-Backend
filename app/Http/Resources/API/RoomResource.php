<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'type' => $this->type,
            'price' => $this->price,
            'status' => $this->status,
            'bed_count' => $this->bed_count,
            'bed_type'  => $this->bed_type,
            'amenities' => $this->whenLoaded('amenities', $this->amenities),
            'featured_image' => $this->featured_image_url,
            'gallery' => $this->gallery_urls,
        ];
    }
}
