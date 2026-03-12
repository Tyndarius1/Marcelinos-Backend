<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bed_modifiers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "w/Living Room", "w/Balcony"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bed_modifiers');
    }
};