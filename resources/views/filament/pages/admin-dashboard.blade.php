<x-filament-panels::page>
    <style>
        .admin-dashboard-shell {
            padding-right: 0.1rem;
            padding-left: 0.1rem;
        }

        /* Mobile: stack all charts vertically */
        .admin-dashboard-charts-row {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .admin-dashboard-left-chart,
        .admin-dashboard-right-charts {
            width: 100%;
        }

        .admin-dashboard-right-charts {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .admin-dashboard-chart-card {
            width: 100%;
        }

        .admin-dashboard-latest-bookings {
            width: 100%;
            overflow-x: auto;
        }

        /* Desktop: side-by-side layout with fixed heights */
        @media (min-width: 1024px) {
            .admin-dashboard-shell {
                padding-right: 0;
                padding-left: 0;
            }

            .admin-dashboard-charts-row {
                --admin-right-gap: 1.5rem;
                flex-direction: row;
                gap: var(--admin-right-gap);
                align-items: flex-start;
            }

            .admin-dashboard-left-chart {
                flex: 2;
                height: auto;
            }

            .admin-dashboard-right-charts {
                flex: 1;
                gap: var(--admin-right-gap);
                display: flex;
                flex-direction: column;
            }

            .admin-dashboard-chart-card {
                display: flex;
            }

            .admin-dashboard-right-charts .admin-dashboard-chart-card {
                flex: 1;
                min-height: 0;
                align-items: center;
                justify-content: center;
            }

            .admin-dashboard-chart-card > div,
            .admin-dashboard-chart-card .fi-wi-widget,
            .admin-dashboard-chart-card .fi-wi-chart,
            .admin-dashboard-chart-card .fi-section {
                width: 100%;
                height: 100%;
            }
        }
    </style>

    <div class="admin-dashboard-shell grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-3">
            @livewire(\App\Filament\Widgets\BookingStatsOverview::class)
        </div>

        <div class="lg:col-span-3 admin-dashboard-charts-row">
            <div class="admin-dashboard-left-chart admin-dashboard-chart-card">
                @livewire(\App\Filament\Widgets\MonthlyBookingsChart::class)
            </div>

            <div class="admin-dashboard-right-charts">
                <div class="admin-dashboard-chart-card">
                    @livewire(\App\Filament\Widgets\SessionsByCountryChart::class)
                </div>
                <div class="admin-dashboard-chart-card">
                    @livewire(\App\Filament\Widgets\SessionsByDeviceChart::class)
                </div>
            </div>
        </div>

        <div class="lg:col-span-3 admin-dashboard-latest-bookings">
            @livewire(\App\Filament\Widgets\LatestBookings::class)
        </div>
    </div>
    <script>
        const leftChart = document.querySelector('.admin-dashboard-left-chart');
        const rightCharts = document.querySelector('.admin-dashboard-right-charts');

        function syncHeight() {
            if (!leftChart || !rightCharts) return;
            // Only apply on desktop
            if (window.matchMedia("(min-width: 1024px)").matches) {
                const leftHeight = leftChart.offsetHeight;
                rightCharts.style.height = `${leftHeight}px`;
            } else {
                // Reset height on smaller screens
                rightCharts.style.height = 'auto';
            }
        }

        // Run on load and resize
        window.addEventListener('load', syncHeight);
        window.addEventListener('resize', syncHeight);
    </script>
</x-filament-panels::page>
