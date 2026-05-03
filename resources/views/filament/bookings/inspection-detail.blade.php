@php
    /** @var \App\Models\BookingInspection|null $inspection */
    $inspection?->loadMissing(['items.photos', 'items.inventoryItem.room', 'inspectedBy', 'booking']);
@endphp

@if (! $inspection)
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No inspection on file.') }}</p>
@else
    <div class="space-y-4 text-sm">
        <div class="flex flex-wrap gap-3">
            <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-800 dark:bg-white/10 dark:text-gray-100">
                {{ __('Result') }}:
                @if ($inspection->status === \App\Models\BookingInspection::STATUS_CLEAR)
                    <span class="ml-1 text-emerald-700 dark:text-emerald-300">{{ __('Clear') }}</span>
                @else
                    <span class="ml-1 text-amber-700 dark:text-amber-300">{{ __('With issues') }}</span>
                @endif
            </span>
            @if ($inspection->inspectedBy)
                <span class="text-gray-600 dark:text-gray-300">
                    {{ __('Inspected by') }} <strong>{{ $inspection->inspectedBy->name }}</strong>
                    · {{ $inspection->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                </span>
            @endif
        </div>

        @if (filled($inspection->notes))
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Notes') }}</div>
                <p class="mt-1 whitespace-pre-wrap text-gray-800 dark:text-gray-100">{{ $inspection->notes }}</p>
            </div>
        @endif

        <div class="space-y-3">
            @foreach ($inspection->items as $item)
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">
                                {{ $item->inventoryItem?->item_name ?? '—' }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $item->inventoryItem?->room?->name ? __('Room: :name', ['name' => $item->inventoryItem->room->name]) : '' }}
                                @if ($item->inventoryItem)
                                    · {{ __('Qty') }} {{ (int) $item->inventoryItem->quantity }}
                                @endif
                            </div>
                        </div>
                        <span @class([
                            'inline-flex rounded px-2 py-0.5 text-xs font-semibold',
                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200' => $item->status === \App\Models\InspectionItem::STATUS_OK,
                            'bg-amber-100 text-amber-900 dark:bg-amber-500/20 dark:text-amber-100' => $item->status === \App\Models\InspectionItem::STATUS_DAMAGED,
                            'bg-rose-100 text-rose-900 dark:bg-rose-500/20 dark:text-rose-100' => $item->status === \App\Models\InspectionItem::STATUS_MISSING,
                        ])>
                            {{ strtoupper($item->status) }}
                        </span>
                    </div>
                    @if (filled($item->remarks))
                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">{{ $item->remarks }}</p>
                    @endif
                    @if ($item->photos->isNotEmpty())
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($item->photos as $photo)
                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($photo->file_path) }}" target="_blank" rel="noopener noreferrer" class="block">
                                    <img
                                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($photo->file_path) }}"
                                        alt=""
                                        class="h-24 w-24 rounded-md object-cover ring-1 ring-gray-200 dark:ring-white/10"
                                    />
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @isset($detailUrl)
        <p class="mt-4">
            <a href="{{ $detailUrl }}" class="text-primary-600 underline hover:text-primary-700 dark:text-primary-400">
                {{ __('Open full inspection record') }}
            </a>
        </p>
    @endisset
@endif
