<x-filament-panels::page>
    <div class="space-y-6">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Choose a date range below. You’ll see the revenue summary for <strong>paid</strong> and <strong>completed</strong> bookings in that period, then use <strong>Export Revenue</strong> in the header to download a CSV (reference, guest, check-in/out, rooms, venues, revenue, status).
        </p>

        <div class="flex flex-wrap items-center gap-2">
            <span class="mr-1 text-sm font-medium text-gray-700 dark:text-gray-300">Quick range:</span>
            @foreach (['today' => 'Today', 'yesterday' => 'Yesterday', 'this_week' => 'This week', 'last_7_days' => 'Last 7 days', 'this_month' => 'This month', 'last_month' => 'Last month', 'last_30_days' => 'Last 30 days', 'this_year' => 'This year', 'last_year' => 'Last year'] as $preset => $label)
                <button
                    type="button"
                    wire:click="setDatePreset('{{ $preset }}')"
                    @class([
                        'inline-flex items-center justify-center rounded-lg px-3 py-1.5 text-sm font-medium transition',
                        'bg-primary-600 text-white shadow hover:bg-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400' => $datePreset === $preset,
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' => $datePreset !== $preset,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{ $this->form }}

        @php
            $summary = $this->revenueSummary;
        @endphp

        <div class="grid gap-4 sm:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">
                    <span class="inline-flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-banknotes" class="h-5 w-5 text-primary-500" />
                        Total revenue
                    </span>
                </x-slot>
                <p class="text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">
                    ₱ {{ $summary['valid'] ? number_format($summary['total_revenue'], 2) : '0.00' }}
                </p>
                @if ($summary['valid'] && $summary['from'] && $summary['to'])
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $summary['from']->format('M j, Y') }} – {{ $summary['to']->format('M j, Y') }}
                    </p>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    <span class="inline-flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-calendar-days" class="h-5 w-5 text-primary-500" />
                        Bookings
                    </span>
                </x-slot>
                <p class="text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">
                    {{ $summary['valid'] ? number_format($summary['booking_count']) : '0' }}
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    paid or completed in range
                </p>
            </x-filament::section>
        </div>

        @if ($summary['valid'] && $summary['booking_count'] > 0)
            <p class="text-sm text-success-600 dark:text-success-400">
                Ready to export {{ number_format($summary['booking_count']) }} {{ str('booking')->plural($summary['booking_count']) }}. Click <strong>Export Revenue</strong> above to download the CSV.
            </p>
        @elseif ($summary['valid'])
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No paid or completed bookings in this period. Try another range or export after you have revenue in the selected dates.
            </p>
        @endif
    </div>
</x-filament-panels::page>
