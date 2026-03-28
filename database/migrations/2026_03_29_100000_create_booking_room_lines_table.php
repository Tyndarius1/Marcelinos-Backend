<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guest-facing room intent: type + bed-spec group + quantity.
     * Specific physical rooms are attached later via booking_room (admin).
     */
    public function up(): void
    {
        Schema::create('booking_room_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('room_type', 32);
            $table->string('inventory_group_key', 512);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price_per_night', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_room_lines');
    }
};
