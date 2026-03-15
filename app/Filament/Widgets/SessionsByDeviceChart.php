<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class SessionsByDeviceChart extends ChartWidget
{
    private const DEVICE_COLORS = [
        'Desktop' => '#22C55E',
        'Mobile' => '#FAED33',
        'Tablet' => '#3B82F6',
        'Unknown' => '#A3A3A3',
    ];

    protected int | string | array $columnSpan = 1;

    protected int | string | array $columnStart = [
        'lg' => 3,
    ];

    protected ?string $maxHeight = '140px';

    protected function activeSinceTimestamp(): int
    {
        $lifetimeMinutes = (int) config('session.lifetime', 120);

        return now()->subMinutes($lifetimeMinutes)->getTimestamp();
    }

    protected function deviceCaseSql(): string
    {
        return "CASE
            WHEN sessions.user_agent IS NULL OR TRIM(sessions.user_agent) = '' THEN 'Unknown'
            WHEN (sessions.user_agent LIKE '%iPad%' OR sessions.user_agent LIKE '%Tablet%' OR (sessions.user_agent LIKE '%Android%' AND sessions.user_agent NOT LIKE '%Mobile%')) THEN 'Tablet'
            WHEN (sessions.user_agent LIKE '%iPhone%' OR sessions.user_agent LIKE '%Mobile%' OR sessions.user_agent LIKE '%Android%') THEN 'Mobile'
            ELSE 'Desktop'
        END";
    }

    public function getHeading(): string | HtmlString | null
    {
        $activeLogins = DB::table('sessions')
            ->where('last_activity', '>=', $this->activeSinceTimestamp())
            ->count();

        $total = number_format($activeLogins);

        return new HtmlString(
            '<div style="display: flex; font-size: .9rem; justify-content: space-between; align-items: center; width: 100%;">' .
            '<span>Active Devices</span>' .
            '<span style="font-weight: 600; color: #374151;">' . $total . '</span>' .
            '</div>'
        );
    }

    protected function getData(): array
    {
        $deviceRows = DB::table('sessions')
            ->where('last_activity', '>=', $this->activeSinceTimestamp())
            ->selectRaw($this->deviceCaseSql() . ' AS device')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('device')
            ->pluck('total', 'device');

        $labels = array_keys(self::DEVICE_COLORS);
        $data = array_map(
            fn (string $device): int => (int) ($deviceRows[$device] ?? 0),
            $labels,
        );

        $colors = array_map(
            fn (string $device): string => self::DEVICE_COLORS[$device],
            $labels,
        );

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderColor' => '#FFFFFF',
                    'borderWidth' => 3,
                    'hoverOffset' => 6,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'cutout' => '55%',
            'layout' => [
                'padding' => 4,
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'left',
                    'labels' => [
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                        'padding' => 10,
                    ],
                ],
            ],
        ];
    }
}
