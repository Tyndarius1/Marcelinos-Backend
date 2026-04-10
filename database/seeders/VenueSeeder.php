<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class VenueSeeder extends Seeder
{
    /**
     * Seed venue inventory with per-event pricing.
     */
    public function run(): void
    {
        $amenityMap = Amenity::query()
            ->pluck('id', 'name');

        $venueData = [
            'name' => 'Marcelinos Grand Pavilion',
            'description' => 'Main covered venue ideal for weddings, birthdays, and corporate functions.',
            'capacity' => 300,
            'wedding_price' => 75000,
            'birthday_price' => 45000,
            'meeting_staff_price' => 30000,
            'status' => Venue::STATUS_AVAILABLE,
        ];

        /** @var Venue $venue */
        $venue = Venue::query()->updateOrCreate(
            ['name' => $venueData['name']],
            $venueData
        );

        $venueAmenityIds = collect([
            'Air Conditioning',
            'Free WiFi',
            'Parking Space',
            'Sound System',
            'Projector',
            'Catering Area',
        ])
            ->map(fn (string $name) => $amenityMap[$name] ?? null)
            ->filter()
            ->values()
            ->all();

        $venue->amenities()->sync($venueAmenityIds);
    }
}
