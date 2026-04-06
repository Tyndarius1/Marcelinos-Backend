{{-- Placed after global search, before the database notifications bell (admin panel only). --}}
<x-filament::icon-button
    :badge="$trashedCount > 0 ? $trashedCount : null"
    color="gray"
    :icon="\Filament\Support\Icons\Heroicon::OutlinedTrash"
    icon-size="lg"
    tag="a"
    :href="$url"
    :label="__('Recycle bin')"
    class="fi-topbar-recycle-bin-btn"
/>
