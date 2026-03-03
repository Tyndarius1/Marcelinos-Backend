<x-filament-panels::page>
    <style>
        /* Only hide Filament UI chrome when printing */
        @media print {

            .fi-topbar,
            .fi-sidebar,
            .fi-header,
            header,
            nav,
            aside,
            .no-print {
                display: none !important;
            }

            .fi-main,
            .fi-main-ctn {
                margin: 0 !important;
                padding: 0 !important;
                max-width: none !important;
            }

            body,
            html {
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
            }
        }
    </style>

    {{-- Main interactive dashboard --}}
    <div id="mainDashboard">
        <div class="flex justify-end no-print mb-6">
            <x-filament::button icon="heroicon-o-printer" onclick="triggerPrint('monthly_overview', 'null')"
                color="primary">
                Print Full Monthly Report
            </x-filament::button>
        </div>

        <div class="grid grid-cols-1 gap-8">
            {{-- Unpaid Bookings Section --}}
            <x-filament::section icon="heroicon-m-clock" icon-color="primary">
                <x-slot name="heading">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between w-full gap-2">
                        <span>Highest Unpaid Bookings (Pending)</span>
                        <button onclick="triggerPrint('unpaid', 'all')"
                            class="no-print px-3 py-1.5 rounded-lg text-sm font-bold bg-primary-50 text-primary-700 hover:bg-primary-100 transition-colors flex items-center gap-1.5 w-fit">
                            <x-filament::icon icon="heroicon-m-printer" class="w-4 h-4" />
                            Print All Pending
                        </button>
                    </div>
                </x-slot>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach (['today' => 'Today', 'next_7_days' => 'Next 7 Days', 'this_month' => 'This Month', 'next_month' => 'Next Month'] as $key => $label)
                        <div wire:click="mountAction('viewBookings', { period: '{{ $key }}', type: 'unpaid' })"
                            class="cursor-pointer relative flex flex-col justify-between overflow-hidden rounded-2xl bg-gray-50 p-6 shadow-sm ring-1 ring-gray-950/5 transition duration-300 hover:shadow-md hover:ring-primary-500/50 hover:-translate-y-1 dark:bg-white/5 dark:ring-white/10 dark:hover:ring-primary-400/50 group">
                            <div
                                class="absolute -right-6 -top-6 w-24 h-24 bg-primary-500/10 rounded-full blur-2xl group-hover:bg-primary-500/30 transition duration-500">
                            </div>
                            <div class="relative z-10">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                @if (isset($unpaid[$key]))
                                    <dd class="mt-2 flex flex-col gap-0.5">
                                        <span
                                            class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white line-clamp-1">{{ $unpaid[$key]['name'] }}</span>
                                        @if(isset($unpaid[$key]['sub']) && $unpaid[$key]['sub'])
                                            <span
                                                class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $unpaid[$key]['sub'] }}</span>
                                        @endif
                                    </dd>
                                @else
                                    <dd class="mt-2"><span
                                            class="text-xl font-medium tracking-tight text-gray-400 dark:text-gray-500">No
                                            Data</span></dd>
                                @endif
                            </div>
                            <div class="mt-4 flex items-center justify-between gap-x-2 relative z-10">
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
                                <button
                                    class="no-print p-1.5 rounded-md text-gray-400 hover:text-primary-700 hover:bg-primary-100 dark:hover:bg-white/10 transition"
                                    title="Print {{ $label }}"
                                    onclick="event.stopPropagation(); triggerPrint('unpaid','{{ $key }}')">
                                    <x-filament::icon icon="heroicon-m-printer" class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            {{-- Successful Bookings Section --}}
            <x-filament::section icon="heroicon-m-check-badge" icon-color="success">
                <x-slot name="heading">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between w-full gap-2">
                        <span>Highest Successful Bookings (Paid/Confirmed)</span>
                        <button onclick="triggerPrint('successful', 'all')"
                            class="no-print px-3 py-1.5 rounded-lg text-sm font-bold bg-success-50 text-success-700 hover:bg-success-100 transition-colors flex items-center gap-1.5 w-fit">
                            <x-filament::icon icon="heroicon-m-printer" class="w-4 h-4" />
                            Print All Confirmed
                        </button>
                    </div>
                </x-slot>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach (['today' => 'Today', 'next_7_days' => 'Next 7 Days', 'this_month' => 'This Month', 'next_month' => 'Next Month'] as $key => $label)
                        <div wire:click="mountAction('viewBookings', { period: '{{ $key }}', type: 'successful' })"
                            class="cursor-pointer relative flex flex-col justify-between overflow-hidden rounded-2xl bg-gray-50 p-6 shadow-sm ring-1 ring-gray-950/5 transition duration-300 hover:shadow-md hover:ring-success-500/50 hover:-translate-y-1 dark:bg-white/5 dark:ring-white/10 dark:hover:ring-success-400/50 group">
                            <div
                                class="absolute -right-6 -top-6 w-24 h-24 bg-success-500/10 rounded-full blur-2xl group-hover:bg-success-500/30 transition duration-500">
                            </div>
                            <div class="relative z-10">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                @if (isset($successful[$key]))
                                    <dd class="mt-2 flex flex-col gap-0.5">
                                        <span
                                            class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white line-clamp-1">{{ $successful[$key]['name'] }}</span>
                                        @if(isset($successful[$key]['sub']) && $successful[$key]['sub'])
                                            <span
                                                class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $successful[$key]['sub'] }}</span>
                                        @endif
                                    </dd>
                                @else
                                    <dd class="mt-2"><span
                                            class="text-xl font-medium tracking-tight text-gray-400 dark:text-gray-500">No
                                            Data</span></dd>
                                @endif
                            </div>
                            <div class="mt-4 flex items-center justify-between gap-x-2 relative z-10">
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
                                <button
                                    class="no-print p-1.5 rounded-md text-gray-400 hover:text-success-700 hover:bg-success-100 dark:hover:bg-white/10 transition"
                                    title="Print {{ $label }}"
                                    onclick="event.stopPropagation(); triggerPrint('successful','{{ $key }}')">
                                    <x-filament::icon icon="heroicon-m-printer" class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>
    </div>

    {{-- =====================================================================
    PRINT TEMPLATE BLOCKS
    Hidden via server-rendered inline style (NOT CSS classes).
    beforeprint/afterprint events swap visibility right before the
    browser renders the print layout — 100% reliable.
    ====================================================================== --}}

    @foreach (['unpaid' => 'Unpaid Bookings (Pending)', 'successful' => 'Successful Bookings (Paid/Confirmed)'] as $typeKey => $typeLabel)
        @foreach (['today' => 'Today', 'next_7_days' => 'Next 7 Days', 'this_month' => 'This Month', 'next_month' => 'Next Month', 'all' => 'All Time'] as $periodKey => $periodLabel)
            @php
                $rep = $reports[$typeKey][$periodKey];
                $repLocal = $rep->where('is_international', false)->values();
                $repForeign = $rep->where('is_international', true)->values();
            @endphp
            <div id="tpl-{{ $typeKey }}-{{ $periodKey }}" style="display:none;">
                @include('filament.pages.partials.print-report-template', [
                    'title' => 'Demographics Report: ' . $typeLabel,
                    'subtitle' => 'Period: ' . $periodLabel . '  ·  Printed: ' . now()->format('M d, Y'),
                    'localData' => $repLocal,
                    'foreignData' => $repForeign,
                ])
                            </div>
        @endforeach
    @endforeach

    <div id="tpl-monthly_overview-null" style="display:none;">
        @include('filament.pages.partials.print-report-template', [
            'title' => 'Comprehensive Demographics Report',
            'subtitle' => 'Month: ' . $reportMonth . '  ·  Generated: ' . now()->format('F j, Y, g:i a'),
            'localData' => $localDemographics->values(),
            'foreignData' => $foreignDemographics->values(),
        ])
    </div>

    <script>
        var _printTarget = null;

        // beforeprint fires synchronously right before browser renders print layout
        window.addEventListener('beforeprint', function () {
            if (_printTarget) {
                // Completely remove dashboard from print flow
                document.getElementById('mainDashboard').style.display = 'none';
                // Show only our report as a normal block
                _printTarget.style.display = 'block';
            }
        });

        // afterprint fires after user prints or cancels
        window.addEventListener('afterprint', function () {
            // Restore dashboard
            document.getElementById('mainDashboard').style.display = '';
            if (_printTarget) {
                _printTarget.style.display = 'none';
                _printTarget = null;
            }
        });

        function triggerPrint(type, period) {
            _printTarget = document.getElementById('tpl-' + type + '-' + period);
            if (!_printTarget) {
                console.warn('Print template not found: tpl-' + type + '-' + period);
                return;
            }
            window.print();
        }
    </script>
</x-filament-panels::page>
