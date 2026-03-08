<?php

namespace App\Filament\Widgets;

use App\Models\Guest;
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
            '<span>Leading 5 Countries</span>' .
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
                    'borderColor' => '#FFFFFF',
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
