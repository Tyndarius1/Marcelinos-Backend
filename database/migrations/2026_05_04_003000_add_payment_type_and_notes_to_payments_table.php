<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_type', 32)
                ->default('booking')
                ->after('booking_id');
            $table->text('notes')
                ->nullable()
                ->after('provider_status');
            $table->index(['booking_id', 'payment_type']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['booking_id', 'payment_type']);
            $table->dropColumn(['payment_type', 'notes']);
        });
    }
};
