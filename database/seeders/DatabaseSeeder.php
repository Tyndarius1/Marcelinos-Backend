<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            AmenitySeeder::class,
            BedSpecificationSeeder::class,
            RoomSeeder::class,
            VenueSeeder::class,
        ]);

        User::query()->updateOrCreate(
            ['email' => 'a@a.com'],
            [
                'name' => 'MWA-ADMIN-001',
                'password' => bcrypt('admin123'),
                'role' => 'admin',
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 's@s.com'],
            [
                'name' => 'MWA-STAFF-001',
                'password' => bcrypt('staff123'),
                'role' => 'staff',
            ]
        );

    }
}
