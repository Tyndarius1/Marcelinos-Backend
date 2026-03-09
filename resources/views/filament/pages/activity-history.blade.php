<x-filament-panels::page>
    <div wire:poll.5s class="space-y-6">
        @forelse ($this->timelineGroups as $groupLabel => $logs)
            <section class="space-y-3">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ $groupLabel }}
                </h3>

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

                                <div class="mt-2 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    <span>{{ $log->created_at?->format('h:i A') }}</span>
                                    <span aria-hidden="true">•</span>
                                    <span class="capitalize">{{ $log->category }}</span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 dark:border-white/20 dark:bg-white/5 dark:text-gray-400">
                No activity yet.
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
