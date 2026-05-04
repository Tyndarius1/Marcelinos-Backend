<?php

use App\Support\RoomChecklistDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_checklist_item_templates', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('default_charge')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        $defaults = [
            'Door lock / key',
            'Windows / curtains',
            'Lights / switches',
            'Aircon / remote',
            'TV / remote',
            'Wi-Fi info card (if applicable)',
            'Bed / mattress condition',
            'Bedsheets / pillowcases',
            'Pillows',
            'Blanket / comforter',
            'Towels (bath / hand)',
            'Toiletries (soap, shampoo, tissue)',
            'Bathroom cleanliness',
            'Shower / faucet working',
            'Toilet flush working',
            'Mirror',
            'Trash bin',
            'Table / chairs',
            'Cabinet / hangers',
            'Overall room cleanliness',
        ];

        $prices = RoomChecklistDefaults::prices();
        $now = now();
        $rows = [];
        foreach ($defaults as $index => $label) {
            $rows[] = [
                'label' => $label,
                'default_charge' => $prices[$label] ?? null,
                'is_active' => true,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('room_checklist_item_templates')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('room_checklist_item_templates');
    }
};

