<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('status', [
                'unpaid',
                'confirmed',
                'occupied',
                'completed',
                'checked_in',
                'checked_out',
                'paid',
                'cancelled',
                'rescheduled' // ✅ ADD THIS
            ])->default('unpaid')->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('status', [
                'unpaid',
                'confirmed',
                'occupied',
                'completed',
                'checked_in',
                'checked_out',
                'paid',
                'cancelled'
            ])->default('unpaid')->change();
        });
    }
};