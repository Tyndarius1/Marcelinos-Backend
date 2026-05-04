<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_checklist_items', function (Blueprint $table): void {
            $table->unsignedInteger('quantity')
                ->default(1)
                ->after('charge');
        });
    }

    public function down(): void
    {
        Schema::table('room_checklist_items', function (Blueprint $table): void {
            $table->dropColumn('quantity');
        });
    }
};
