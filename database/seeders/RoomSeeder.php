<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\BedSpecification;
use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Seed sample room inventory and relation data.
     */
    public function run(): void
    {
        $bedSpecMap = BedSpecification::query()
            ->pluck('id', 'specification');

        $amenityMap = Amenity::query()
            ->pluck('id', 'name');

        $rooms = [
            [
                'name' => 'Room 101',
                'description' => 'Cozy standard room good for couples.',
                'capacity' => 2,
                'type' => Room::TYPE_STANDARD,
                'price' => 1800,
                'status' => Room::STATUS_AVAILABLE,
                'bed_specs' => ['1 Double Bed'],
                'amenities' => ['Air Conditioning', 'Free WiFi', 'Smart TV', 'Private Bathroom', 'Hot and Cold Shower'],
            ],
            [
                'name' => 'Room 102',
                'description' => 'Standard room with twin bed setup.',
                'capacity' => 2,
                'type' => Room::TYPE_STANDARD,
                'price' => 1700,
                'status' => Room::STATUS_AVAILABLE,
                'bed_specs' => ['2 Single Beds'],
                'amenities' => ['Air Conditioning', 'Free WiFi', 'Smart TV', 'Private Bathroom'],
            ],
            [
                'name' => 'Room 201',
                'description' => 'Spacious family room for small groups.',
                'capacity' => 4,
                'type' => Room::TYPE_FAMILY,
                'price' => 3200,
                'status' => Room::STATUS_AVAILABLE,
                'bed_specs' => ['1 Queen Bed and 1 Single Bed'],
                'amenities' => ['Air Conditioning', 'Free WiFi', 'Smart TV', 'Private Bathroom', 'Mini Fridge', 'Parking Space'],
            ],
            [
                'name' => 'Room 202',
                'description' => 'Family room ideal for weekend staycations.',
                'capacity' => 5,
                'type' => Room::TYPE_FAMILY,
                'price' => 3600,
                'status' => Room::STATUS_AVAILABLE,
                'bed_specs' => ['2 Double Beds'],
                'amenities' => ['Air Conditioning', 'Free WiFi', 'Smart TV', 'Private Bathroom', 'Mini Fridge', 'Swimming Pool Access'],
            ],
            [
                'name' => 'Room 301',
                'description' => 'Deluxe room with premium comfort.',
                'capacity' => 3,
                'type' => Room::TYPE_DELUXE,
                'price' => 4500,
                'status' => Room::STATUS_AVAILABLE,
                'bed_specs' => ['1 King Bed'],
                'amenities' => ['Air Conditioning', 'Free WiFi', 'Smart TV', 'Private Bathroom', 'Hot and Cold Shower', 'Mini Fridge', 'Complimentary Toiletries'],
            ],
        ];

        foreach ($rooms as $roomData) {
            $bedSpecNames = $roomData['bed_specs'];
            $amenityNames = $roomData['amenities'];

            unset($roomData['bed_specs'], $roomData['amenities']);

            /** @var Room $room */
            $room = Room::query()->updateOrCreate(
                ['name' => $roomData['name']],
                $roomData
            );

            $bedSpecIds = collect($bedSpecNames)
                ->map(fn (string $name) => $bedSpecMap[$name] ?? null)
                ->filter()
                ->values()
                ->all();

            $amenityIds = collect($amenityNames)
                ->map(fn (string $name) => $amenityMap[$name] ?? null)
                ->filter()
                ->values()
                ->all();

            $room->bedSpecifications()->sync($bedSpecIds);
            $room->amenities()->sync($amenityIds);
        }
    }
}
