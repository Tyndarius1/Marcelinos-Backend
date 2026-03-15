<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @php
        $conflicts = $getConflicts();
    @endphp
    @if (count($conflicts) > 0)
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Contact these guests before blocking ({{ count($conflicts) }} booking{{ count($conflicts) > 1 ? 's' : '' }}):</p>
        <div class="rounded-lg border border-gray-200 dark:border-white/10 overflow-hidden max-h-64 overflow-y-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 dark:bg-white/5 sticky top-0">
                    <tr>
                        <th class="text-left py-2 px-3 font-medium text-gray-600 dark:text-gray-400 w-8">#</th>
                        <th class="text-left py-2 px-3 font-medium text-gray-600 dark:text-gray-400">Guest</th>
                        <th class="text-left py-2 px-3 font-medium text-gray-600 dark:text-gray-400">Room / Venue</th>
                        <th class="text-left py-2 px-3 font-medium text-gray-600 dark:text-gray-400">Contact</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($conflicts as $index => $b)
                        <tr class="border-t border-gray-100 dark:border-white/5 {{ $index % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-gray-50/50 dark:bg-white/5' }}">
                            <td class="py-2 px-3 text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                            <td class="py-2 px-3">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $b['guest_name'] }}</span>
                                <span class="block text-xs font-mono text-gray-500 dark:text-gray-400">{{ $b['reference_number'] }}</span>
                            </td>
                            <td class="py-2 px-3 text-gray-600 dark:text-gray-300">
                                {{ $b['rooms'] }} @if($b['venues'] !== '—') · {{ $b['venues'] }} @endif
                            </td>
                            <td class="py-2 px-3">
                                <a href="mailto:{{ $b['email'] }}" class="text-primary-600 dark:text-primary-400 hover:underline block truncate max-w-[180px]" title="{{ $b['email'] }}">{{ $b['email'] }}</a>
                                <a href="tel:{{ $b['contact_num'] }}" class="text-primary-600 dark:text-primary-400 hover:underline text-xs">{{ $b['contact_num'] }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-dynamic-component>
