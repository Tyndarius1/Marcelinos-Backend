<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BedSpecificationSeeder extends Seeder
{
    public function run(): void
    {
        // Use delete instead of truncate so FK actions (null/cascade) can run safely.
        DB::table('bed_specifications')->delete();

        // Insert bed specifications
        DB::table('bed_specifications')->insert([
            ['specification' => '1 Single Bed'],
            ['specification' => '2 Single Beds'],
            ['specification' => '1 Double Bed'],
            ['specification' => '1 Queen Bed'],
            ['specification' => '1 King Bed'],
            ['specification' => '2 Double Beds'],
        ]);
    }
}