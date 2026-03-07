<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Booking;
use Carbon\Carbon;

class MonthlyBookingsChart extends ChartWidget
{
    protected ?string $heading = 'Booking Trends - Last 12 Months';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'lg' => 2,
    ];

    protected int | string | array $columnStart = [
        'lg' => 1,
    ];
    protected int | string | array $rowSpan = 2;
    protected static ?string $minHeight = '200px';


    protected function getData(): array
    {
        $labels = [];
        $data = [];

        $start = now()->startOfMonth()->subMonths(11);
        $end = now()->startOfMonth();

        $period = new \DatePeriod($start, new \DateInterval('P1M'), $end->copy()->addMonth());

        foreach ($period as $date) {
            $labels[] = $date->format('M Y');
            $year = (int) $date->format('Y');
            $month = (int) $date->format('m');
            $data[] = Booking::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->count();
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Bookings',
                    'data' => $data,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                    'maxBarThickness' => 28,
                ],
            ],

        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        $dataset = $this->getData()['datasets'][0]['data'];
        $maxValue = max($dataset) ?: 10;
        $yMax = ceil($maxValue / 5) * 5;

        return [
            'responsive' => true,
            'maintainAspectRatio' => false, // height controlled by rowSpan
            'plugins' => [
                'tooltip' => [
                    'callbacks' => [
                        'label' => fn($context) => 'Bookings: '.$context['raw'],
                    ],
                ],
                'legend' => [
                    'display' => true,
                    'labels' => [
                        'boxWidth' => 10,
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'max' => $yMax,
                    'ticks' => [
                        'display' => true,
                        'stepSize' => 5,
                        'precision' => 0,
                        'color' => '#4b5563', // Tailwind gray-700
                        'font' => ['weight' => '500'],
                    ],
                    'grid' => [
                        'color' => 'rgba(203, 213, 225, 0.3)', // Tailwind gray-300
                    ],
                ],
                'x' => [
                    'ticks' => [
                        'color' => '#4b5563',
                        'font' => ['weight' => '500'],
                    ],
                    'grid' => [
                        'color' => 'rgba(203, 213, 225, 0.1)',
                    ],
                ],
            ],
        ];
    }
}
