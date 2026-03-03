<x-filament-panels::page>
    <style>
        /* Fix backgrounds + layout when printing */
        @media print {

            @page {
                margin: 10mm;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            .fi-topbar,
            .fi-sidebar,
            .fi-header,
            header,
            nav,
            aside,
            .no-print {
                display: none !important;
            }

            .fi-layout,
            .fi-main,
            .fi-main-ctn,
            .fi-page,
            .filament-page,
            .fi-app,
            [class*="fi-layout"],
            [class*="fi-main"],
            [class*="fi-page"] {
                margin: 0 !important;
                padding: 0 !important;
                left: 0 !important;
                right: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                transform: none !important;
                overflow: visible !important;
            }

            :root {
                color-scheme: light !important;
                --sidebar-width: 0px !important;
                --fi-sidebar-width: 0px !important;
            }

            body,
            html,
            main,
            section,
            .dark,
            [class*="bg-gray-"],
            [class*="bg-slate-"],
            [class*="dark:bg-"] {
                background: transparent !important;
                background-color: #ffffff !important;
                color: #000000 !important;
            }

            body,
            html {
                margin: 0 !important;
                padding: 0 !important;
            }

            .fi-main-ctn {
                overflow: visible !important;
            }
        }

        @media print {
            dialog[open] {
                display: none !important;
            }

            dialog::backdrop,
            ::backdrop {
                background: transparent !important;
                opacity: 0 !important;
            }

            [class*="backdrop"],
            [class*="overlay"],
            [data-headlessui-state],
            [data-dialog-backdrop],
            [data-overlay] {
                display: none !important;
                opacity: 0 !important;
                visibility: hidden !important;
            }
        }
    </style>

    {{-- Main interactive dashboard --}}
    <div id="mainDashboard">
        <div class="flex justify-end gap-2 no-print mb-6">
            <x-filament::button icon="heroicon-o-printer" onclick="triggerPrint('monthly_overview', 'null')"
                color="primary" outlined>
                Print Monthly Report
            </x-filament::button>
            <x-filament::button icon="heroicon-o-printer" onclick="triggerPrint('yearly_overview', 'null')"
                color="primary">
                Print Yearly Report
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
                        @php
                            // ✅ COUNT EVERYTHING (local + foreign + any address)
                            $unpaidCollection = $reports['unpaid'][$key] ?? collect();
                            $unpaidCount = $unpaidCollection->count();

                            // Keep your "highest" display source (name/sub) if you already compute it
                            $unpaidTop = $unpaid[$key] ?? null;
                        @endphp

                        <div wire:click="mountAction('viewBookings', { period: '{{ $key }}', type: 'unpaid' })"
                            class="cursor-pointer h-full relative flex flex-col justify-between overflow-hidden rounded-2xl bg-gray-50 p-6 shadow-sm ring-1 ring-gray-950/5 transition duration-300 hover:shadow-md hover:ring-primary-500/50 hover:-translate-y-1 dark:bg-white/5 dark:ring-white/10 dark:hover:ring-primary-400/50 group">
                            <div
                                class="absolute -right-6 -top-6 w-24 h-24 bg-primary-500/10 rounded-full blur-2xl group-hover:bg-primary-500/30 transition duration-500">
                            </div>

                            <div class="relative z-10">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>

                                @if ($unpaidCount > 0)
                                    <dd class="mt-2 flex flex-col gap-0.5">
                                        @if ($unpaidTop)
                                            <span
                                                class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white line-clamp-1">{{ $unpaidTop['name'] }}</span>
                                            @if(isset($unpaidTop['sub']) && $unpaidTop['sub'])
                                                <span
                                                    class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $unpaidTop['sub'] }}</span>
                                            @endif
                                        @else
                                            {{-- Fallback if "highest" info isn't available but bookings exist --}}
                                            <span class="text-xl font-medium tracking-tight text-[#6CAE30]">Data Available</span>
                                        @endif
                                    </dd>
                                @else
                                    <dd class="mt-2">
                                        <span class="text-xl font-medium tracking-tight text-gray-400 dark:text-gray-500">
                                            No Data
                                        </span>
                                    </dd>
                                @endif
                            </div>

                            <div class="mt-auto pt-4 flex flex-wrap items-center justify-between gap-2 relative z-10">
                                @if ($unpaidCount > 0)
                                    <span
                                        class="inline-flex items-center gap-x-1.5 rounded-full px-2 py-1 text-xs font-medium text-primary-700 bg-primary-50 dark:text-primary-400 dark:bg-primary-400/10">
                                        <x-filament::icon icon="heroicon-m-user-group" class="w-4 h-4" />
                                        {{ $unpaidCount }} Booking(s)
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
                        @php
                            // ✅ COUNT EVERYTHING (local + foreign + any address)
                            $successfulCollection = $reports['successful'][$key] ?? collect();
                            $successfulCount = $successfulCollection->count();

                            $successfulTop = $successful[$key] ?? null;
                        @endphp

                        <div wire:click="mountAction('viewBookings', { period: '{{ $key }}', type: 'successful' })"
                            class="cursor-pointer h-full relative flex flex-col justify-between overflow-hidden rounded-2xl bg-gray-50 p-6 shadow-sm ring-1 ring-gray-950/5 transition duration-300 hover:shadow-md hover:ring-success-500/50 hover:-translate-y-1 dark:bg-white/5 dark:ring-white/10 dark:hover:ring-success-400/50 group">
                            <div
                                class="absolute -right-6 -top-6 w-24 h-24 bg-success-500/10 rounded-full blur-2xl group-hover:bg-success-500/30 transition duration-500">
                            </div>

                            <div class="relative z-10">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>

                                @if ($successfulCount > 0)
                                    <dd class="mt-2 flex flex-col gap-0.5">
                                        @if ($successfulTop)
                                            <span
                                                class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white line-clamp-1">{{ $successfulTop['name'] }}</span>
                                            @if(isset($successfulTop['sub']) && $successfulTop['sub'])
                                                <span
                                                    class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $successfulTop['sub'] }}</span>
                                            @endif
                                        @else
                                            <span class="text-xl font-medium tracking-tight text-[#6CAE30]">Data Available</span>

                                        @endif
                                    </dd>
                                @else
                                    <dd class="mt-2">
                                        <span class="text-xl font-medium tracking-tight text-gray-400 dark:text-gray-500">
                                            No Data
                                        </span>
                                    </dd>
                                @endif
                            </div>

                            <div class="mt-auto pt-4 flex flex-wrap items-center justify-between gap-2 relative z-10">
                                @if ($successfulCount > 0)
                                    <span
                                        class="inline-flex items-center gap-x-1.5 rounded-full px-2 py-1 text-xs font-medium text-success-700 bg-success-50 dark:text-success-400 dark:bg-success-400/10">
                                        <x-filament::icon icon="heroicon-m-user-group" class="w-4 h-4" />
                                        {{ $successfulCount }} Booking(s)
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

    {{-- PRINT TEMPLATE BLOCKS --}}
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

    <div id="tpl-yearly_overview-null" style="display:none;">
        @include('filament.pages.partials.print-report-template', [
            'title' => 'Comprehensive Demographics Report',
            'subtitle' => 'Year: ' . $reportYear . '  ·  Generated: ' . now()->format('F j, Y, g:i a'),
            'localData' => $yearlyLocalDemographics->values(),
            'foreignData' => $yearlyForeignDemographics->values(),
        ])
    </div>

    <script>
        var _printTarget = null;

        window.addEventListener('beforeprint', function () {
            if (_printTarget) {
                document.getElementById('mainDashboard').style.display = 'none';
                _printTarget.style.display = 'block';
            }
        });

        window.addEventListener('afterprint', function () {
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