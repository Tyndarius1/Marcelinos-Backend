<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ActivityHistory extends Page
{
    public int $logsLimit = 5;

    public string $search = '';

    public string $dateMode = 'all_time';

    public ?string $selectedDate = null;

    protected int $logsStep = 10;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Activity History';

    protected static string | \UnitEnum | null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Activity History';

    protected ?string $heading = 'Activity History';

    protected string $view = 'filament.pages.activity-history';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->hasPrivilege('manage_activity_logs') ?? false;
    }

    public function getTimelineGroupsProperty(): Collection
    {
        $logs = $this->getLogsQuery()
            ->latest('created_at')
            ->limit($this->logsLimit)
            ->get();

        return $logs->groupBy(function (ActivityLog $log): string {
            $createdAt = Carbon::parse($log->created_at);

            if ($createdAt->isToday()) {
                return 'Today';
            }

            if ($createdAt->isYesterday()) {
                return 'Yesterday';
            }

            return $createdAt->format('F j, Y');
        });
    }

    public function getHasMoreLogsProperty(): bool
    {
        return $this->getLogsQuery()->count() > $this->logsLimit;
    }

    public function seeMore(): void
    {
        $this->logsLimit += $this->logsStep;
    }

    public function updatedSearch(): void
    {
        $this->logsLimit = 5;
    }

    public function updatedSelectedDate(): void
    {
        $this->logsLimit = 5;
    }

    public function updatedDateMode(): void
    {
        if ($this->dateMode !== 'custom_date') {
            $this->selectedDate = null;
        }

        $this->logsLimit = 5;
    }

    protected function getLogsQuery(): Builder
    {
        $search = trim($this->search);
        $selectedDate = trim((string) $this->selectedDate);

        return ActivityLog::query()
            ->with('user:id,name')
            ->when(
                $this->dateMode === 'custom_date' && $selectedDate !== '',
                fn (Builder $query): Builder => $query->whereDate('created_at', $selectedDate)
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%' . $search . '%';

                $query->where(function (Builder $subQuery) use ($like): void {
                    $subQuery
                        ->where('description', 'like', $like)
                        ->orWhere('event', 'like', $like)
                        ->orWhere('category', 'like', $like)
                        ->orWhere('ip_address', 'like', $like)
                        ->orWhere('user_agent', 'like', $like)
                        ->orWhereHas('user', function (Builder $userQuery) use ($like): void {
                            $userQuery->where('name', 'like', $like);
                        });
                });
            });
    }

    public function getLogIcon(string $category, string $event): string
    {
        if ($category === 'auth' && $event === 'user.login') {
            return 'heroicon-o-arrow-right-circle';
        }

        if ($category === 'auth' && $event === 'user.logout') {
            return 'heroicon-o-arrow-left-circle';
        }

        if ($category === 'booking') {
            return 'heroicon-o-calendar-days';
        }

        if ($category === 'room') {
            return 'heroicon-o-home-modern';
        }

        if ($category === 'venue') {
            return 'heroicon-o-building-office-2';
        }

        if ($category === 'report') {
            return 'heroicon-o-document-arrow-down';
        }

        if (str_contains($event, 'photo.uploaded')) {
            return 'heroicon-o-arrow-up-tray';
        }

        if (str_contains($event, 'photo.replaced')) {
            return 'heroicon-o-photo';
        }

        if (str_contains($event, 'created')) {
            return 'heroicon-o-plus-circle';
        }

        if (str_contains($event, 'updated')) {
            return 'heroicon-o-pencil-square';
        }

        if (str_contains($event, 'deleted')) {
            return 'heroicon-o-trash';
        }

        return 'heroicon-o-clock';
    }

    public function getLogIconColor(string $category, string $event): string
    {
        if ($category === 'auth' && $event === 'user.login') {
            return 'text-success-600';
        }

        if ($category === 'auth' && $event === 'user.logout') {
            return 'text-gray-500';
        }

        if ($category === 'booking') {
            return 'text-warning-600';
        }

        if ($category === 'room' || $category === 'venue') {
            return 'text-info-600';
        }

        if ($category === 'report') {
            return 'text-info-600';
        }

        if (str_contains($event, 'photo.uploaded') || str_contains($event, 'photo.replaced')) {
            return 'text-primary-600';
        }

        if (str_contains($event, 'created')) {
            return 'text-success-600';
        }

        if (str_contains($event, 'updated')) {
            return 'text-primary-600';
        }

        if (str_contains($event, 'deleted')) {
            return 'text-danger-600';
        }

        return 'text-gray-500';
    }

    public function getDisplayMessage(ActivityLog $log): string
    {
        return match (true) {
            $log->category === 'auth' && $log->event === 'user.login' => 'logged in.',
            $log->category === 'auth' && $log->event === 'user.logout' => 'logged out.',
            $log->category === 'report' && $log->event === 'report.downloaded' => $this->reportMessage($log),
            str_starts_with((string) $log->event, 'resource.') => $this->resourceMessage($log),
            default => $this->stripActorPrefix($log),
        };
    }

    public function getCategoryLabel(ActivityLog $log): string
    {
        if ($log->category !== 'resource') {
            return Str::headline((string) $log->category);
        }

        $model = (string) data_get($log->meta, 'model', 'resource');
        $baseModel = class_basename($model);
        $humanModel = trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $baseModel));

        return $humanModel !== '' ? $humanModel : 'Resource';
    }

    private function resourceMessage(ActivityLog $log): string
    {
        $clean = $this->stripActorPrefix($log);

        if (preg_match('/^([A-Za-z0-9_\\\\]+)\s(created|updated|deleted):\s(.+)\.$/i', $clean, $matches) !== 1) {
            return $clean;
        }

        $modelName = (string) $matches[1];
        $verb = strtolower((string) $matches[2]);
        $subject = trim((string) $matches[3]);

        $baseModelName = class_basename($modelName);
        $humanModelName = strtolower(trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $baseModelName)));
        $baseModelNameLower = strtolower($baseModelName);

        if ($baseModelNameLower === 'gallery') {
            return match ($verb) {
                'created' => 'added an image in gallery.',
                'updated' => 'updated an image in gallery.',
                'deleted' => 'deleted an image from gallery.',
                default => sprintf('%s %s: %s.', $verb, $humanModelName, $subject),
            };
        }

        if ($baseModelNameLower === 'review' && $verb === 'updated') {
            if (preg_match('/^(#[^\s]+)\s*\((.+)\)$/', $subject, $reviewMatches) === 1) {
                $reviewId = ltrim(trim((string) $reviewMatches[1]), '#');
                $friendlyChanges = $this->humanizeReviewChanges(trim((string) $reviewMatches[2]));

                if ($friendlyChanges !== '') {
                    return sprintf('updated review %s: %s.', $reviewId, $friendlyChanges);
                }
            }
        }

        if ($baseModelNameLower === 'blockeddate') {
            try {
                $subject = Carbon::parse($subject)->format('F d, Y');
            } catch (\Throwable) {
                // Keep original subject when it's not a parseable date (e.g. legacy #id logs).
            }
        }

        return sprintf('%s %s: %s.', $verb, $humanModelName, $subject);
    }

    private function humanizeReviewChanges(string $changesText): string
    {
        $parts = array_filter(array_map('trim', explode(';', $changesText)));

        if (empty($parts)) {
            return '';
        }

        $friendly = [];
        $newTargetType = null;
        $newTargetId = null;
        $approvalChangeMessage = null;

        foreach ($parts as $part) {
            if (preg_match('/^(.+?)\sfrom\s(.+?)\sto\s(.+)$/i', $part, $matches) !== 1) {
                $friendly[] = $part;

                continue;
            }

            $field = strtolower(trim((string) $matches[1]));
            $old = trim((string) $matches[2]);
            $new = trim((string) $matches[3]);

            if ($field === 'site review' || $field === 'approved') {
                $approvalChangeMessage = sprintf('Approved was changed from %s to %s', $old, $new);

                continue;
            }

            if ($field === 'reviewable type') {
                $newTargetType = $this->normalizeReviewTargetType($new);

                continue;
            }

            if ($field === 'reviewable id') {
                $newTargetId = strtolower($new) === 'null' ? null : $new;

                continue;
            }

            $friendly[] = sprintf('%s changed from %s to %s', $field, $old, $new);
        }

        if ($approvalChangeMessage !== null) {
            array_unshift($friendly, $approvalChangeMessage);
        }

        if ($newTargetType !== null || $newTargetId !== null) {
            if ($newTargetType === null && $newTargetId === null) {
                $friendly[] = 'unlinked the review from a target';
            } elseif ($newTargetType !== null && $newTargetId !== null) {
                $friendly[] = sprintf('linked to %s %s', $newTargetType, $newTargetId);
            } elseif ($newTargetType !== null) {
                $friendly[] = sprintf('linked to %s', $newTargetType);
            } else {
                $friendly[] = sprintf('linked to item %s', $newTargetId);
            }
        }

        return implode('; ', $friendly);
    }

    private function normalizeReviewTargetType(string $value): ?string
    {
        if (strtolower($value) === 'null') {
            return null;
        }

        return class_basename($value);
    }

    public function getDeviceName(ActivityLog $log): string
    {
        $agent = strtolower(trim((string) $log->user_agent));

        if ($agent === '') {
            return 'Unknown device';
        }

        if (str_contains($agent, 'ipad') || str_contains($agent, 'tablet') || (str_contains($agent, 'android') && ! str_contains($agent, 'mobile'))) {
            return 'Tablet';
        }

        if (str_contains($agent, 'iphone') || str_contains($agent, 'mobile') || str_contains($agent, 'android')) {
            return 'Mobile';
        }

        return 'Desktop';
    }

    public function getBrowserName(ActivityLog $log): string
    {
        $agent = strtolower(trim((string) $log->user_agent));

        if ($agent === '') {
            return 'Unknown browser';
        }

        if (str_contains($agent, 'edg/')) {
            return 'Edge';
        }

        if (str_contains($agent, 'opr/') || str_contains($agent, 'opera')) {
            return 'Opera';
        }

        if (str_contains($agent, 'chrome/')) {
            return 'Chrome';
        }

        if (str_contains($agent, 'firefox/')) {
            return 'Firefox';
        }

        if (str_contains($agent, 'safari/') && ! str_contains($agent, 'chrome/')) {
            return 'Safari';
        }

        if (str_contains($agent, 'trident/') || str_contains($agent, 'msie ')) {
            return 'Internet Explorer';
        }

        return 'Other browser';
    }

    private function reportMessage(ActivityLog $log): string
    {
        $type = str_replace('_', ' ', (string) data_get($log->meta, 'type', 'report'));
        $period = data_get($log->meta, 'period');

        if (is_string($period) && $period !== '') {
            return sprintf('downloaded %s report (%s).', $type, str_replace('_', ' ', $period));
        }

        $clean = $this->stripActorPrefix($log);

        return str_starts_with(strtolower($clean), 'downloaded ') ? $clean : 'downloaded report.';
    }

    private function stripActorPrefix(ActivityLog $log): string
    {
        $message = trim((string) $log->description);
        $actor = trim((string) ($log->user?->name ?? ''));

        if ($actor !== '' && str_starts_with($message, $actor . ' ')) {
            return ltrim(substr($message, strlen($actor)));
        }

        return $message;
    }
}
