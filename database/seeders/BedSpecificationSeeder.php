<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BedSpecificationSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data (optional)
        DB::table('bed_specifications')->truncate();

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