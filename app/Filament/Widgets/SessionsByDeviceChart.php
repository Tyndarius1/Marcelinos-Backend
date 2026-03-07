<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class SessionsByDeviceChart extends ChartWidget
{
    protected int | string | array $columnSpan = 1;

    protected int | string | array $columnStart = [
        'lg' => 3,
    ];

    protected ?string $maxHeight = '140px';

    public function getHeading(): string | HtmlString | null
    {
        $totalSessions = DB::table('sessions')->count();
        $total = number_format($totalSessions);
        return new HtmlString(
            '<div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">' .
            '<span>Sessions By Device</span>' .
            '<span style="font-weight: 600; color: #374151;">' . $total . '</span>' .
            '</div>'
        );
    }

    protected function getData(): array
    {
        $deviceRows = DB::table('sessions')
            ->selectRaw("CASE
                WHEN user_agent IS NULL OR TRIM(user_agent) = '' THEN 'Unknown'
                WHEN (user_agent LIKE '%iPad%' OR user_agent LIKE '%Tablet%' OR (user_agent LIKE '%Android%' AND user_agent NOT LIKE '%Mobile%')) THEN 'Tablet'
                WHEN (user_agent LIKE '%iPhone%' OR user_agent LIKE '%Mobile%' OR user_agent LIKE '%Android%') THEN 'Mobile'
                ELSE 'Desktop'
            END AS device")
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('device')
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $deviceRows->pluck('device')->all(),
            'datasets' => [
                [
                    'data' => $deviceRows->pluck('total')->map(fn ($value) => (int) $value)->all(),
                    'backgroundColor' => ['#22C55E', '#FAED33', '#3B82F6', '#A3A3A3'],
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
