<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('item_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('booking_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspected_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['clear', 'with_issues']);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('booking_id');
        });

        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('booking_inspections')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('room_inventory_items')->cascadeOnDelete();
            $table->enum('status', ['ok', 'damaged', 'missing']);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('inspection_item_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_item_id')->constrained('inspection_items')->cascadeOnDelete();
            $table->string('file_path');
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bookings MODIFY booking_status ENUM(
                'pending_verification',
                'reserved',
                'occupied',
                'completed',
                'flagged',
                'cancelled',
                'rescheduled'
            ) NOT NULL DEFAULT 'reserved'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::table('bookings')
                ->where('booking_status', 'flagged')
                ->update(['booking_status' => 'completed']);

            DB::statement("ALTER TABLE bookings MODIFY booking_status ENUM(
                'pending_verification',
                'reserved',
                'occupied',
                'completed',
                'cancelled',
                'rescheduled'
            ) NOT NULL DEFAULT 'reserved'");
        }

        Schema::dropIfExists('inspection_item_photos');
        Schema::dropIfExists('inspection_items');
        Schema::dropIfExists('booking_inspections');
        Schema::dropIfExists('room_inventory_items');
    }
};
