<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

        try {
            ActivityLog::create([
                'user_id' => $userId ?? Auth::id(),
                'category' => Str::limit($category, 50, ''),
                'event' => Str::limit($event, 80, ''),
                'subject_type' => $subject ? Str::limit($subject::class, 255, '') : null,
                'subject_id' => $subject?->getKey(),
                // Protect write path when summaries get long (e.g., large edited text fields).
                'description' => Str::limit($description, 255, ''),
                'meta' => empty($meta) ? null : $meta,
                'ip_address' => Str::limit((string) ($request?->ip() ?? ''), 45, ''),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Activity log write failed', [
                'message' => $exception->getMessage(),
                'event' => $event,
                'category' => $category,
            ]);

            report($exception);
        }
    }
}
