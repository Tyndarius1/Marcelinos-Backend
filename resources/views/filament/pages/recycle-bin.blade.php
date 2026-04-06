<x-filament-panels::page>
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
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Recycle Bin') }}
                </x-slot>
                <x-slot name="description">
                    {{ __('Like the Windows Recycle Bin: restore puts the item back, or remove it permanently (you must type DELETE). Newest deletions are listed first.') }}
                </x-slot>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 text-start text-sm dark:divide-white/10">
                            <thead class="bg-gray-50 dark:bg-white/5">
                                <tr>
                                    <th scope="col" class="px-4 py-3 font-semibold text-gray-950 dark:text-white">
                                        {{ __('Name') }}
                                    </th>
                                    <th scope="col" class="hidden px-4 py-3 font-semibold text-gray-950 sm:table-cell dark:text-white">
                                        {{ __('Original location') }}
                                    </th>
                                    <th scope="col" class="hidden px-4 py-3 font-semibold text-gray-950 md:table-cell dark:text-white">
                                        {{ __('Date deleted') }}
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-end font-semibold text-gray-950 dark:text-white">
                                        {{ __('Actions') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                @foreach ($this->trashedPaginator as $row)
                                    <tr
                                        wire:key="trash-{{ $row['type'] }}-{{ $row['id'] }}"
                                        class="transition hover:bg-gray-50/80 dark:hover:bg-white/5"
                                    >
                                        <td class="px-4 py-3 align-top">
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
                                                <span title="{{ $row['deleted_at']->toIso8601String() }}">
                                                    {{ $row['deleted_at']->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                                </span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 align-middle">
                                            <div class="flex flex-wrap items-center justify-end gap-2">
                                                @if (! empty($row['edit_url']))
                                                    <x-filament::button
                                                        color="gray"
                                                        size="sm"
                                                        outlined
                                                        tag="a"
                                                        :href="$row['edit_url']"
                                                    >
                                                        {{ __('Open') }}
                                                    </x-filament::button>
                                                @endif
                                                <x-filament::button
                                                    color="success"
                                                    size="sm"
                                                    wire:click="restoreItem('{{ $row['type'] }}', @js($row['id']))"
                                                >
                                                    {{ __('Restore') }}
                                                </x-filament::button>
                                                <x-filament::button
                                                    color="danger"
                                                    size="sm"
                                                    outlined
                                                    wire:click="openPurgeModal('{{ $row['type'] }}', @js($row['id']))"
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
            <ul class="divide-y divide-gray-200 rounded-xl border border-gray-200 dark:divide-white/10 dark:border-white/10">
                @foreach ($this->links as $link)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                        <a
                            href="{{ $link['url'] }}"
                            class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                        >
                            {{ $link['label'] }}
                        </a>
                        <span class="text-sm tabular-nums text-gray-500 dark:text-gray-400">
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
