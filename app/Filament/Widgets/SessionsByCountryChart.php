<?php

namespace App\Filament\Widgets;

use App\Models\Guest;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\HtmlString;

class SessionsByCountryChart extends ChartWidget
{
    protected int | string | array $columnSpan = 1;

    protected int | string | array $columnStart = [
        'lg' => 3,
    ];

    protected ?string $maxHeight = '140px';

    public function getHeading(): string | HtmlString | null
    {
        $total = number_format(Guest::count());
        return new HtmlString(
            '<div style="display: flex; font-size: .9rem; justify-content: space-between; align-items: center; width: 100%;">' .
            '<span>Top 5 Leading Countries</span>' .
            '<span style="font-weight: 600; color: #374151;">' . $total . '</span>' .
            '</div>'
        );
    }

    protected function getData(): array
    {
        $rows = Guest::query()
            ->selectRaw("COALESCE(NULLIF(TRIM(country), ''), 'Unknown') as country")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('country')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return [
            'labels' => $rows->pluck('country')->all(),
            'datasets' => [
                [
                    'data' => $rows->pluck('total')->map(fn ($value) => (int) $value)->all(),
                    'backgroundColor' => ['#22C55E',' #FAED33',  '#3B82F6', '#EF4444', '#8B5CF6'],
                    'borderWidth' => 3,
                    'width' => 1,
                    'hoverOffset' => 5,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array | RawJs | null
    {
        return RawJs::make(<<<'JS'
{
    responsive: true,
    maintainAspectRatio: false,
    cutout: '55%',
    layout: {
        padding: 4,
    },
    elements: {
        arc: {
            borderColor: () => {
                const isDark = document.documentElement.classList.contains('dark')
                    || document.body.classList.contains('dark')
                    || window.matchMedia('(prefers-color-scheme: dark)').matches;

                return isDark ? '#0F172B' : '#FFFFFF';
            },
        },
    },
    datasets: {
        doughnut: {
            borderColor: (context) => {
                const isDark = document.documentElement.classList.contains('dark')
                    || document.body.classList.contains('dark')
                    || window.matchMedia('(prefers-color-scheme: dark)').matches;

                return isDark ? '#0F172B' : '#FFFFFF';
            },
        },
    },
    plugins: {
        legend: {
            position: 'left',
            labels: {
                padding: 10,
            },
        },
    },
}
JS);
    }
}
