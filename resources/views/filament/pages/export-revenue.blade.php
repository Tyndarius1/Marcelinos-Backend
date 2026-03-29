<x-filament-panels::page>
    <div class="space-y-6">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Choose a date range below. You’ll see the revenue summary for <strong>paid</strong> and <strong>completed</strong> bookings in that period, then use <strong>Export Revenue</strong> in the header to download a CSV (reference, guest, check-in/out, rooms, venues, revenue, status).
        </p>

        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Year</span>
                <select
                    wire:model.live="revenueYear"
                    class="fi-input block min-w-[7rem] rounded-lg border border-gray-300 bg-white py-1.5 ps-3 pe-8 text-sm text-gray-950 shadow-sm transition duration-75 focus:border-primary-600 focus:ring-2 focus:ring-primary-600/20 disabled:bg-gray-50 disabled:text-gray-500 disabled:opacity-70 dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500/20"
                >
                    @for ($y = 2000; $y <= $this->maxSelectableRevenueYear(); $y++)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
                <span class="text-xs text-gray-500 dark:text-gray-400">2000 through {{ $this->maxSelectableRevenueYear() }} (includes a few future years for planning). Full year = Jan 1–Dec 31.</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="mr-1 text-sm font-medium text-gray-700 dark:text-gray-300">Months ({{ $revenueYear }})</span>
                @foreach ([
                    'jan' => 'January',
                    'feb' => 'February',
                    'mar' => 'March',
                    'apr' => 'April',
                    'may' => 'May',
                    'jun' => 'June',
                    'jul' => 'July',
                    'aug' => 'August',
                    'sep' => 'September',
                    'oct' => 'October',
                    'nov' => 'November',
                    'dec' => 'December',
                ] as $preset => $label)
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
