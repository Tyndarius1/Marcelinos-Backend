@php
    use App\Models\Booking;
    use App\Models\Room;
    $legendItems = $this->calendarLegendItems;
    $roomTypeCapacities = $this->roomTypeCapacities;
    $spreadsheetId = trim((string) config('services.google_sheets.spreadsheet_id', ''));
    $spreadsheetUrl = $spreadsheetId !== '' ? "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/preview" : null;
    $reservationFilterLabels = [
        'room' => __('Rooms only'),
        'venue' => __('Venue only'),
        'both' => __('Room + Venue'),
    ];
    $referenceLinkClassByPayment = [
        Booking::PAYMENT_STATUS_PAID => 'text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300',
        Booking::PAYMENT_STATUS_PARTIAL => 'text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300',
        Booking::PAYMENT_STATUS_UNPAID => 'text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300',
        Booking::PAYMENT_STATUS_REFUND_PENDING => 'text-orange-600 hover:text-orange-700 dark:text-orange-400 dark:hover:text-orange-300',
        Booking::PAYMENT_STATUS_REFUNDED => 'text-rose-600 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300',
    ];
    $statusDisplayPillByPayment = [
        Booking::PAYMENT_STATUS_PARTIAL => 'bg-amber-100 text-amber-500 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/15 dark:text-amber-100 dark:ring-amber-400/25',
        Booking::PAYMENT_STATUS_PAID => 'bg-emerald-100 text-emerald-900 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/15 dark:text-emerald-100 dark:ring-emerald-400/25',
        Booking::PAYMENT_STATUS_UNPAID => 'bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-600/15 dark:bg-white/10 dark:text-gray-200 dark:ring-white/15',
        Booking::PAYMENT_STATUS_REFUND_PENDING => 'bg-orange-100 text-orange-900 ring-1 ring-inset ring-orange-600/20 dark:bg-orange-500/15 dark:text-orange-100 dark:ring-orange-400/25',
        Booking::PAYMENT_STATUS_REFUNDED => 'bg-rose-100 text-rose-900 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-500/15 dark:text-rose-100 dark:ring-rose-400/25',
    ];
@endphp

