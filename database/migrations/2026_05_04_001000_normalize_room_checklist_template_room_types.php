<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $allowed = ['standard', 'family', 'deluxe'];

        DB::table('room_checklist_item_templates')
            ->select(['id', 'applicable_room_types'])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($allowed): void {
                foreach ($rows as $row) {
                    $raw = $row->applicable_room_types;
                    $decoded = [];

                    if (is_string($raw) && trim($raw) !== '') {
                        $json = json_decode($raw, true);
                        if (is_array($json)) {
                            $decoded = $json;
                        }
                    } elseif (is_array($raw)) {
                        $decoded = $raw;
                    }

                    $normalized = collect($decoded)
                        ->map(fn ($value): string => strtolower(trim((string) $value)))
                        ->filter(fn (string $value): bool => in_array($value, $allowed, true))
                        ->unique()
                        ->values()
                        ->all();

                    DB::table('room_checklist_item_templates')
                        ->where('id', $row->id)
                        ->update([
                            'applicable_room_types' => $normalized === [] ? null : json_encode($normalized),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // no-op
    }
};
