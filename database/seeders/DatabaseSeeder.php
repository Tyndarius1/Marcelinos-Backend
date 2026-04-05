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

        User::factory()->create([
            'name' => 'MWA-ADMIN-001',
            'email' => 'a@a.com',
            'password' => bcrypt('admin123'),
            'role' => 'admin',
        ]);

        User::factory()->create([
            'name' => 'MWA-STAFF-001',
            'email' => 's@s.com',
            'password' => bcrypt('staff123'),
            'role' => 'staff',
        ]);

    }
}
