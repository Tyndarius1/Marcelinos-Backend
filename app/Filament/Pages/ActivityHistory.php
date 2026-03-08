<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ActivityHistory extends Page
{
    public int $logsLimit = 5;

    protected int $logsStep = 10;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Activity History';

    protected static string | \UnitEnum | null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Activity History';

    protected ?string $heading = 'Activity History';

    protected string $view = 'filament.pages.activity-history';

    public function getTimelineGroupsProperty(): Collection
    {
        $logs = ActivityLog::query()
            ->with('user:id,name')
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
        return ActivityLog::query()->count() > $this->logsLimit;
    }

    public function seeMore(): void
    {
        $this->logsLimit += $this->logsStep;
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

        if ($category === 'report') {
            return 'heroicon-o-document-arrow-down';
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

        if ($category === 'report') {
            return 'text-info-600';
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
            default => $this->stripActorPrefix($log),
        };
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
