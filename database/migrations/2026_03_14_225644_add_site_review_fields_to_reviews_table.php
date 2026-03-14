<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('reviews', 'is_site_review')) {
                $table->boolean('is_site_review')->default(false);
            }

            if (! Schema::hasColumn('reviews', 'reviewable_type') && ! Schema::hasColumn('reviews', 'reviewable_id')) {
                $table->nullableMorphs('reviewable');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'reviewable_type') && Schema::hasColumn('reviews', 'reviewable_id')) {
                $table->dropMorphs('reviewable');
            }

            if (Schema::hasColumn('reviews', 'is_site_review')) {
                $table->dropColumn('is_site_review');
            }
        });
    }
};
