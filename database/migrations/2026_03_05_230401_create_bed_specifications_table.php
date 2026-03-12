<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bed_specifications', function (Blueprint $table) {
            $table->id();
            $table->string('specification'); // e.g. 1 single bed and 1 double bed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bed_specifications');
    }
};