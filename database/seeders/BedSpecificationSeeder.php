<?php

namespace Database\Seeders;

use App\Models\BedSpecification;
use Illuminate\Database\Seeder;

class BedSpecificationSeeder extends Seeder
{
    /**
     * Seed room bed configurations.
     */
    public function run(): void
    {
        $specifications = [
            '1 Single Bed',
            '1 Double Bed',
            '2 Single Beds',
            '1 Queen Bed',
            '1 King Bed',
            '1 Queen Bed and 1 Single Bed',
            '2 Double Beds',
        ];

        foreach ($specifications as $specification) {
            BedSpecification::query()->firstOrCreate([
                'specification' => $specification,
            ]);
        }
    }
}
