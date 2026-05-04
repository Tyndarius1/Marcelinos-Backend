<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_checklist_items', function (Blueprint $table) {
            $table->string('evidence_photo_path')
                ->nullable()
                ->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('room_checklist_items', function (Blueprint $table) {
            $table->dropColumn('evidence_photo_path');
        });
    }
};
