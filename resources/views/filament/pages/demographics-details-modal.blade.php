<div class="space-y-4 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
    @if ($bookings->isEmpty())
        <div class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">
            No bookings found for this period.
        </div>
    @else
        <div class="flex flex-col gap-4">
            @foreach ($bookings as $booking)
                <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm transition hover:shadow-md overflow-hidden">
                    <div class="px-5 py-4 flex flex-col sm:flex-row justify-between items-start gap-3 border-b border-gray-100 dark:border-white/5 bg-gray-50/50 dark:bg-white/5">
                        <div class="flex items-start gap-3">
                            <div class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 font-bold">
                                {{ substr($booking->guest->first_name, 0, 1) }}{{ substr($booking->guest->last_name, 0, 1) }}
                            </div>
                            <div>
                                <h4 class="text-base font-semibold text-gray-950 dark:text-white">
                                    {{ $booking->guest->full_name }}
                                </h4>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-xs font-mono text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                        <x-filament::icon icon="heroicon-m-hashtag" class="w-3 h-3"/>
                                        {{ $booking->reference_number }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="shrink-0 pt-1">
                            <span class="inline-flex items-center gap-x-1.5 rounded-full px-2.5 py-1 text-xs font-medium text-gray-700 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                <x-filament::icon icon="heroicon-m-calendar-days" class="w-3.5 h-3.5 text-primary-500" />
                                {{ $booking->check_in->format('M d, Y') }} - {{ $booking->check_out->format('M d, Y') }}
                            </span>
                        </div>
                    </div>

                    <div class="p-5">
                        @if ($booking->guest->is_international)
                            <div class="inline-flex items-center gap-2 mb-3 px-2.5 py-1 rounded-md bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400">
                                <x-filament::icon icon="heroicon-m-globe-americas" class="h-4 w-4" />
                                <span class="text-xs font-bold uppercase tracking-wider">International Guest</span>
                            </div>
                            <div class="grid grid-cols-1 gap-2 text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-500 dark:text-gray-400 font-medium whitespace-nowrap">Country:</span> 
                                    <span class="font-bold text-gray-900 dark:text-white text-base">{{ $booking->guest->country ?: 'Not Specified' }}</span>
                                </div>
                            </div>
                        @else
                            <div class="inline-flex items-center gap-2 mb-4 px-2.5 py-1 rounded-md bg-success-50 dark:bg-success-500/10 text-success-700 dark:text-success-400">
                                <x-filament::icon icon="heroicon-m-map-pin" class="h-4 w-4" />
                                <span class="text-xs font-bold uppercase tracking-wider">Local Guest</span>
                            </div>
                            
                            <!-- Highlighted Region & Province -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                <div class="rounded-lg bg-gray-50 dark:bg-white/5 p-3 border border-gray-100 dark:border-white/5 shadow-sm">
                                    <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Region</span>
                                    <span class="text-lg font-bold text-primary-600 dark:text-primary-400">{{ $booking->guest->region ?: 'Not Specified' }}</span>
                                </div>
                                <div class="rounded-lg bg-gray-50 dark:bg-white/5 p-3 border border-gray-100 dark:border-white/5 shadow-sm">
                                    <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Province</span>
                                    <span class="text-lg font-bold text-primary-600 dark:text-primary-400">{{ $booking->guest->province ?: 'Not Specified' }}</span>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-sm border-t border-gray-100 dark:border-white/5 pt-4">
                                <div>
                                    <span class="block text-xs text-gray-400 mb-0.5 uppercase tracking-wider">Municipality/City</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-200">{{ $booking->guest->municipality ?: 'Not Specified' }}</span>
                                </div>
                                <div>
                                    <span class="block text-xs text-gray-400 mb-0.5 uppercase tracking-wider">Barangay</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-200">{{ $booking->guest->barangay ?: 'Not Specified' }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
