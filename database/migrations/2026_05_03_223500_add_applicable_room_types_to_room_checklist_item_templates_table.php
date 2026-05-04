<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_checklist_item_templates', function (Blueprint $table) {
            $table->json('applicable_room_types')
                ->nullable()
                ->after('default_charge');
        });
    }

    public function down(): void
    {
        Schema::table('room_checklist_item_templates', function (Blueprint $table) {
            $table->dropColumn('applicable_room_types');
        });
    }
};
