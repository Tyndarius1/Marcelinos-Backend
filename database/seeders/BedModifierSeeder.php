<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BedModifierSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('bed_modifiers')->delete();

        DB::table('bed_modifiers')->insert([
            ['name' => 'w/Living Room'],
        ]);
    }
}