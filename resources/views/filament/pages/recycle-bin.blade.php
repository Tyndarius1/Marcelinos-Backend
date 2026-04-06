<x-filament-panels::page>
    @php
        $style = request()->query('style', 'luxury');
        if (! in_array($style, ['luxury', 'corporate', 'saas'], true)) {
            $style = 'luxury';
        }

        $themes = [
            'luxury' => [
                'hero' => 'relative overflow-hidden rounded-2xl border border-gray-200/80 bg-white p-7 shadow-sm dark:border-white/10 dark:bg-gray-900',
                'accent' => 'from-primary-500/80 via-sky-500/70 to-violet-500/70',
                'badge' => 'inline-flex items-center gap-2 rounded-lg border border-primary-200 bg-primary-50/60 px-3 py-1.5 text-sm font-medium text-primary-800 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-300',
                'badgeIcon' => 'h-4 w-4 text-primary-500 dark:text-primary-300',
                'badgeText' => 'text-primary-700/80 dark:text-primary-300/80',
                'stat' => 'rounded-xl border border-gray-200/80 bg-gradient-to-b from-white to-gray-50/60 px-4 py-3 shadow-sm dark:border-white/10 dark:from-white/[0.04] dark:to-white/[0.02]',
                'table' => 'overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900',
                'thead' => 'bg-gray-50/90 dark:bg-white/5',
                'row' => 'group transition hover:bg-primary-50/40 dark:hover:bg-primary-500/[0.06]',
                'typeBadge' => 'inline-flex items-center rounded-md border border-gray-200 bg-white px-2 py-0.5 text-[11px] font-medium text-gray-600 group-hover:border-primary-200 group-hover:text-primary-700 dark:border-white/10 dark:bg-white/[0.03] dark:text-gray-300 dark:group-hover:border-primary-400/40 dark:group-hover:text-primary-300',
                'actions' => 'inline-flex flex-wrap items-center justify-end gap-2 rounded-lg border border-transparent bg-transparent p-1 group-hover:border-primary-100 group-hover:bg-primary-50/50 dark:group-hover:border-primary-400/20 dark:group-hover:bg-primary-500/[0.08]',
            ],
            'corporate' => [
                'hero' => 'relative overflow-hidden rounded-2xl border border-gray-300/80 bg-white p-7 shadow-sm dark:border-white/10 dark:bg-gray-900',
                'accent' => 'from-slate-500/80 via-slate-400/60 to-slate-600/70',
                'badge' => 'inline-flex items-center gap-2 rounded-md border border-gray-300 bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-800 dark:border-white/10 dark:bg-white/5 dark:text-gray-200',
                'badgeIcon' => 'h-4 w-4 text-gray-500 dark:text-gray-400',
                'badgeText' => 'text-gray-600 dark:text-gray-400',
                'stat' => 'rounded-lg border border-gray-300/80 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/[0.03]',
                'table' => 'overflow-hidden rounded-xl border border-gray-300 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900',
                'thead' => 'bg-gray-100 dark:bg-white/5',
                'row' => 'group transition hover:bg-gray-50 dark:hover:bg-white/[0.04]',
                'typeBadge' => 'inline-flex items-center rounded-md border border-gray-300 bg-gray-50 px-2 py-0.5 text-[11px] font-medium text-gray-700 dark:border-white/10 dark:bg-white/[0.03] dark:text-gray-300',
                'actions' => 'inline-flex flex-wrap items-center justify-end gap-2',
            ],
            'saas' => [
                'hero' => 'relative overflow-hidden rounded-2xl border border-indigo-200/80 bg-gradient-to-br from-white to-indigo-50/50 p-7 shadow-sm dark:border-indigo-300/30 dark:from-slate-900 dark:to-indigo-500/12',
                'accent' => 'from-indigo-500/80 via-cyan-500/70 to-fuchsia-500/70',
                'badge' => 'inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50/90 px-3 py-1.5 text-sm font-medium text-indigo-800 dark:border-indigo-300/35 dark:bg-indigo-400/15 dark:text-indigo-100',
                'badgeIcon' => 'h-4 w-4 text-indigo-500 dark:text-indigo-200',
                'badgeText' => 'text-indigo-700/80 dark:text-indigo-200/80',
                'stat' => 'rounded-xl border border-indigo-200/80 bg-white/95 px-4 py-3 shadow-sm dark:border-indigo-300/30 dark:bg-slate-900/65',
                'table' => 'overflow-hidden rounded-xl border border-indigo-200/80 bg-white shadow-sm dark:border-indigo-300/30 dark:bg-slate-900/70',
                'thead' => 'bg-indigo-50/80 dark:bg-indigo-400/[0.10]',
                'row' => 'group transition hover:bg-indigo-50/70 dark:hover:bg-indigo-400/[0.12]',
                'typeBadge' => 'inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700 dark:border-indigo-300/35 dark:bg-indigo-400/[0.20] dark:text-indigo-100',
                'actions' => 'inline-flex flex-wrap items-center justify-end gap-2 rounded-lg border border-transparent p-1 group-hover:border-indigo-200/80 group-hover:bg-indigo-50/80 dark:group-hover:border-indigo-300/35 dark:group-hover:bg-indigo-400/[0.14]',
            ],
        ];

        $theme = $themes[$style];
    @endphp

    <div class="fi-recycle-bin space-y-6">
        {{-- Hero / empty state --}}
        @if ($this->totalTrashed === 0)
            <div
                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-gray-50/80 px-6 py-16 text-center dark:border-white/20 dark:bg-white/5"
            >
                <div
                    class="mb-4 flex h-20 w-20 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                >
                    <x-filament::icon
                        icon="heroicon-o-trash"
                        class="h-10 w-10 text-gray-400 dark:text-gray-500"
                    />
                </div>
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ __('Recycle Bin is empty') }}
                </h2>
                <p class="mt-2 max-w-md text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Nothing you delete from the admin panel is in the bin right now. When you remove items, they appear here so you can restore them or delete them forever.') }}
                </p>
            </div>
        @else
            <div class="{{ $theme['hero'] }}">
                <div class="pointer-events-none absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r {{ $theme['accent'] }}"></div>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold tracking-tight text-gray-950 dark:text-white">
                            {{ __('Recycle Bin') }}
                        </h2>
                        <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400">
                            {{ __('Restore items or delete them permanently. Newest deletions appear first.') }}
                        </p>
                    </div>
                    <div class="{{ $theme['badge'] }}">
                        <x-filament::icon icon="heroicon-o-archive-box-x-mark" class="{{ $theme['badgeIcon'] }}" />
                        <span class="tabular-nums">{{ number_format($this->totalTrashed) }}</span>
                        <span class="{{ $theme['badgeText'] }}">{{ __('total') }}</span>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="{{ $theme['stat'] }}">
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="heroicon-o-circle-stack" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Main records') }}</p>
                        </div>
                        <p class="mt-1.5 text-xl font-semibold tabular-nums text-gray-950 dark:text-white">
                            {{ number_format(\App\Filament\Pages\RecycleBin::primaryTrashedTotal()) }}
                        </p>
                    </div>
                    <div class="{{ $theme['stat'] }}">
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="heroicon-o-link" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Related records') }}</p>
                        </div>
                        <p class="mt-1.5 text-xl font-semibold tabular-nums text-gray-950 dark:text-white">
                            {{ number_format($this->totalTrashed - \App\Filament\Pages\RecycleBin::primaryTrashedTotal()) }}
                        </p>
                    </div>
                    <div class="{{ $theme['stat'] }}">
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="heroicon-o-list-bullet" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Shown on this page') }}</p>
                        </div>
                        <p class="mt-1.5 text-xl font-semibold tabular-nums text-gray-950 dark:text-white">
                            {{ number_format($this->trashedPaginator->count()) }}
                        </p>
                    </div>
                </div>
            </div>

            <x-filament::section>
                <x-slot name="heading">{{ __('Deleted items') }}</x-slot>
                <x-slot name="description">{{ __('Review, restore, or permanently remove records.') }}</x-slot>

                <div class="{{ $theme['table'] }}">
                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 text-start text-sm dark:divide-white/10">
                            <thead class="{{ $theme['thead'] }}">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                                        {{ __('Name') }}
                                    </th>
                                    <th scope="col" class="hidden px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-600 sm:table-cell dark:text-gray-300">
                                        {{ __('Original location') }}
                                    </th>
                                    <th scope="col" class="hidden px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-600 md:table-cell dark:text-gray-300">
                                        {{ __('Date deleted') }}
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-end text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                                        {{ __('Actions') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                @foreach ($this->trashedPaginator as $row)
                                    <tr wire:key="trash-{{ $row['type'] }}-{{ $row['id'] }}" class="{{ $theme['row'] }}">
                                        <td class="px-4 py-3 align-top">
                                            <div class="mb-1 flex items-center gap-2">
                                                <span class="{{ $theme['typeBadge'] }}">
                                                    {{ $row['type_label'] }}
                                                </span>
                                            </div>
                                            <div class="font-medium text-gray-950 dark:text-white">
                                                {{ $row['name'] }}
                                            </div>
                                            <div class="mt-1 text-xs text-gray-500 sm:hidden dark:text-gray-400">
                                                {{ $row['location'] }}
                                                @if ($row['deleted_at'])
                                                    · {{ $row['deleted_at']->diffForHumans() }}
                                                @endif
                                            </div>
                                        </td>
                                        <td class="hidden px-4 py-3 align-middle text-gray-600 sm:table-cell dark:text-gray-400">
                                            {{ $row['location'] }}
                                        </td>
                                        <td class="hidden px-4 py-3 align-middle tabular-nums text-gray-600 md:table-cell dark:text-gray-400">
                                            @if ($row['deleted_at'])
                                                <div class="space-y-0.5">
                                                    <span title="{{ $row['deleted_at']->toIso8601String() }}">
                                                        {{ $row['deleted_at']->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                                    </span>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ $row['deleted_at']->diffForHumans() }}
                                                    </div>
                                                </div>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 align-middle">
                                            <div class="{{ $theme['actions'] }}">
                                                @if (! empty($row['edit_url']))
                                                    <x-filament::button
                                                        color="gray"
                                                        size="sm"
                                                        outlined
                                                        class="rounded-md min-w-[4.25rem]"
                                                        tag="a"
                                                        :href="$row['edit_url']"
                                                    >
                                                        {{ __('Open') }}
                                                    </x-filament::button>
                                                @endif
                                                <x-filament::button
                                                    color="success"
                                                    size="sm"
                                                    class="rounded-md min-w-[4.25rem]"
                                                    wire:click="restoreItem('{{ $row['type'] }}', '{{ (string) $row['id'] }}')"
                                                >
                                                    {{ __('Restore') }}
                                                </x-filament::button>
                                                <x-filament::button
                                                    color="danger"
                                                    size="sm"
                                                    outlined
                                                    class="rounded-md min-w-[7.5rem]"
                                                    wire:click="openPurgeModal('{{ $row['type'] }}', '{{ (string) $row['id'] }}')"
                                                >
                                                    {{ __('Delete forever') }}
                                                </x-filament::button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($this->trashedPaginator->hasPages())
                        <div class="border-t border-gray-200 px-4 py-3 dark:border-white/10">
                            {{ $this->trashedPaginator->links() }}
                        </div>
                    @endif
                </div>
            </x-filament::section>
        @endif

        <x-filament::section :collapsible="true" :collapsed="true">
            <x-slot name="heading">
                {{ __('Browse by type') }}
            </x-slot>
            <x-slot name="description">
                {{ __('Jump to a list with the “Trashed” filter set to only deleted items.') }}
            </x-slot>
            <ul class="divide-y divide-gray-200 rounded-xl border border-gray-200 bg-white shadow-sm dark:divide-white/10 dark:border-white/10 dark:bg-gray-900">
                @foreach ($this->links as $link)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 transition hover:bg-gray-50 dark:hover:bg-white/[0.03]">
                        <a
                            href="{{ $link['url'] }}"
                            class="font-medium text-primary-600 hover:text-primary-500 hover:underline dark:text-primary-400"
                        >
                            {{ $link['label'] }}
                        </a>
                        <span class="inline-flex items-center rounded-md border border-gray-200 px-2 py-0.5 text-xs tabular-nums text-gray-600 dark:border-white/10 dark:text-gray-300">
                            {{ trans_choice(':count in bin|:count in bin', $link['count'], ['count' => $link['count']]) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    </div>

    {{-- Permanent delete modal (Windows-style confirm) --}}
    @if (filled($this->purgeType) && filled($this->purgeId))
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 px-4 py-6 dark:bg-gray-950/70"
            wire:click.self="closePurgeModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="recycle-purge-title"
        >
            <div
                class="fi-modal-window w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                @click.stop
            >
                <div class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
                    <h2 id="recycle-purge-title" class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ __('Delete permanently?') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ __('This cannot be undone. You are about to remove:') }}
                        <span class="font-medium text-gray-950 dark:text-white">{{ $this->purgeItemName }}</span>
                    </p>
                </div>
                <div class="space-y-4 px-6 py-4">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model="purgeTypedConfirm"
                            :placeholder="__('Type DELETE in all capitals')"
                            autocomplete="off"
                            class="font-mono"
                        />
                    </x-filament::input.wrapper>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('Same safety as elsewhere: type the word DELETE to confirm permanent removal.') }}
                    </p>
                </div>
                <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-white/10 dark:bg-white/5">
                    <x-filament::button color="gray" wire:click="closePurgeModal">
                        {{ __('Cancel') }}
                    </x-filament::button>
                    <x-filament::button color="danger" wire:click="confirmPermanentDelete">
                        {{ __('Delete permanently') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
