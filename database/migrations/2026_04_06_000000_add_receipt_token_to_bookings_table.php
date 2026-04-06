<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('receipt_token', 36)->nullable()->unique()->after('reference_number');
        });

        DB::table('bookings')->orderBy('id')->pluck('id')->each(function (int $id): void {
            DB::table('bookings')->where('id', $id)->update([
                'receipt_token' => (string) Str::uuid(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['receipt_token']);
            $table->dropColumn('receipt_token');
        });
    }
};
