<x-filament-panels::page>
    <style>
        @media print {

            .fi-topbar,
            .fi-sidebar,
            header.fi-header,
            .print-hidden {
                display: none !important;
            }

            .fi-main {
                margin: 0 !important;
                padding: 0 !important;
            }

            body {
                background: white !important;
            }

            .print-grid {
                display: block !important;
            }

            .print-break {
                page-break-inside: avoid;
            }
        }
    </style>

    <div class="flex justify-end print-hidden mb-6">
        <x-filament::button icon="heroicon-o-printer" onclick="window.print()" color="primary">
            Print Report
        </x-filament::button>
    </div>

    <!-- Title for print mainly -->
    <div class="hidden print:block mb-8 text-center">
        <h1 class="text-3xl font-bold text-gray-900">Guest Demographics Report</h1>
        <p class="text-gray-500">Generated on {{ now()->format('F j, Y, g:i a') }}</p>
    </div>

    <div class="grid grid-cols-1 gap-8">

        {{-- Unpaid Bookings Section --}}
        <x-filament::section icon="heroicon-m-clock" icon-color="primary" class="print-break">
            <x-slot name="heading">
                Highest Unpaid Bookings (Pending)
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 custom-stats-grid">
                @foreach (['today' => 'Today', 'next_7_days' => 'Next 7 Days', 'this_month' => 'This Month', 'next_month' => 'Next Month'] as $key => $label)
                    <div wire:click="mountAction('viewBookings', { period: '{{ $key }}', type: 'unpaid' })"
                        class="cursor-pointer relative flex flex-col justify-between overflow-hidden rounded-2xl bg-gray-50 p-6 shadow-sm ring-1 ring-gray-950/5 transition duration-300 hover:shadow-md hover:ring-primary-500/50 hover:-translate-y-1 dark:bg-white/5 dark:ring-white/10 dark:hover:ring-primary-400/50 group">
                        <!-- Decorative gradient blob -->
                        <div
                            class="absolute -right-6 -top-6 w-24 h-24 bg-primary-500/10 rounded-full blur-2xl group-hover:bg-primary-500/30 transition duration-500">
                        </div>

                        <div class="relative z-10">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            @if (isset($unpaid[$key]))
                                <dd class="mt-2 flex items-baseline gap-x-2">
                                    <span class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white line-clamp-1">
                                        {{ $unpaid[$key]['name'] }}
                                    </span>
                                </dd>
                            @else
                                <dd class="mt-2 flex items-baseline gap-x-2">
                                    <span class="text-xl font-medium tracking-tight text-gray-400 dark:text-gray-500">
                                        No Data
                                    </span>
                                </dd>
                            @endif
                        </div>

                        <div class="mt-4 flex items-center gap-x-2 text-sm relative z-10 block">
                            @if (isset($unpaid[$key]))
                                <span
                                    class="inline-flex items-center gap-x-1.5 rounded-full px-2 py-1 text-xs font-medium text-primary-700 bg-primary-50 dark:text-primary-400 dark:bg-primary-400/10">
                                    <x-filament::icon icon="heroicon-m-user-group" class="w-4 h-4" />
                                    {{ $unpaid[$key]['count'] }} Booking(s)
                                </span>
                            @else
                                <span
                                    class="inline-flex items-center gap-x-1.5 rounded-full px-2 py-1 text-xs font-medium text-gray-600 bg-gray-100 dark:text-gray-400 dark:bg-gray-400/10">
                                    <x-filament::icon icon="heroicon-m-minus" class="w-4 h-4" />
                                    0 Bookings
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- Successful Bookings Section --}}
        <x-filament::section icon="heroicon-m-check-badge" icon-color="success" class="print-break auto-rows-min">
            <x-slot name="heading">
                Highest Successful Bookings (Paid/Confirmed)
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 custom-stats-grid">
                @foreach (['today' => 'Today', 'next_7_days' => 'Next 7 Days', 'this_month' => 'This Month', 'next_month' => 'Next Month'] as $key => $label)
                    <div wire:click="mountAction('viewBookings', { period: '{{ $key }}', type: 'successful' })"
                        class="cursor-pointer relative flex flex-col justify-between overflow-hidden rounded-2xl bg-gray-50 p-6 shadow-sm ring-1 ring-gray-950/5 transition duration-300 hover:shadow-md hover:ring-success-500/50 hover:-translate-y-1 dark:bg-white/5 dark:ring-white/10 dark:hover:ring-success-400/50 group">
                        <!-- Decorative gradient blob -->
                        <div
                            class="absolute -right-6 -top-6 w-24 h-24 bg-success-500/10 rounded-full blur-2xl group-hover:bg-success-500/30 transition duration-500">
                        </div>

                        <div class="relative z-10">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            @if (isset($successful[$key]))
                                <dd class="mt-2 flex items-baseline gap-x-2">
                                    <span class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white line-clamp-1">
                                        {{ $successful[$key]['name'] }}
                                    </span>
                                </dd>
                            @else
                                <dd class="mt-2 flex items-baseline gap-x-2">
                                    <span class="text-xl font-medium tracking-tight text-gray-400 dark:text-gray-500">
                                        No Data
                                    </span>
                                </dd>
                            @endif
                        </div>

                        <div class="mt-4 flex items-center gap-x-2 text-sm relative z-10 block">
                            @if (isset($successful[$key]))
                                <span
                                    class="inline-flex items-center gap-x-1.5 rounded-full px-2 py-1 text-xs font-medium text-success-700 bg-success-50 dark:text-success-400 dark:bg-success-400/10">
                                    <x-filament::icon icon="heroicon-m-user-group" class="w-4 h-4" />
                                    {{ $successful[$key]['count'] }} Booking(s)
                                </span>
                            @else
                                <span
                                    class="inline-flex items-center gap-x-1.5 rounded-full px-2 py-1 text-xs font-medium text-gray-600 bg-gray-100 dark:text-gray-400 dark:bg-gray-400/10">
                                    <x-filament::icon icon="heroicon-m-minus" class="w-4 h-4" />
                                    0 Bookings
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>