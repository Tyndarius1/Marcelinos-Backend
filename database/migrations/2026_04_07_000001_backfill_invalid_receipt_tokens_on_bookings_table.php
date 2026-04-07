<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bookings')
            ->select(['id', 'receipt_token'])
            ->orderBy('id')
            ->get()
            ->each(function (object $booking): void {
                $token = (string) ($booking->receipt_token ?? '');

                if (! Str::isUuid($token)) {
                    DB::table('bookings')
                        ->where('id', $booking->id)
                        ->update(['receipt_token' => (string) Str::uuid()]);
                }
            });
    }

    public function down(): void
    {
        // Non-destructive data repair migration; no rollback action required.
    }
};
