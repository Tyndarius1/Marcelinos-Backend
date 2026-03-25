<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->date('blocked_on');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'blocked_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_blocked_dates');
    }
};
