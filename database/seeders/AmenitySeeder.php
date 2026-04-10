<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    /**
     * Seed amenities commonly used by rooms and venues.
     */
    public function run(): void
    {
        $amenities = [
            'Air Conditioning',
            'Free WiFi',
            'Smart TV',
            'Private Bathroom',
            'Hot and Cold Shower',
            'Mini Fridge',
            'Complimentary Toiletries',
            'Parking Space',
            'Swimming Pool Access',
            'Sound System',
            'Projector',
            'Catering Area',
        ];

        foreach ($amenities as $name) {
            Amenity::query()->firstOrCreate(['name' => $name]);
        }
    }
}