<x-filament-panels::page>
    <div
        class="booking-cal-page mx-auto w-full min-w-0 max-w-6xl space-y-5 b-8 transition-opacity duration-200 sm:space-y-8 sm:px-0 sm:pb-10"
        wire:loading.class="pointer-events-none opacity-60"
        wire:target="previousMonth,nextMonth,month,year,reservationFilter"
    >
        {{-- Page hero --}}
        <div
            class="relative overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10"
        >
            <div
                class="pointer-events-none absolute -right-24 -top-24 h-64 w-64 rounded-full bg-primary-500/10 blur-3xl dark:bg-primary-400/15"
            ></div>
            <div
                class="pointer-events-none absolute -bottom-20 -left-20 h-56 w-56 rounded-full bg-sky-400/10 blur-3xl dark:bg-sky-500/10"
            ></div>

            <div class="relative flex min-w-0 flex-col gap-6 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                <div class="min-w-0 flex-1 space-y-2">
                    <div class="flex min-w-0 items-start gap-3 sm:items-center">
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-white shadow-md shadow-primary-900/20 ring-1 ring-white/20 dark:bg-primary-500 dark:ring-white/10"
                        >
                            <x-filament::icon
                                icon="heroicon-o-calendar-days"
                                class="h-6 w-6"
                            />
                        </span>
                        <div class="min-w-0 flex-1">
                            <h1
                                class="text-xl font-semibold tracking-tight text-gray-950 sm:text-2xl dark:text-white"
                            >
                                {{ __('Booking Calendar') }}
                            </h1>
                            <p class="text-pretty break-words text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                                {{ __('Each calendar day from check-in through check-out is included. Use reservation type to separate Rooms only, Venue only, and Room + Venue bookings.') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="flex w-full min-w-0 max-w-full flex-wrap items-center gap-2 rounded-xl border border-gray-200/90 bg-gray-50/90 px-3 py-2 text-xs text-gray-600 shadow-inner sm:w-auto sm:max-w-none dark:border-white/10 dark:bg-white/5 dark:text-gray-300"
                >
                    <x-filament::icon
                        icon="heroicon-m-sparkles"
                        class="h-4 w-4 text-primary-600 dark:text-primary-400"
                    />
                    <span class="font-medium text-gray-700 dark:text-gray-200">
                        {{ $this->currentPeriodLabel() }}
                    </span>
                    <span class="hidden h-4 w-px bg-gray-300 sm:inline dark:bg-white/15"></span>
                    <span class="tabular-nums text-gray-500 dark:text-gray-400">
                        {{ __('Today:') }}
                        <time datetime="{{ now()->toDateString() }}">{{ now()->format('M j, Y') }}</time>
                    </span>

                    @if ($spreadsheetUrl)
                        <a
                            href="{{ $spreadsheetUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex w-full items-center justify-center rounded-md border border-primary-600 px-2 py-1 text-xs font-semibold text-primary-700 transition hover:bg-primary-50 dark:border-primary-400 dark:text-primary-300 dark:hover:bg-primary-500/10"
                        >
                            {{ __('View Data Backup') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Calendar card --}}
        <x-filament::section
            class="min-w-0 max-w-full"
            icon="heroicon-o-squares-2x2"
            icon-color="primary"
            :heading="$this->currentPeriodLabel()"
            :description="__('Use the controls to change month and reservation type. Each badge count shows how many bookings overlap that day for the selected reservation type.')"
        >
            <div class="space-y-6">
                {{-- Toolbar: prev/next aligned with select controls (bottom edge) --}}
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:gap-x-3 sm:gap-y-3">
                    <div
                        class="inline-flex w-full shrink-0 items-center justify-center gap-1 self-start rounded-xl border border-gray-200 bg-white px-1 py-1 shadow-sm dark:border-white/10 dark:bg-gray-950 sm:w-auto sm:justify-start sm:gap-0.5 sm:self-end sm:px-0.5 sm:py-0.5"
                    >
                        <x-filament::icon-button
                            color="gray"
                            icon="heroicon-m-chevron-left"
                            wire:click="previousMonth"
                            wire:loading.attr="disabled"
                            wire:target="previousMonth"
                            :label="__('Previous month')"
                            class="rounded-lg!"
                        />
                        <x-filament::icon-button
                            color="gray"
                            icon="heroicon-m-chevron-right"
                            wire:click="nextMonth"
                            wire:loading.attr="disabled"
                            wire:target="nextMonth"
                            :label="__('Next month')"
                            class="!rounded-lg"
                        />
                    </div>

                    <div class="grid min-w-0 w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-initial sm:gap-3">
                        <div class="min-w-0 sm:w-[10.5rem]">
                            <label
                                class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400"
                                for="room-cal-month"
                            >
                                {{ __('Month') }}
                            </label>
                            <x-filament::input.wrapper>
                                <x-filament::input.select
                                    id="room-cal-month"
                                    wire:model.live="month"
                                    class="w-full"
                                >
                                    @foreach (range(1, 12) as $m)
                                        <option value="{{ $m }}">
                                            {{ \Carbon\Carbon::createFromDate($this->year, $m, 1)->format('F') }}
                                        </option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>
                        <div class="min-w-0 sm:w-[8rem]">
                            <label
                                class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400"
                                for="room-cal-year"
                            >
                                {{ __('Year') }}
                            </label>
                            <x-filament::input.wrapper>
                                <x-filament::input.select
                                    id="room-cal-year"
                                    wire:model.live="year"
                                    class="w-full"
                                >
                                    @foreach ($this->yearOptions() as $y)
                                        <option value="{{ $y }}">{{ $y }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>
                    </div>

                    <div class="min-w-0 sm:w-[14rem]">
                        <label
                            class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400"
                            for="room-cal-reservation-filter"
                        >
                            {{ __('Reservation type') }}
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select
                                id="room-cal-reservation-filter"
                                wire:model.live="reservationFilter"
                                class="w-full"
                            >
                                <option value="room">{{ __('Rooms only') }}</option>
                                <option value="venue">{{ __('Venue only') }}</option>
                                <option value="both">{{ __('Room + Venue') }}</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                </div>

                {{-- Legend: wrap on narrow screens --}}
                <div
                    class="legend-strip rounded-xl border border-dashed border-gray-200/90 bg-gray-50/50 px-3 py-3 dark:border-white/10 dark:bg-white/[0.03] sm:px-4"
                    wire:key="calendar-legend-{{ $this->reservationFilter }}-{{ $this->month }}-{{ $this->year }}"
                >
                    <div
                        class="flex w-full flex-wrap items-center gap-2 sm:min-w-0 sm:gap-x-4 sm:gap-y-2"
                    >
                        <span
                            class="shrink-0 text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400"
                        >
                            {{ __('Legend') }}
                        </span>
                        @foreach ($legendItems as $type => $label)
                            @if ($this->reservationFilter === 'venue')
                                <span
                                    class="inline-flex w-[calc(50%-0.3rem)] flex-none items-center gap-1.5 rounded-full bg-gradient-to-r from-sky-100 to-cyan-100 px-2.5 py-1 text-[11px] font-semibold text-sky-800 ring-1 ring-inset ring-sky-600/20 dark:from-sky-500/15 dark:to-cyan-400/10 dark:text-sky-200 dark:ring-sky-400/25 sm:w-auto sm:max-w-[14rem]"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-sky-500/90 dark:bg-sky-300"></span>
                                    <span class="truncate">{{ $label }}</span>
                                </span>
                            @else
                                <x-room-type-badge
                                    class="w-full flex-none sm:w-auto sm:max-w-[14rem]"
                                    :type="$type"
                                />
                            @endif
                        @endforeach
                        @if ($this->reservationFilter !== 'venue')
                            <span
                                class="inline-flex w-full min-h-[2rem] flex-none items-center justify-center gap-1.5 rounded-lg border border-[#0e7490] bg-[#0891b2] px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm ring-1 ring-[#67e8f9] sm:w-auto sm:max-w-[14rem] sm:text-xs"
                            >
                                <x-filament::icon icon="heroicon-o-no-symbol" class="h-3.5 w-3.5" />
                                {{ __('Fully Booked') }}
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Calendar grid: responsive columns, no horizontal overflow --}}
                <div
                    class="-mx-1 px-1 pb-1 sm:mx-0 sm:px-0 sm:pb-0"
                >
                    <div
                        class="w-full space-y-1.5 sm:space-y-2"
                    >
                        {{-- Weekday strip --}}
                        <div class="hidden sm:grid grid-cols-7 gap-2">
                            @foreach ([['short' => 'S', 'full' => 'Sun'], ['short' => 'M', 'full' => 'Mon'], ['short' => 'T', 'full' => 'Tue'], ['short' => 'W', 'full' => 'Wed'], ['short' => 'T', 'full' => 'Thu'], ['short' => 'F', 'full' => 'Fri'], ['short' => 'S', 'full' => 'Sat']] as $dow)
                                <div
                                    class="rounded-md bg-gray-100 py-2.5 text-center text-[11px] font-semibold uppercase tracking-wider text-gray-500 sm:rounded-lg dark:bg-white/5 dark:text-gray-400"
                                >
                                    <span>{{ $dow['full'] }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Days --}}
                        <div
                            class="grid grid-cols-1 gap-2 sm:grid-cols-7"
                            wire:key="calendar-grid-{{ $this->reservationFilter }}-{{ $this->month }}-{{ $this->year }}"
                        >
                    @foreach ($this->calendarWeeks as $week)
                        @foreach ($week as $cell)
                            @php
                                $dowIndex = $loop->index;
                                $isWeekend = $dowIndex === 0 || $dowIndex === 6;
                                $isToday =
                                    $cell['inMonth'] && ($cell['dateStr'] ?? null) === now()->toDateString();
                                $isBlocked = (bool) ($cell['isBlocked'] ?? false);
                            @endphp
                            <div
                                @class([
                                    'hidden sm:flex' => ! $cell['inMonth'],
                                    'flex min-h-[5.5rem] flex-col rounded-xl border p-2.5 transition-colors sm:min-h-[8.5rem]',
                                    'border-transparent bg-gray-100/40 dark:bg-white/[0.02]' => ! $cell['inMonth'],
                                    'border-rose-500/90 bg-rose-600 shadow-sm text-white dark:border-rose-400/50 dark:bg-rose-700' => $cell['inMonth'] && $isBlocked,
                                    'border-gray-200/90 bg-white shadow-sm dark:border-white/10 dark:bg-gray-950/40' => $cell['inMonth'] && ! $isWeekend && ! $isBlocked,
                                    'border-gray-200/90 bg-slate-50/90 shadow-sm dark:border-white/10 dark:bg-slate-950/35' => $cell['inMonth'] && $isWeekend && ! $isBlocked,
                                    'ring-2 ring-primary-500/80 ring-offset-2 ring-offset-white dark:ring-primary-400/80 dark:ring-offset-gray-900' => $isToday,
                                ])
                            >
                                @if ($cell['inMonth'])
                                    @php
                                    @endphp
                                    <div class="mb-2 flex items-center justify-between gap-2 sm:mb-1.5 sm:items-start sm:gap-1">
                                        <div class="flex items-center gap-2">
                                            <span
                                                @class([
                                                    'flex h-6 min-w-[1.5rem] items-center justify-center rounded-md text-xs font-bold tabular-nums sm:h-7 sm:min-w-[1.75rem] sm:rounded-lg sm:text-sm',
                                                    'bg-primary-600 text-white shadow-sm dark:bg-primary-500' => $isToday,
                                                    'text-gray-900 dark:text-gray-100' => ! $isToday && ! $isBlocked,
                                                    'bg-rose-700 text-white shadow-sm dark:bg-rose-800' => $isBlocked && ! $isToday,
                                                ])
                                            >
                                                {{ $cell['day'] }}
                                            </span>
                                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 sm:hidden {{ $isBlocked ? '!text-rose-100 dark:!text-rose-100' : '' }}">
                                                {{ \Carbon\Carbon::parse($cell['dateStr'])->format('l') }}
                                            </span>
                                        </div>
                                        @if ($isBlocked)
                                            <span class="inline-flex items-center rounded-md bg-rose-900/35 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white ring-1 ring-inset ring-white/25">
                                                {{ __('Blocked') }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap content-start gap-1.5 sm:min-h-0 sm:flex-1 sm:flex-col sm:gap-1">
                                        @if ($isBlocked)
                                            <div class="mt-1 inline-flex w-full items-center justify-center rounded-lg bg-rose-900/30 px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-rose-50 ring-1 ring-inset ring-white/20 sm:mt-auto">
                                                {{ __('Unavailable for booking') }}
                                            </div>
                                        @else
                                            @foreach ($legendItems as $type => $label)
                                                @php $cnt = $cell['typeCounts'][$type] ?? 0; @endphp
                                                @if ($cnt === 0)
                                                    @continue
                                                @endif
                                                <button
                                                    type="button"
                                                    wire:click="openDayType('{{ $cell['dateStr'] }}', '{{ $type }}')"
                                                    class="group w-full cursor-pointer appearance-none rounded-lg border-0 bg-transparent p-0 text-left shadow-none transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 dark:focus-visible:ring-offset-gray-900"
                                                    title="{{ __('View bookings') }}"
                                                >
                                                    @if ($this->reservationFilter === 'venue')
                                                        <span
                                                            @class([
                                                                'inline-flex w-full items-center justify-between rounded-lg px-2 py-1 text-[11px] font-medium ring-1 ring-inset transition',
                                                                'bg-gradient-to-r from-sky-100 to-cyan-100 text-sky-900 ring-sky-600/25 shadow-sm dark:from-sky-500/20 dark:to-cyan-400/10 dark:text-sky-100 dark:ring-sky-400/30' => $cnt > 0,
                                                            ])
                                                        >
                                                            <span class="inline-flex min-w-0 items-center gap-1.5 pe-2">
                                                                <span class="h-1.5 w-1.5 rounded-full bg-sky-500 dark:bg-sky-300"></span>
                                                                <span class="truncate">{{ $label }}</span>
                                                            </span>
                                                            <span class="inline-flex h-4 min-w-[1.1rem] items-center justify-center rounded-full bg-white/80 px-1 text-[10px] font-semibold tabular-nums text-sky-800 ring-1 ring-sky-700/10 dark:bg-sky-900/50 dark:text-sky-100 dark:ring-sky-300/20">
                                                                {{ $cnt }}
                                                            </span>
                                                        </span>
                                                    @else
                                                        <x-room-type-badge
                                                            class="w-full"
                                                            :type="$type"
                                                            :muted="$cnt === 0"
                                                            :count="$cnt"
                                                            compact
                                                        />
                                                    @endif
                                                </button>
                                            @endforeach
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section
            class="min-w-0 max-w-full"
            icon="heroicon-o-clock"
            icon-color="success"
            :heading="__('Current overlapping bookings')"
            :description="__('Bookings overlapping today. Includes Room, Venue, and Room + Venue bookings.')"
        >
            @php
                $activeRows = $this->activeBookingRows;
                $activeCount = count($activeRows);
            @endphp
            <div class="space-y-3">
                <div class="inline-flex w-fit items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-gray-950/5 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10">
                    <span class="tabular-nums">{{ $activeCount }}</span>
                    <span class="ms-1">{{ $activeCount === 1 ? __('overlapping booking') : __('overlapping bookings') }}</span>
                </div>
                @if ($activeCount === 0)
                    <p class="rounded-xl border border-dashed border-gray-200 bg-gray-50/70 px-4 py-6 text-sm text-gray-600 dark:border-white/10 dark:bg-white/[0.03] dark:text-gray-300">
                        {{ __('No overlapping bookings for today.') }}
                    </p>
                @else
                    <ul class="space-y-3">
                        @foreach ($activeRows as $row)
                            <li class="rounded-xl border border-gray-200/90 bg-white px-4 py-3 text-sm shadow-sm dark:border-white/10 dark:bg-gray-950/50">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a
                                            href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $row['id']]) }}"
                                            class="inline-block break-words font-mono text-sm font-semibold hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 dark:focus-visible:ring-offset-gray-900 {{ $referenceLinkClassByPayment[$row['payment_status']] ?? $referenceLinkClassByPayment[Booking::PAYMENT_STATUS_UNPAID] }}"
                                        >
                                            {{ $row['reference_number'] }}
                                        </a>
                                        <p class="truncate text-gray-700 dark:text-gray-200">{{ $row['guest_name'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ __('Active for :range', ['range' => $row['active_date_range'] ?? '—']) }}
                                        </p>
                                    </div>
                                    <span
                                        @class([
                                            'rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                            $statusDisplayPillByPayment[$row['payment_status'] ?? ''] ?? $statusDisplayPillByPayment[Booking::PAYMENT_STATUS_UNPAID],
                                        ])
                                    >
                                        {{ $row['status_display'] ?? '—' }}
                                    </span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </x-filament::section>
    </div>

    {{-- Detail modal --}}
    @if ($modalDate && $modalType)
        <div
            class="fixed inset-0 z-50 flex items-end justify-center overflow-y-auto bg-gray-950/60 px-0 py-0 backdrop-blur-[2px] sm:items-center sm:px-4 sm:py-10"
            wire:click.self="closeModal"
            x-data
            x-on:keydown.escape.window="$wire.closeModal()"
            role="presentation"
        >
            <div
                class="relative flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl border border-gray-200/90 bg-white shadow-2xl ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10 sm:max-h-[85vh] sm:rounded-2xl"
                wire:click.stop
                role="dialog"
                aria-modal="true"
                aria-labelledby="room-cal-modal-title"
            >
                <div
                    class="relative border-b border-gray-200/80 bg-gradient-to-r from-gray-50 to-white px-5 py-4 dark:border-white/10 dark:from-gray-900 dark:to-gray-900"
                >
                    <div
                        class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary-600 via-sky-500 to-primary-600 dark:from-primary-500 dark:via-sky-400 dark:to-primary-500"
                    ></div>
                    <div class="flex items-start justify-between gap-3 pt-0.5">
                        <div class="min-w-0 space-y-1">
                            <div class="flex items-center gap-2 text-primary-600 dark:text-primary-400">
                                <x-filament::icon
                                    icon="heroicon-o-calendar-days"
                                    class="h-5 w-5 shrink-0"
                                />
                                <p
                                    id="room-cal-modal-title"
                                    class="truncate text-base font-semibold text-gray-950 dark:text-white"
                                >
                                    {{ $this->modalHeadingLabel() }}
                                </p>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ __('Bookings for this reservation type on the selected day.') }}
                            </p>
                        </div>
                        <x-filament::icon-button
                            color="gray"
                            icon="heroicon-m-x-mark"
                            wire:click="closeModal"
                            :label="__('Close')"
                            class="shrink-0"
                        />
                    </div>
                </div>

                <div class="space-y-5 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                    @php
                        $count = count($this->modalBookingRows);
                    @endphp

                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <div class="min-w-0 max-w-full sm:max-w-md">
                            @if ($this->reservationFilter === 'venue')
                                <span
                                    @class([
                                        'inline-flex w-full items-center justify-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset sm:w-auto sm:min-w-[14rem]',
                                        'bg-gray-100 text-gray-500 ring-gray-300/60 dark:bg-white/[0.06] dark:text-gray-400 dark:ring-white/10' => $count === 0,
                                        'bg-gradient-to-r from-sky-100 to-cyan-100 text-sky-900 ring-sky-600/25 dark:from-sky-500/20 dark:to-cyan-400/10 dark:text-sky-100 dark:ring-sky-400/30' => $count > 0,
                                    ])
                                >
                                    {{ $this->modalTypeLabel() }}
                                </span>
                            @else
                                <x-room-type-badge
                                    class="w-full sm:w-auto sm:min-w-[14rem]"
                                    :type="$modalType"
                                    :muted="$count === 0"
                                />
                            @endif
                        </div>
                        <span
                            class="inline-flex w-fit items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-gray-950/5 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10"
                        >
                            <span class="tabular-nums">{{ $count }}</span>
                            <span class="ms-1">{{ $count === 1 ? __('booking') : __('bookings') }}</span>
                        </span>
                    </div>

                    @if ($count === 0)
                        <div
                            class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-gray-200 bg-gray-50/80 px-6 py-12 text-center dark:border-white/10 dark:bg-white/[0.03]"
                        >
                            <div
                                class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10"
                            >
                                <x-filament::icon
                                    icon="heroicon-o-calendar"
                                    class="h-9 w-9 text-gray-300 dark:text-gray-500"
                                />
                            </div>
                            <div class="space-y-1">
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                    {{ __('No bookings for this date') }}
                                </p>
                                <p class="max-w-xs text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                    {{ __('No bookings found for this reservation type on the selected day.') }}
                                </p>
                            </div>
                        </div>
                    @else
                        <ul class="space-y-3">
                            @foreach ($this->modalBookingRows as $row)
                                <li
                                    class="overflow-visible rounded-xl border border-gray-200/90 bg-white shadow-sm ring-1 ring-gray-950/[0.04] dark:border-white/10 dark:bg-gray-950/50 dark:ring-white/[0.06]"
                                >
                                    <div
                                        class="flex flex-col gap-3 border-b border-gray-100 px-3 py-3 dark:border-white/5 sm:flex-row sm:items-start sm:justify-between sm:px-4"
                                    >
                                        <div class="min-w-0">
                                            <button
                                                type="button"
                                                @click.stop="window.location.assign('{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $row['id']]) }}')"
                                                class="break-all text-left font-mono text-sm font-semibold text-primary-600 hover:text-primary-700 hover:underline dark:text-primary-400 dark:hover:text-primary-300"
                                            >
                                                {{ $row['reference_number'] }}
                                            </button>
                                            <p class="mt-0.5 break-words text-sm text-gray-700 dark:text-gray-200">
                                                {{ $row['guest_name'] }}
                                            </p>
                                            @php
                                                $badgeKind = $row['booking_badge_kind'] ?? 'room';
                                            @endphp
                                            <p class="mt-2">
                                                @if ($badgeKind === 'both')
                                                    <span
                                                        class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-900 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/15 dark:text-amber-100 dark:ring-amber-400/25"
                                                    >
                                                        {{ __('Room + Venue') }}
                                                    </span>
                                                @elseif ($badgeKind === 'venue')
                                                    <span
                                                        class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-900 ring-1 ring-inset ring-sky-600/20 dark:bg-sky-500/15 dark:text-sky-100 dark:ring-sky-400/25"
                                                    >
                                                        {{ __('Venue') }}
                                                    </span>
                                                @else
                                                    <span
                                                        class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-900 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/15 dark:text-emerald-100 dark:ring-emerald-400/25"
                                                    >
                                                        {{ __('Room') }}
                                                    </span>
                                                @endif
                                            </p>
                                        </div>
                                        <div class="flex shrink-0 items-start justify-between gap-2 sm:justify-start">
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    @class([
                                                        'rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                                        $statusDisplayPillByPayment[$row['payment_status'] ?? ''] ?? $statusDisplayPillByPayment[Booking::PAYMENT_STATUS_UNPAID],
                                                    ])
                                                >
                                                    {{ $row['status_display'] ?? '—' }}
                                                </span>
                                                @if (($row['has_special_discount'] ?? false) === true)
                                                    <span
                                                        class="inline-flex items-center gap-1 rounded-full border border-amber-500/40 bg-amber-950 px-2.5 py-0.5 text-[9px] font-semibold uppercase tracking-[0.12em] text-amber-200 shadow-sm dark:border-amber-300/35 dark:bg-amber-900 dark:text-amber-100"
                                                        title="{{ $row['discount_tooltip'] ?? __('Special discount applied') }}"
                                                        aria-label="{{ $row['discount_tooltip'] ?? __('Special discount applied') }}"
                                                    >
                                                        <span class="h-1 w-1 rounded-full bg-amber-300/90 dark:bg-amber-200/90"></span>
                                                        <span>{{ $row['discount_badge_text'] ?? __('Discount') }}</span>
                                                    </span>
                                                @endif
                                            </div>

                                            <div
                                                class="relative"
                                                x-data="{
                                                    open: false,
                                                    payOpen: false,
                                                    payId: {{ (int) $row['id'] }},
                                                    completeOpen: false,
                                                    completeId: {{ (int) $row['id'] }},
                                                    cancelOpen: false,
                                                    cancelId: {{ (int) $row['id'] }},
                                                    delOpen: false,
                                                    delVal: '',
                                                    delConfirm: false,
                                                    delRef: @js($row['reference_number']),
                                                    delId: {{ (int) $row['id'] }},
                                                    submitPayBalance() {
                                                        $wire.payBalance(this.payId);
                                                        this.payOpen = false;
                                                    },
                                                    submitCancel() {
                                                        $wire.cancelBooking(this.cancelId);
                                                        this.cancelOpen = false;
                                                    },
                                                    submitComplete(confirmed = false) {
                                                        $wire.completeBooking(this.completeId, confirmed);
                                                        this.completeOpen = false;
                                                    },
                                                    submitDelete() {
                                                        $wire.deleteBooking(this.delId, this.delVal);
                                                        this.delOpen = false;
                                                        this.delVal = '';
                                                        this.delConfirm = false;
                                                    },
                                                }"
                                                @click.outside="open = false"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-white"
                                                    aria-label="Booking actions"
                                                    @click="open = ! open"
                                                >
                                                    <x-filament::icon icon="heroicon-m-ellipsis-vertical" class="h-4 w-4" />
                                                </button>

                                                <div
                                                    x-cloak
                                                    x-show="open"
                                                    x-transition.origin.top.right
                                                    class="absolute right-0 top-full z-30 mt-1 w-40 overflow-hidden rounded-md border border-gray-300 bg-white py-1 shadow-md dark:border-white/15 dark:bg-gray-900"
                                                >
                                                    <button
                                                        type="button"
                                                        @click="open = false; window.location.assign('{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $row['id']]) }}')"
                                                        class="block w-full whitespace-nowrap px-3 py-1.5 text-left text-[13px] font-medium leading-5 text-gray-800 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5"
                                                    >
                                                        View
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="open = false; window.location.assign('{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('edit', ['record' => $row['id']]) }}')"
                                                        class="block w-full whitespace-nowrap px-3 py-1.5 text-left text-[13px] font-medium leading-5 text-gray-800 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5"
                                                    >
                                                        Edit
                                                    </button>

                                                    @if (($row['can_pay_balance'] ?? false) === true)
                                                        <button
                                                            type="button"
                                                            @click="open = false; payOpen = true"
                                                            class="block w-full whitespace-nowrap px-3 py-1.5 text-left text-[13px] font-medium leading-5 text-sky-700 hover:bg-sky-50 dark:text-sky-300 dark:hover:bg-sky-500/10"
                                                        >
                                                            {{ __('Settle remaining balance') }}
                                                        </button>
                                                    @endif

                                                    @if (($row['can_mark_refund_completed'] ?? false) === true)
                                                        <button
                                                            type="button"
                                                            wire:click="markRefundCompleted({{ $row['id'] }})"
                                                            @click="open = false"
                                                            class="block w-full whitespace-nowrap px-3 py-1.5 text-left text-[13px] font-medium leading-5 text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                                                        >
                                                            {{ __('Mark refund completed') }}
                                                        </button>
                                                    @endif

                                                    @if (($row['payment_status'] ?? null) === Booking::PAYMENT_STATUS_PAID && (($row['can_check_in'] ?? false) === true))
                                                        <button
                                                            type="button"
                                                            wire:click="checkInBooking({{ $row['id'] }})"
                                                            @click="open = false"
                                                            class="block w-full whitespace-nowrap px-3 py-1.5 text-left text-[13px] font-medium leading-5 text-amber-700 hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-amber-500/10"
                                                        >
                                                            {{ __('Check in guest') }}
                                                        </button>
                                                    @endif

                                                    @if (($row['can_complete'] ?? false) === true)
                                                        <button
                                                            type="button"
                                                            @click="open = false; completeOpen = true"
                                                            class="block w-full whitespace-nowrap px-3 py-1.5 text-left text-[13px] font-medium leading-5 text-gray-800 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5"
                                                        >
                                                            {{ $row['complete_label'] ?? __('Checkout') }}
                                                        </button>
                                                    @endif

                                                    @if (! in_array(($row['booking_status'] ?? null), [Booking::BOOKING_STATUS_CANCELLED, Booking::BOOKING_STATUS_COMPLETED], true))
                                                        <button
                                                            type="button"
                                                            @click="open = false; cancelOpen = true"
                                                            class="block w-full whitespace-nowrap px-3 py-1.5 text-left text-[13px] font-medium leading-5 text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10"
                                                        >
                                                            Cancel booking
                                                        </button>
                                                    @endif

                                                    <button
                                                        type="button"
                                                        @click="open = false; delOpen = true; delVal = ''; delConfirm = false"
                                                        class="block w-full whitespace-nowrap px-3 py-1.5 text-left text-[13px] font-medium leading-5 text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10"
                                                    >
                                                        Delete
                                                    </button>
                                                </div>

                                                <template x-teleport="body">
                                                    <div
                                                        x-show="payOpen"
                                                        x-cloak
                                                        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
                                                        style="display: none;"
                                                    >
                                                        <div class="absolute inset-0 bg-black/50" @click="payOpen = false"></div>
                                                        <div
                                                            class="relative z-10 w-full max-w-md rounded-xl border border-gray-200 bg-white p-5 shadow-xl dark:border-white/10 dark:bg-gray-900"
                                                            @click.stop
                                                        >
                                                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                                                {{ __('Settle remaining balance') }}
                                                            </h3>
                                                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                                                {{ __('This records one payment for the full remaining balance and sets payment to Paid. For partial cash, use the Payments tab on the booking.') }}
                                                            </p>
                                                            <div class="mt-4 flex justify-end gap-2">
                                                                <button
                                                                    type="button"
                                                                    class="rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
                                                                    @click="payOpen = false"
                                                                >
                                                                    {{ __('No, go back') }}
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    class="rounded-lg bg-sky-600 px-3 py-2 text-sm font-medium text-white hover:bg-sky-700"
                                                                    @click="submitPayBalance()"
                                                                >
                                                                    {{ __('Yes, mark as paid') }}
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>

                                                <template x-teleport="body">
                                                    <div
                                                        x-show="completeOpen"
                                                        x-cloak
                                                        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
                                                        style="display: none;"
                                                    >
                                                        <div class="absolute inset-0 bg-black/50" @click="completeOpen = false"></div>
                                                        <div
                                                            class="relative z-10 w-full max-w-xl rounded-xl border border-gray-200 bg-white p-5 shadow-xl dark:border-white/10 dark:bg-gray-900"
                                                            @click.stop
                                                        >
                                                            @php
                                                                $checklistSummary = $row['checklist_summary'] ?? [
                                                                    'total_items' => 0,
                                                                    'answered_items' => 0,
                                                                    'incomplete_items' => 0,
                                                                    'broken_items' => 0,
                                                                    'missing_items' => 0,
                                                                    'should_warn_on_complete' => false,
                                                                ];
                                                            @endphp
                                                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                                                {{ $row['complete_label'] ?? __('Checkout') }}
                                                            </h3>
                                                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                                                {{ __('Review checklist progress before checkout completion.') }}
                                                            </p>
                                                            <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                                                                <div class="flex items-center justify-between">
                                                                    <span>{{ __('Checklist progress') }}</span>
                                                                    <span class="font-semibold tabular-nums">
                                                                        {{ (int) ($checklistSummary['answered_items'] ?? 0) }}/{{ (int) ($checklistSummary['total_items'] ?? 0) }}
                                                                    </span>
                                                                </div>
                                                                <div class="mt-2 grid grid-cols-3 gap-2 text-[11px]">
                                                                    <div>{{ __('Incomplete') }}: <span class="font-semibold tabular-nums">{{ (int) ($checklistSummary['incomplete_items'] ?? 0) }}</span></div>
                                                                    <div>{{ __('Broken') }}: <span class="font-semibold tabular-nums">{{ (int) ($checklistSummary['broken_items'] ?? 0) }}</span></div>
                                                                    <div>{{ __('Missing') }}: <span class="font-semibold tabular-nums">{{ (int) ($checklistSummary['missing_items'] ?? 0) }}</span></div>
                                                                </div>
                                                            </div>
                                                            @if (($checklistSummary['should_warn_on_complete'] ?? false) === true)
                                                                <p class="mt-3 rounded-lg border border-amber-300/70 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-400/35 dark:bg-amber-500/10 dark:text-amber-100">
                                                                    {{ __('Checklist has incomplete item(s). You can still complete this booking after confirmation.') }}
                                                                </p>
                                                            @endif
                                                            <div class="mt-4 flex justify-end gap-2">
                                                                <button
                                                                    type="button"
                                                                    class="rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
                                                                    @click="completeOpen = false"
                                                                >
                                                                    {{ __('Go back') }}
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                                                                    @click="submitComplete({{ (($checklistSummary['should_warn_on_complete'] ?? false) === true) ? 'true' : 'false' }})"
                                                                >
                                                                    {{ (($checklistSummary['should_warn_on_complete'] ?? false) === true) ? __('Complete anyway') : ($row['complete_label'] ?? __('Checkout')) }}
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>

                                                <template x-teleport="body">
                                                    <div
                                                        x-show="cancelOpen"
                                                        x-cloak
                                                        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
                                                        style="display: none;"
                                                    >
                                                        <div class="absolute inset-0 bg-black/50" @click="cancelOpen = false"></div>
                                                        <div
                                                            class="relative z-10 w-full max-w-md rounded-xl border border-gray-200 bg-white p-5 shadow-xl dark:border-white/10 dark:bg-gray-900"
                                                            @click.stop
                                                        >
                                                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                                                {{ __('Cancel booking') }}
                                                            </h3>
                                                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                                                {{ __('Are you sure you want to cancel this booking? This will update its status to Cancelled.') }}
                                                            </p>
                                                            <div class="mt-4 flex justify-end gap-2">
                                                                <button
                                                                    type="button"
                                                                    class="rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
                                                                    @click="cancelOpen = false"
                                                                >
                                                                    {{ __('No, keep booking') }}
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-700"
                                                                    @click="submitCancel()"
                                                                >
                                                                    {{ __('Yes, cancel booking') }}
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>

                                                <template x-teleport="body">
                                                    <div
                                                        x-show="delOpen"
                                                        x-cloak
                                                        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
                                                        style="display: none;"
                                                    >
                                                        <div class="absolute inset-0 bg-black/50" @click="delOpen = false"></div>
                                                        <div
                                                            class="relative z-10 w-full max-w-md rounded-xl border border-gray-200 bg-white p-4 shadow-xl dark:border-white/10 dark:bg-gray-900"
                                                            @click.stop
                                                        >
                                                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                                                {{ __('Delete booking') }}
                                                            </h3>
                                                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Type the reference and confirm to move this booking to Recycle Bin.') }}</p>
                                                            <p class="mt-2 rounded-md bg-gray-100 px-2 py-1 font-mono text-sm font-semibold text-gray-900 dark:bg-white/10 dark:text-white" x-text="delRef"></p>
                                                            <input
                                                                type="text"
                                                                x-model="delVal"
                                                                class="mt-2 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-danger-500 focus:outline-none focus:ring-1 focus:ring-danger-500 dark:border-white/10 dark:bg-gray-950 dark:text-white"
                                                                autocomplete="off"
                                                                :placeholder="delRef"
                                                            />
                                                            <label class="mt-2 flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                                <input
                                                                    type="checkbox"
                                                                    x-model="delConfirm"
                                                                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-danger-600 focus:ring-danger-500 dark:border-white/20 dark:bg-gray-950"
                                                                />
                                                                <span>{{ __('I understand this will move the booking to Recycle Bin.') }}</span>
                                                            </label>
                                                            <div class="mt-4 flex justify-end gap-2">
                                                                <button
                                                                    type="button"
                                                                    class="rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
                                                                    @click="delOpen = false; delConfirm = false; delVal = ''"
                                                                >
                                                                    {{ __('Cancel') }}
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-40"
                                                                    :disabled="delVal.trim() !== delRef || !delConfirm"
                                                                    @click="submitDelete()"
                                                                >
                                                                    {{ __('Delete booking') }}
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="space-y-2 px-3 py-3 text-xs text-gray-600 dark:text-gray-400 sm:px-4">
                                        @php
                                            $inlineChecklistSummary = $row['checklist_summary'] ?? null;
                                        @endphp
                                        @if (is_array($inlineChecklistSummary))
                                            <div class="rounded-lg border border-gray-200/80 bg-gray-50/80 px-2.5 py-2 text-[11px] text-gray-700 dark:border-white/10 dark:bg-white/[0.03] dark:text-gray-200">
                                                <div class="flex items-center justify-between">
                                                    <span class="font-medium">{{ __('Checklist') }}</span>
                                                    <span class="tabular-nums">{{ (int) ($inlineChecklistSummary['answered_items'] ?? 0) }}/{{ (int) ($inlineChecklistSummary['total_items'] ?? 0) }}</span>
                                                </div>
                                                <div class="mt-1 text-gray-600 dark:text-gray-400">
                                                    {{ __('Incomplete: :count', ['count' => (int) ($inlineChecklistSummary['incomplete_items'] ?? 0)]) }}
                                                </div>
                                            </div>
                                        @endif
                                        <div class="flex gap-2">
                                            <x-filament::icon
                                                icon="heroicon-m-arrow-right-circle"
                                                class="mt-0.5 h-4 w-4 shrink-0 text-gray-400"
                                            />
                                            <div class="leading-snug">
                                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('Stay') }}</span>
                                                <span class="mt-0.5 block tabular-nums text-gray-600 dark:text-gray-400">
                                                    {{ $row['check_in'] }}
                                                    <span class="text-gray-400 dark:text-gray-500">→</span>
                                                    {{ $row['check_out'] }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <x-filament::icon
                                                icon="heroicon-m-home-modern"
                                                class="mt-0.5 h-4 w-4 shrink-0 text-gray-400"
                                            />
                                            <div>
                                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('Rooms') }}</span>
                                                <span class="mt-0.5 block text-gray-600 dark:text-gray-400">{{ $row['rooms'] }}</span>
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <x-filament::icon
                                                icon="heroicon-m-building-office-2"
                                                class="mt-0.5 h-4 w-4 shrink-0 text-gray-400"
                                            />
                                            <div>
                                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('Venues') }}</span>
                                                <span class="mt-0.5 block text-gray-600 dark:text-gray-400">{{ $row['venues'] }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div
                    class="border-t border-gray-200/80 bg-gray-50/80 px-5 py-3 dark:border-white/10 dark:bg-white/[0.03]"
                >
                    <p class="text-center text-[11px] text-gray-500 dark:text-gray-500">
                        {{ __('Tip: open a reference to view payment, rooms, and full itinerary in the booking record.') }}
                    </p>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
