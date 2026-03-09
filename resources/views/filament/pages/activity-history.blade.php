<x-filament-panels::page>
    <div wire:poll.5s class="space-y-6">
        @forelse ($this->timelineGroups as $groupLabel => $logs)
            <section class="space-y-3">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ $groupLabel }}
                    </h3>

                    @if ($loop->first)
                        <div class="flex w-full flex-col gap-2 md:w-auto md:flex-row md:items-center md:justify-end">
                            @if ($this->dateMode === 'custom_date')
                                <div class="w-full md:w-44 md:shrink-0">
                                    <label for="activity-date-filter" class="sr-only">Filter by specific date</label>
                                    <button
                                        type="button"
                                        onclick="document.getElementById('activity-date-filter')?.showPicker?.()"
                                        class="fi-input flex w-full items-center justify-between rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-300 transition focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/20"
                                    >
                                        <span>
                                            {{ filled($this->selectedDate) ? \Illuminate\Support\Carbon::parse($this->selectedDate)->format('M j, Y') : 'Select date' }}
                                        </span>
                                        <x-filament::icon icon="heroicon-o-calendar-days" class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                    </button>
                                    <input
                                        id="activity-date-filter"
                                        type="date"
                                        wire:model.live="selectedDate"
                                        class="sr-only"
                                    />
                                </div>
                            @endif

                            <div class="w-full md:w-auto md:shrink-0">
                                <div class="inline-flex w-full overflow-hidden rounded-lg border border-gray-300 bg-white dark:border-white/20 dark:bg-white/5">
                                    <button
                                        type="button"
                                        wire:click="$set('dateMode', 'all_time')"
                                        @class([
                                            'px-3 py-2 text-sm font-medium transition',
                                            'bg-primary-600 text-white' => $this->dateMode === 'all_time',
                                            'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/10' => $this->dateMode !== 'all_time',
                                        ])
                                    >
                                        All time
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="$set('dateMode', 'custom_date')"
                                        @class([
                                            'border-l border-gray-300 px-3 py-2 text-sm font-medium transition dark:border-white/20',
                                            'bg-primary-600 text-white' => $this->dateMode === 'custom_date',
                                            'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/10' => $this->dateMode !== 'custom_date',
                                        ])
                                    >
                                        Custom date
                                    </button>
                                </div>
                            </div>

                            <div class="w-full md:w-auto md:min-w-[20rem]">
                                <label for="activity-search" class="sr-only">Search activity</label>
                                <input
                                    id="activity-search"
                                    type="search"
                                    wire:model.live.debounce.400ms="search"
                                    placeholder="Search user, event, message, IP, device..."
                                    class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-300 transition focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/20"
                                />
                            </div>
                        </div>
                    @endif
                </div>

                <div class="relative pl-7">
                    <div class="absolute left-[1.1rem] top-0 bottom-0 w-px bg-gray-200 dark:bg-white/10"></div>

                    <div class="space-y-4">
                        @foreach ($logs as $log)
                            @php
                                $icon = $this->getLogIcon((string) $log->category, (string) $log->event);
                                $iconColor = $this->getLogIconColor((string) $log->category, (string) $log->event);
                                $actor = $log->user?->name ?? 'System';
                            @endphp

                            <article class="relative rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-white/10 dark:bg-white/5">
                                <span class="absolute -left-[1.85rem] top-5 inline-flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                                    <x-filament::icon
                                        :icon="$icon"
                                        class="h-4 w-4 {{ $iconColor }}"
                                    />
                                </span>

                                <p class="text-sm text-gray-900 dark:text-gray-100">
                                    <span class="font-semibold">{{ $actor }}</span>
                                    <span>{{ $this->getDisplayMessage($log) }}</span>
                                </p>

                                <div class="mt-2 flex items-center justify-between gap-3 text-xs text-gray-500 dark:text-gray-400">
                                    <div class="flex items-center gap-2">
                                        <span>{{ $log->created_at?->format('h:i A') }}</span>
                                        <span aria-hidden="true">•</span>
                                        <span>{{ $this->getCategoryLabel($log) }}</span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                            {{ $this->getDeviceName($log) }}
                                        </span>
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                            {{ $this->getBrowserName($log) }}
                                        </span>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @empty
            <div class="space-y-3">
                <div class="flex justify-end">
                    <div class="flex w-full flex-col gap-2 md:w-auto md:max-w-none md:flex-row md:items-center md:justify-end">
                        @if ($this->dateMode === 'custom_date')
                            <div class="w-full md:w-44 md:shrink-0">
                                <label for="activity-date-filter-empty" class="sr-only">Filter by specific date</label>
                                <button
                                    type="button"
                                    onclick="document.getElementById('activity-date-filter-empty')?.showPicker?.()"
                                    class="fi-input flex w-full items-center justify-between rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-300 transition focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/20"
                                >
                                    <span>
                                        {{ filled($this->selectedDate) ? \Illuminate\Support\Carbon::parse($this->selectedDate)->format('M j, Y') : 'Select date' }}
                                    </span>
                                    <x-filament::icon icon="heroicon-o-calendar-days" class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                </button>
                                <input
                                    id="activity-date-filter-empty"
                                    type="date"
                                    wire:model.live="selectedDate"
                                    class="sr-only"
                                />
                            </div>
                        @endif

                        <div class="w-full md:w-auto md:shrink-0">
                            <div class="inline-flex w-full overflow-hidden rounded-lg border border-gray-300 bg-white dark:border-white/20 dark:bg-white/5">
                                <button
                                    type="button"
                                    wire:click="$set('dateMode', 'all_time')"
                                    @class([
                                        'px-3 py-2 text-sm font-medium transition',
                                        'bg-primary-600 text-white' => $this->dateMode === 'all_time',
                                        'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/10' => $this->dateMode !== 'all_time',
                                    ])
                                >
                                    All time
                                </button>
                                <button
                                    type="button"
                                    wire:click="$set('dateMode', 'custom_date')"
                                    @class([
                                        'border-l border-gray-300 px-3 py-2 text-sm font-medium transition dark:border-white/20',
                                        'bg-primary-600 text-white' => $this->dateMode === 'custom_date',
                                        'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/10' => $this->dateMode !== 'custom_date',
                                    ])
                                >
                                    Custom date
                                </button>
                            </div>
                        </div>

                        

                        <div class="w-full md:w-auto md:min-w-[20rem] md:max-w-md">
                            <label for="activity-search" class="sr-only">Search activity</label>
                            <input
                                id="activity-search"
                                type="search"
                                wire:model.live.debounce.400ms="search"
                                placeholder="Search user, event, message, IP, device..."
                                class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-300 transition focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/20"
                            />
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 dark:border-white/20 dark:bg-white/5 dark:text-gray-400">
                @if (filled($search))
                    No activity matched "{{ $search }}".
                @else
                    No activity yet.
                @endif
                </div>
            </div>
        @endforelse

        @if ($this->hasMoreLogs)
            <div class="flex justify-center pt-2">
                <x-filament::button
                    color="gray"
                    icon="heroicon-o-chevron-down"
                    wire:click="seeMore"
                >
                    See more
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
