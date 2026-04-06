<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Soft deletes for Filament-managed records (recycle bin).
     *
     * @var array<int, string>
     */
    private array $tables = [
        'rooms',
        'venues',
        'users',
        'guests',
        'bookings',
        'reviews',
        'contact_us',
        'galleries',
        'blog_posts',
        'amenities',
        'bed_specifications',
        'blocked_dates',
        'room_blocked_dates',
        'venue_blocked_dates',
        'payments',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if (! Schema::hasColumn($table, 'deleted_at')) {
                    $blueprint->softDeletes();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if (Schema::hasColumn($table, 'deleted_at')) {
                    $blueprint->dropSoftDeletes();
                }
            });
        }
    }
};
