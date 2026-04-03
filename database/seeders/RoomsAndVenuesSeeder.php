<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;
use App\Models\Venue;

class RoomsAndVenuesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Venues
        $venues = [

            [
                'name' => 'Air-Conditioned Venue',
                'description' => 'Fully air-conditioned venue ideal for weddings, birthdays, and meetings. Comfortably accommodates up to 50 guests, offering a cozy and versatile space for intimate events, seminars, and special celebrations. Well-maintained, accessible, and perfect for creating memorable experiences in a comfortable setting.',
                'capacity' => 50,
                'wedding_price' => 8000,
                'birthday_price' => 8000,
                'meeting_staff_price' => 8000,
                'status' => 'available',
            ],

            [
                'name' => 'Non Air-Conditioned Venue',
                'description' => 'Spacious, naturally ventilated venue perfect for weddings, birthdays, and meetings. Accommodates up to 80 guests, offering a comfortable and budget-friendly setting for intimate gatherings, seminars, and special occasions. Ideal for those who prefer an open-air ambiance with a relaxed and refreshing atmosphere.',
                'capacity' => 80,
                'wedding_price' => 12000,
                'birthday_price' => 8000,
                'meeting_staff_price' => 6000,
                'status' => 'available',
            ],

        ];

        foreach ($venues as $venue) {
            Venue::create($venue);
        }

        // 2. Rooms
        $rooms = [

            // STANDARD ROOM (6 total)
            ...array_fill(0, 6, [
                'name' => 'Standard Room',
                'description' => 'Cozy standard room designed for comfort and convenience. Features two single beds, ideal for up to 2 guests...',
                'capacity' => 2,
                'type' => 'standard',
                'price' => 1500,
                'status' => 'available',
                'specification_id' => 2,
            ]),

            // DELUXE ROOM (4 total)
            ...array_fill(0, 4, [
                'name' => 'Deluxe Room',
                'description' => 'Spacious deluxe room ideal for up to 3 guests...',
                'capacity' => 3,
                'type' => 'deluxe',
                'price' => 2200,
                'status' => 'available',
                'specification_id' => 3,
            ]),

            // FAMILY ROOM (1 total)
            [
                'name' => 'Family Room',
                'description' => 'Comfortable family room perfect for up to 4 guests...',
                'capacity' => 4,
                'type' => 'family',
                'price' => 3000,
                'status' => 'available',
                'specification_id' => 4,
            ],
        ];

        foreach ($rooms as $room) {
            Room::create($room);
        }
    }
}