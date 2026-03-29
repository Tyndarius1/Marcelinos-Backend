@php
    use App\Models\Booking;
    use App\Models\Room;

    $typeOrder = [Room::TYPE_STANDARD, Room::TYPE_FAMILY, Room::TYPE_DELUXE];
    $statusPill = [
        Booking::STATUS_UNPAID => 'bg-violet-100 text-violet-800 ring-1 ring-inset ring-violet-600/15 dark:bg-violet-500/15 dark:text-violet-200 dark:ring-violet-400/25',
        Booking::STATUS_PAID => 'bg-emerald-100 text-emerald-800 ring-1 ring-inset ring-emerald-600/15 dark:bg-emerald-500/15 dark:text-emerald-200 dark:ring-emerald-400/25',
        Booking::STATUS_OCCUPIED => 'bg-amber-100 text-amber-900 ring-1 ring-inset ring-amber-600/15 dark:bg-amber-500/15 dark:text-amber-200 dark:ring-amber-400/25',
        Booking::STATUS_COMPLETED => 'bg-gray-100 text-gray-800 ring-1 ring-inset ring-gray-600/15 dark:bg-white/10 dark:text-gray-200 dark:ring-white/15',
        Booking::STATUS_CANCELLED => 'bg-rose-100 text-rose-800 ring-1 ring-inset ring-rose-600/15 dark:bg-rose-500/15 dark:text-rose-200 dark:ring-rose-400/25',
        Booking::STATUS_RESCHEDULED => 'bg-blue-100 text-blue-800 ring-1 ring-inset ring-blue-600/15 dark:bg-blue-500/15 dark:text-blue-200 dark:ring-blue-400/25',
    ];
@endphp

