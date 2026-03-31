@php
    use App\Models\Room;

    $type = $getState();
    $compact = true;
    $muted = false;
    $count = null;
    $hideLabelOnMobile = false;

    $labels = [
        Room::TYPE_STANDARD => 'Standard',
        Room::TYPE_FAMILY => 'Family',
        Room::TYPE_DELUXE => 'Deluxe',
    ];
    $label = strtoupper($labels[$type] ?? (string) $type);

    $outer = match ($type) {
        Room::TYPE_STANDARD => 'rounded-lg bg-[#38523b] shadow-md shadow-black/30 ring-1 ring-white/10',
        Room::TYPE_FAMILY => 'rounded-lg bg-[#3c513d] shadow-md shadow-black/30 ring-1 ring-white/10',
        Room::TYPE_DELUXE => 'rounded-full bg-gradient-to-r from-[#9c7a42] via-[#b8924a] to-[#c5a467] shadow-md shadow-amber-950/35 ring-1 ring-amber-100/25',
        default => 'rounded-lg bg-gray-700 shadow-md ring-1 ring-white/10',
    };

    $iconBox = match ($type) {
        Room::TYPE_STANDARD => 'rounded-md bg-emerald-950/40 ring-1 ring-white/10',
        Room::TYPE_FAMILY => 'rounded-md bg-emerald-950/35 ring-1 ring-white/10',
        Room::TYPE_DELUXE => 'rounded-md bg-black/15 ring-1 ring-white/15',
        default => 'rounded-md bg-black/20',
    };

    $mutedWrap = $muted
        ? 'opacity-[0.68] saturate-[0.6] brightness-[0.96]'
        : '';
@endphp

<div class="px-3 py-4 flex max-w-max">
    <div class="w-28 sm:w-32">
        <div
            @class([
                'room-type-badge relative isolate flex w-full min-w-0 select-none items-stretch overflow-hidden',
                $muted ? 'room-type-badge--muted' : '',
                $outer,
                $mutedWrap,
                $compact ? 'min-h-[1.65rem]' : 'min-h-[2rem]',
            ])
        >
            {{-- Gloss sweep (see theme.css .room-type-badge::after) --}}

            {{-- Icon tile (slightly lighter inset) --}}
            <div
                @class([
                    'relative z-[2] flex shrink-0 items-center justify-center',
                    $iconBox,
                    $compact ? 'px-1.5 py-1' : 'px-2 py-1.5',
                ])
            >
                @if ($type === Room::TYPE_STANDARD)
                    {{-- Bed (outline, tabler-style) --}}
                    <svg
                        @class([$compact ? 'h-3.5 w-3.5' : 'h-4 w-4', 'text-white'])
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M7 9m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                        <path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16" />
                    </svg>
                @elseif ($type === Room::TYPE_FAMILY)
                    <x-filament::icon
                        icon="heroicon-o-users"
                        @class([
                            'text-white',
                            $compact ? 'h-3.5 w-3.5' : 'h-4 w-4',
                        ])
                    />
                @else
                    {{-- Crown (Lucide-style outline) --}}
                    <svg
                        @class([$compact ? 'h-3.5 w-3.5' : 'h-4 w-4', 'text-white'])
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path
                            d="M11.562 3.266a.5.5 0 0 1 .876 0L15.39 8.87a1 1 0 0 0 1.516.294L21.183 5.5a.5.5 0 0 1 .798.519l-2.834 10.246a1 1 0 0 1-.956.734H5.81a1 1 0 0 1-.957-.734L2.02 6.02a.5.5 0 0 1 .798-.519l4.276 3.664a1 1 0 0 0 1.516-.294z"
                        />
                        <path d="M5 21h14" />
                    </svg>
                @endif
            </div>

            {{-- Divider --}}
            <div class="relative z-[2] w-px shrink-0 bg-white/35" aria-hidden="true"></div>

            {{-- Label + optional count --}}
            <div
                @class([
                    'relative z-[2] flex min-w-0 flex-1 items-center justify-between gap-1',
                    $compact ? 'px-1.5 py-0.5' : 'px-2 py-1',
                ])
            >
                <span
                    @class([
                        'room-type-badge-label truncate font-bold uppercase tracking-wide text-white',
                        $compact ? 'text-[8px] leading-tight sm:text-[9px]' : 'text-[10px] sm:text-xs',
                        $hideLabelOnMobile ? 'hidden sm:inline' : '',
                    ])
                >
                    {{ $label }}
                </span>
                @if ($count !== null)
                    <span
                        @class([
                            'shrink-0 rounded-md bg-black/20 px-1 font-bold tabular-nums text-white ring-1 ring-white/15',
                            $compact ? 'py-px text-[8px]' : 'py-0.5 text-[10px]',
                        ])
                    >{{ $count }}</span>
                @endif
            </div>
        </div>
    </div>
</div>
