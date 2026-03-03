<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Guest;
use App\Models\Booking;
use Carbon\Carbon;

class GuestDemographicsTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define some fake municipalities
        $municipalities = ['Cebu City', 'Mandaue City', 'Lapu-Lapu City', 'Talisay City', 'Consolacion'];

        // Define timeframes
        $timeframes = [
            'today' => Carbon::today(),
            'next_7_days' => Carbon::today()->addDays(3),
            'this_month' => Carbon::now()->addDays(15),
            'next_month' => Carbon::now()->addMonth()->addDays(5)
        ];

        // Seed Guests
        $guests = [];
        for ($i = 0; $i < 20; $i++) {
            $guests[] = Guest::create([
                'first_name' => 'Test' . $i,
                'last_name' => 'Guest' . $i,
                'email' => "testguest{$i}@example.com",
                'contact_num' => '0912345678' . rand(0, 9),
                'is_international' => false,
                'municipality' => $municipalities[array_rand($municipalities)],
            ]);
        }

        // Seed Bookings (Unpaid/Pending)
        foreach ($timeframes as $time => $date) {
            for ($i = 0; $i < rand(2, 5); $i++) {
                Booking::create([
                    'guest_id' => $guests[array_rand($guests)]->id,
                    'status' => 'unpaid',
                    'check_in' => $date->copy()->setHour(14),
                    'check_out' => $date->copy()->addDays(2)->setHour(12),
                    'no_of_days' => 2,
                    'total_price' => rand(2000, 10000),
                ]);
            }
        }

        // Seed Bookings (Successful/Paid)
        foreach ($timeframes as $time => $date) {
            for ($i = 0; $i < rand(5, 12); $i++) {
                Booking::create([
                    'guest_id' => $guests[array_rand($guests)]->id,
                    'status' => 'paid',
                    'check_in' => $date->copy()->setHour(14),
                    'check_out' => $date->copy()->addDays(2)->setHour(12),
                    'no_of_days' => 2,
                    'total_price' => rand(2000, 10000),
                ]);
            }
        }

        $this->command->info('Fake bookings and guests created successfully to test demographics!');
    }
}