<x-filament-panels::page>
    <div
        class="mx-auto max-w-6xl space-y-6 px-0 sm:space-y-8 sm:px-0 pb-8 sm:pb-10 transition-opacity duration-200"
        wire:loading.class="pointer-events-none opacity-60"
        wire:target="previousMonth,nextMonth,month,year"
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

            <div class="relative flex flex-col gap-6 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                <div class="min-w-0 space-y-2">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-white shadow-md shadow-primary-900/20 ring-1 ring-white/20 dark:bg-primary-500 dark:ring-white/10"
                        >
                            <x-filament::icon
                                icon="heroicon-o-calendar-days"
                                class="h-6 w-6"
                            />
                        </span>
                        <div>
                            <h1
                                class="text-xl font-semibold tracking-tight text-gray-950 sm:text-2xl dark:text-white"
                            >
                                {{ __('Room calendar') }}
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ __('Overlapping stays by room category (assigned rooms, or guest selection until rooms are assigned). Cancelled reservations are excluded.') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center gap-2 rounded-xl border border-gray-200/90 bg-gray-50/90 px-3 py-2 text-xs text-gray-600 shadow-inner dark:border-white/10 dark:bg-white/5 dark:text-gray-300"
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
                </div>
            </div>
        </div>

        {{-- Calendar card --}}
        <x-filament::section
            icon="heroicon-o-squares-2x2"
            icon-color="primary"
            :heading="$this->currentPeriodLabel()"
            :description="__('Use the controls to change month. Each badge is a room category; the number is how many bookings overlap that day.')"
        >
            <div class="space-y-6">
                {{-- Toolbar: prev/next aligned with select controls (bottom edge) --}}
                <div
                    class="flex flex-wrap items-end gap-x-3 gap-y-3"
                >
                    <div
                        class="inline-flex shrink-0 items-center gap-0.5 self-end rounded-xl border border-gray-200 bg-white px-0.5 py-0.5 shadow-sm dark:border-white/10 dark:bg-gray-950"
                    >
                        <x-filament::icon-button
                            color="gray"
                            icon="heroicon-m-chevron-left"
                            wire:click="previousMonth"
                            wire:loading.attr="disabled"
                            wire:target="previousMonth"
                            :label="__('Previous month')"
                            class="!rounded-lg"
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

                    <div
                        class="grid min-w-0 flex-1 grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-initial sm:gap-3"
                    >
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
                </div>

                {{-- Legend: single horizontal row; scroll on narrow screens --}}
                <div
                    class="legend-strip room-calendar-scroll overflow-x-auto overscroll-x-contain rounded-xl border border-dashed border-gray-200/90 bg-gray-50/50 px-3 py-3 dark:border-white/10 dark:bg-white/[0.03] sm:overflow-visible sm:px-4"
                >
                    <div
                        class="flex w-max flex-nowrap items-center gap-2 sm:w-full sm:min-w-0 sm:flex-wrap sm:items-center sm:gap-x-4 sm:gap-y-2"
                    >
                        <span
                            class="shrink-0 text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400"
                        >
                            {{ __('Legend') }}
                        </span>
                        @foreach ($typeOrder as $type)
                            <x-room-type-badge
                                class="w-[11.5rem] max-w-[calc(100vw-6rem)] shrink-0 sm:w-auto sm:max-w-[14rem] sm:shrink"
                                :type="$type"
                            />
                        @endforeach
                    </div>
                </div>

                {{-- Calendar grid: horizontal scroll on small screens so cells stay readable --}}
                <p
                    class="mb-1 text-center text-[11px] leading-snug text-gray-500 dark:text-gray-400 sm:hidden"
                >
                    {{ __('Swipe sideways to see all days.') }}
                </p>
                <div
                    class="room-calendar-scroll overflow-x-auto overscroll-x-contain pb-1 [-webkit-overflow-scrolling:touch] sm:overflow-x-visible sm:pb-0"
                >
                    <div
                        class="min-w-[34rem] space-y-1.5 sm:min-w-0 sm:w-full sm:space-y-2"
                    >
                        {{-- Weekday strip --}}
                        <div class="grid grid-cols-7 gap-1 sm:gap-2">
                            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow)
                                <div
                                    class="rounded-md bg-gray-100 py-2 text-center text-[10px] font-semibold uppercase tracking-wider text-gray-500 sm:rounded-lg sm:py-2.5 sm:text-[11px] dark:bg-white/5 dark:text-gray-400"
                                >
                                    {{ $dow }}
                                </div>
                            @endforeach
                        </div>

                        {{-- Days --}}
                        <div class="grid grid-cols-7 gap-1 sm:gap-2">
                    @foreach ($this->calendarWeeks as $week)
                        @foreach ($week as $cell)
                            @php
                                $dowIndex = $loop->index;
                                $isWeekend = $dowIndex === 0 || $dowIndex === 6;
                                $isToday =
                                    $cell['inMonth'] && ($cell['dateStr'] ?? null) === now()->toDateString();
                            @endphp
                            <div
                                @class([
                                    'flex min-h-[6.5rem] flex-col rounded-lg border p-1.5 transition-colors sm:min-h-[8.5rem] sm:rounded-xl sm:p-2.5',
                                    'border-transparent bg-gray-100/40 dark:bg-white/[0.02]' => ! $cell['inMonth'],
                                    'border-gray-200/90 bg-white shadow-sm dark:border-white/10 dark:bg-gray-950/40' => $cell['inMonth'] && ! $isWeekend,
                                    'border-gray-200/90 bg-slate-50/90 shadow-sm dark:border-white/10 dark:bg-slate-950/35' => $cell['inMonth'] && $isWeekend,
                                    'ring-2 ring-primary-500/80 ring-offset-2 ring-offset-white dark:ring-primary-400/80 dark:ring-offset-gray-900' => $isToday,
                                ])
                            >
                                @if ($cell['inMonth'])
                                    <div class="mb-1 flex items-start justify-between gap-0.5 sm:mb-1.5 sm:gap-1">
                                        <span
                                            @class([
                                                'flex h-6 min-w-[1.5rem] items-center justify-center rounded-md text-xs font-semibold tabular-nums sm:h-7 sm:min-w-[1.75rem] sm:rounded-lg sm:text-sm',
                                                'bg-primary-600 text-white shadow-sm dark:bg-primary-500' => $isToday,
                                                'text-gray-900 dark:text-gray-100' => ! $isToday,
                                            ])
                                        >
                                            {{ $cell['day'] }}
                                        </span>
                                    </div>
                                    <div class="flex min-h-0 flex-1 flex-col gap-0.5 sm:gap-1">
                                        @foreach ($typeOrder as $type)
                                            @php $cnt = $cell['typeCounts'][$type] ?? 0; @endphp
                                            <button
                                                type="button"
                                                wire:click="openDayType('{{ $cell['dateStr'] }}', '{{ $type }}')"
                                                class="group w-full cursor-pointer appearance-none rounded-lg border-0 bg-transparent p-0 text-left shadow-none transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 dark:focus-visible:ring-offset-gray-900"
                                            >
                                                <x-room-type-badge
                                                    class="w-full"
                                                    :type="$type"
                                                    :muted="$cnt === 0"
                                                    :count="$cnt"
                                                    compact
                                                />
                                            </button>
                                        @endforeach
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
    </div>

    {{-- Detail modal --}}
    @if ($modalDate && $modalType)
        <div
            class="fixed inset-0 z-50 flex items-end justify-center overflow-y-auto bg-gray-950/60 px-0 py-0 backdrop-blur-[2px] sm:items-center sm:px-4 sm:py-10"
            wire:click="closeModal"
            x-data
            x-on:keydown.escape.window="$wire.closeModal()"
            role="presentation"
        >
            <div
                class="relative flex max-h-[min(90vh,720px)] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl border border-gray-200/90 bg-white shadow-2xl ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10 sm:rounded-2xl"
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
                                {{ __('Bookings that include this room type on the selected night.') }}
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

                <div class="flex-1 space-y-5 overflow-y-auto px-5 py-5">
                    @php
                        $count = count($this->modalBookingRows);
                    @endphp

                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <div class="min-w-0 max-w-full sm:max-w-md">
                            <x-room-type-badge
                                class="w-full sm:w-auto sm:min-w-[14rem]"
                                :type="$modalType"
                                :muted="$count === 0"
                            />
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
                                    {{ __('This room type is available for the selected night.') }}
                                </p>
                            </div>
                        </div>
                    @else
                        <ul class="space-y-3">
                            @foreach ($this->modalBookingRows as $row)
                                <li
                                    class="overflow-hidden rounded-xl border border-gray-200/90 bg-white shadow-sm ring-1 ring-gray-950/[0.04] dark:border-white/10 dark:bg-gray-950/50 dark:ring-white/[0.06]"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-white/5"
                                    >
                                        <div class="min-w-0">
                                            <a
                                                href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $row['id']]) }}"
                                                class="font-mono text-sm font-semibold text-primary-600 hover:text-primary-700 hover:underline dark:text-primary-400 dark:hover:text-primary-300"
                                            >
                                                {{ $row['reference_number'] }}
                                            </a>
                                            <p class="mt-0.5 truncate text-sm text-gray-700 dark:text-gray-200">
                                                {{ $row['guest_name'] }}
                                            </p>
                                        </div>
                                        @php
                                            $pill = $statusPill[$row['status']] ?? 'bg-gray-100 text-gray-800 ring-1 ring-inset ring-gray-600/15 dark:bg-white/10 dark:text-gray-200';
                                        @endphp
                                        <span
                                            @class([
                                                'shrink-0 rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                                $pill,
                                            ])
                                        >
                                            {{ Booking::statusOptions()[$row['status']] ?? $row['status'] }}
                                        </span>
                                    </div>
                                    <div class="space-y-2 px-4 py-3 text-xs text-gray-600 dark:text-gray-400">
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
