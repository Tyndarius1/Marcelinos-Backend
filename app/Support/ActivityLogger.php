<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ActivityLogger
{
    public static function log(
        string $category,
        string $event,
        string $description,
        ?Model $subject = null,
        array $meta = [],
        ?int $userId = null,
    ): void {
        if ($subject instanceof ActivityLog) {
            return;
        }

        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        $request = request();

        ActivityLog::create([
            'user_id' => $userId ?? Auth::id(),
            'category' => $category,
            'event' => $event,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'meta' => empty($meta) ? null : $meta,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
