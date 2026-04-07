<x-filament-panels::page wire:poll.75s="refreshHealth">
    @php
        $emailUsed = max(0, (int) $this->emailsSentToday);
        $emailLimit = max(1, (int) $this->mailDailyLimit);
        $emailPercent = min(100, (int) round(($emailUsed / $emailLimit) * 100));
        $emailOnline = str_starts_with(strtolower((string) $this->emailHealth), 'online');
        $smsOnline = str_starts_with(strtolower((string) $this->smsHealth), 'online');
    @endphp

    <style>
        .settings-kpi-card {
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        .settings-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.08);
            border-color: rgba(116, 155, 102, 0.35);
        }

        .settings-soft-panel {
            background-image: radial-gradient(circle at top right, rgba(116, 155, 102, 0.14), transparent 45%);
        }

        .settings-progress-fill {
            transition: width .55s ease-in-out;
        }
    </style>

    <div class="space-y-6">
        <x-filament::section icon="heroicon-m-swatch" icon-color="success" class="settings-soft-panel">
            <x-slot name="heading">
                <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-50">Communication Settings</div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Same style system as Guest Demographics with Livewire tabs and health insights.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-wide {{ $emailOnline && $smsOnline ? 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-300' : 'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-300' }}">
                            {{ $emailOnline && $smsOnline ? 'Healthy' : 'Attention needed' }}
                        </span>
                        <x-filament::button size="sm" wire:click="refreshHealth" icon="heroicon-m-arrow-path">Refresh</x-filament::button>
                    </div>
                </div>
            </x-slot>

            <div class="inline-flex w-full flex-wrap gap-2 rounded-xl border border-gray-200 bg-gray-50 px-2 py-2 text-xs dark:border-white/10 dark:bg-gray-800/60">
                @foreach ([
                    'overview' => 'OVERVIEW',
                    'actions' => 'ACTIONS',
                    'email' => 'EMAIL CONFIG',
                    'sms' => 'SMS CONFIG',
                ] as $tabKey => $tabLabel)
                    <button type="button"
                        wire:click="setTab('{{ $tabKey }}')"
                        class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-wide transition {{ $this->activeTab === $tabKey ? 'bg-emerald-500 text-white shadow-sm' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-100 dark:border-gray-700' }}">
                        {{ $tabLabel }}
                    </button>
                @endforeach
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white/80 px-3 py-2 dark:border-white/10 dark:bg-gray-900/40">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Email status</p>
                    <p class="mt-1 text-sm font-bold {{ $emailOnline ? 'text-success-700 dark:text-success-300' : 'text-danger-700 dark:text-danger-300' }}">{{ $emailOnline ? 'Online' : 'Issue' }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white/80 px-3 py-2 dark:border-white/10 dark:bg-gray-900/40">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">SMS status</p>
                    <p class="mt-1 text-sm font-bold {{ $smsOnline ? 'text-success-700 dark:text-success-300' : 'text-danger-700 dark:text-danger-300' }}">{{ $smsOnline ? 'Online' : 'Issue' }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white/80 px-3 py-2 dark:border-white/10 dark:bg-gray-900/40">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Email left today</p>
                    <p class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ $this->emailsLeftToday }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white/80 px-3 py-2 dark:border-white/10 dark:bg-gray-900/40">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">SMS credits</p>
                    <p class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ $this->smsCredits !== null ? number_format($this->smsCredits, 2) : 'N/A' }}</p>
                </div>
            </div>
        </x-filament::section>

        @if (count($this->alerts) > 0)
            <div class="space-y-2">
                @foreach ($this->alerts as $alert)
                    <div class="rounded-xl border px-4 py-3 text-sm {{ $alert['level'] === 'danger' ? 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200' : 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200' }}">
                        <span class="font-semibold">{{ $alert['title'] }}:</span> {{ $alert['detail'] }}
                    </div>
                @endforeach
            </div>
        @endif

        @if ($this->activeTab === 'overview' || $this->activeTab === 'actions')
            <x-filament::section icon="heroicon-m-wrench-screwdriver" icon-color="primary" heading="Action Center">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="settings-kpi-card rounded-2xl border border-transparent bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Diagnostics</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Run on-demand checks with safe throttling and cached health reads.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <x-filament::button size="sm" wire:click="sendTestEmail" wire:loading.attr="disabled" wire:target="sendTestEmail">Send Test Email</x-filament::button>
                            <x-filament::button size="sm" wire:click="testSmsConnectivity" wire:loading.attr="disabled" wire:target="testSmsConnectivity">Test SMS Connectivity</x-filament::button>
                            <x-filament::button size="sm" color="gray" wire:click="refreshHealth">Re-sync</x-filament::button>
                        </div>
                        <p wire:loading wire:target="sendTestEmail,testSmsConnectivity,refreshHealth" class="mt-2 text-[10px] font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">Running...</p>
                    </div>
                    <div class="settings-kpi-card rounded-2xl border border-transparent bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Test email recipient</label>
                        <input type="email" wire:model.defer="testEmailRecipient" class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900/70" />
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Email alert threshold</label>
                                <input type="number" min="1" max="100" wire:model.blur="emailAlertThreshold" class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900/70" />
                            </div>
                            <div>
                                <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Low SMS threshold</label>
                                <input type="number" min="0" step="0.01" wire:model.blur="smsLowCreditThreshold" class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900/70" />
                            </div>
                        </div>
                    </div>
                </div>
            </x-filament::section>
        @endif

        @if ($this->activeTab === 'overview')
            <x-filament::section icon="heroicon-m-chart-bar-square" icon-color="success" heading="Live Service Snapshot">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <div class="settings-kpi-card relative flex flex-col justify-between overflow-hidden rounded-2xl border border-transparent bg-gray-50 p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email Health</dt>
                        <dd class="mt-2 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $this->emailHealth }}</dd>
                        <div class="mt-4">
                            <div class="mb-1 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400"><span>Usage</span><span>{{ $emailUsed }}/{{ $emailLimit }}</span></div>
                            <div class="h-2 rounded-full bg-gray-200 dark:bg-white/10">
                                <div class="settings-progress-fill h-2 rounded-full {{ $emailPercent > 85 ? 'bg-danger-500' : 'bg-emerald-500' }}" style="width: {{ $emailPercent }}%"></div>
                            </div>
                        </div>
                        <p class="mt-2 text-[11px] font-semibold uppercase tracking-wider {{ $emailPercent > 85 ? 'text-danger-700 dark:text-danger-300' : 'text-gray-500 dark:text-gray-400' }}">
                            {{ $emailPercent }}% quota used
                        </p>
                    </div>
                    <div class="settings-kpi-card relative flex flex-col justify-between overflow-hidden rounded-2xl border border-transparent bg-gray-50 p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">SMS Health</dt>
                        <dd class="mt-2 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $this->smsHealth }}</dd>
                        <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Credits: {{ $this->smsCredits !== null ? number_format($this->smsCredits, 2) : 'N/A' }}</p>
                    </div>
                    <div class="settings-kpi-card relative flex flex-col justify-between overflow-hidden rounded-2xl border border-transparent bg-gray-50 p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tracker</dt>
                        <dd class="mt-2 text-lg font-bold tracking-tight text-gray-950 dark:text-white">{{ $this->lastCheckedAt ?? 'Never' }}</dd>
                        <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Auto refresh: 75 seconds</p>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="settings-kpi-card rounded-2xl border border-transparent bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Quick Navigation</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <x-filament::button size="sm" color="gray" wire:click="setTab('email')">Open Email Config</x-filament::button>
                            <x-filament::button size="sm" color="gray" wire:click="setTab('sms')">Open SMS Config</x-filament::button>
                            <x-filament::button size="sm" color="gray" wire:click="setTab('actions')">Open Actions</x-filament::button>
                        </div>
                    </div>

                    <div class="settings-kpi-card rounded-2xl border border-transparent bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">System Recommendations</p>
                        <ul class="mt-2 space-y-1.5 text-sm text-gray-700 dark:text-gray-200">
                            @foreach ($this->recommendations as $tip)
                                <li class="flex items-start gap-2">
                                    <span class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    <span>{{ $tip }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </x-filament::section>
        @endif

        @if ($this->activeTab === 'email')
            <x-filament::section icon="heroicon-m-envelope" icon-color="primary" heading="Email Configuration (Hostinger SMTP)">
                <x-slot name="description">
                    Locked by default. Click edit credentials to unlock.
                </x-slot>
                <div class="mb-4 flex gap-2">
                    @if (! $this->editingMail)
                        <x-filament::modal id="confirm-mail-edit" width="md">
                            <x-slot name="trigger">
                                <x-filament::button size="sm">Edit Credentials</x-filament::button>
                            </x-slot>

                            <x-slot name="heading">Unlock Email Credentials?</x-slot>
                            <x-slot name="description">
                                You are about to unlock sensitive email settings. Continue only if you intend to update SMTP credentials.
                            </x-slot>

                            <x-slot name="footerActions">
                                <x-filament::button
                                    color="gray"
                                    x-on:click="$dispatch('close-modal', { id: 'confirm-mail-edit' })"
                                >
                                    Cancel
                                </x-filament::button>
                                <x-filament::button
                                    color="warning"
                                    x-on:click="$dispatch('close-modal', { id: 'confirm-mail-edit' }); $wire.enableMailEdit()"
                                >
                                    Yes, Unlock
                                </x-filament::button>
                            </x-slot>
                        </x-filament::modal>
                    @else
                        <x-filament::button size="sm" color="gray" wire:click="cancelMailEdit">Cancel</x-filament::button>
                        <x-filament::button size="sm" color="success" wire:click="saveMailSettings">Save Changes</x-filament::button>
                    @endif
                </div>
                <div class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Connection & Identity</div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    @foreach ([
                        ['key' => 'mailHost', 'label' => 'MAIL_HOST', 'type' => 'text'],
                        ['key' => 'mailPort', 'label' => 'MAIL_PORT', 'type' => 'text'],
                        ['key' => 'mailUsername', 'label' => 'MAIL_USERNAME', 'type' => 'email'],
                        ['key' => 'mailPassword', 'label' => 'MAIL_PASSWORD', 'type' => 'password'],
                        ['key' => 'mailEncryption', 'label' => 'MAIL_ENCRYPTION', 'type' => 'text'],
                        ['key' => 'mailDailyLimit', 'label' => 'MAIL_DAILY_LIMIT', 'type' => 'number'],
                        ['key' => 'mailFromAddress', 'label' => 'MAIL_FROM_ADDRESS', 'type' => 'email'],
                        ['key' => 'mailFromName', 'label' => 'MAIL_FROM_NAME', 'type' => 'text'],
                    ] as $field)
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $field['label'] }}</label>
                            <div class="relative">
                                <input type="{{ $field['key'] === 'mailPassword' ? ($this->showMailPassword ? 'text' : 'password') : $field['type'] }}" wire:model.defer="{{ $field['key'] }}"
                                class="w-full rounded-xl border px-3 py-2.5 {{ $field['key'] === 'mailPassword' ? 'pr-12' : '' }} text-sm {{ $this->editingMail ? 'border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-900/70' : 'cursor-not-allowed border-gray-200 bg-gray-100 text-gray-500 dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-400' }}"
                                @disabled(! $this->editingMail) />
                                @if ($field['key'] === 'mailPassword')
                                    <button
                                        type="button"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 rounded-md text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-white"
                                        wire:click="toggleMailPasswordVisibility"
                                        title="{{ $this->showMailPassword ? 'Hide password' : 'Show password' }}"
                                    >
                                        <x-filament::icon :icon="$this->showMailPassword ? 'heroicon-m-eye-slash' : 'heroicon-m-eye'" class="h-4 w-4" />
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        @if ($this->activeTab === 'sms')
            <x-filament::section icon="heroicon-m-chat-bubble-left-right" icon-color="success" heading="SMS Configuration (Semaphore)">
                <x-slot name="description">
                    Tracks account health, credits, and sent messages.
                </x-slot>
                <div class="mb-4 flex gap-2">
                    @if (! $this->editingSms)
                        <x-filament::modal id="confirm-sms-edit" width="md">
                            <x-slot name="trigger">
                                <x-filament::button size="sm">Edit Credentials</x-filament::button>
                            </x-slot>

                            <x-slot name="heading">Unlock SMS Credentials?</x-slot>
                            <x-slot name="description">
                                This will unlock Semaphore API fields for editing. Make sure you have the correct API key and sender name before saving.
                            </x-slot>

                            <x-slot name="footerActions">
                                <x-filament::button
                                    color="gray"
                                    x-on:click="$dispatch('close-modal', { id: 'confirm-sms-edit' })"
                                >
                                    Cancel
                                </x-filament::button>
                                <x-filament::button
                                    color="warning"
                                    x-on:click="$dispatch('close-modal', { id: 'confirm-sms-edit' }); $wire.enableSmsEdit()"
                                >
                                    Yes, Unlock
                                </x-filament::button>
                            </x-slot>
                        </x-filament::modal>
                    @else
                        <x-filament::button size="sm" color="gray" wire:click="cancelSmsEdit">Cancel</x-filament::button>
                        <x-filament::button size="sm" color="success" wire:click="saveSmsSettings">Save Changes</x-filament::button>
                    @endif
                </div>
                <div class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Provider Credentials</div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ([
                        ['key' => 'semaphoreApiKey', 'label' => 'SEMAPHORE_API_KEY', 'type' => 'password'],
                        ['key' => 'semaphoreOtpUrl', 'label' => 'SEMAPHORE_OTP_URL', 'type' => 'url'],
                        ['key' => 'semaphoreSenderName', 'label' => 'SEMAPHORE_SENDER_NAME', 'type' => 'text'],
                    ] as $field)
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $field['label'] }}</label>
                            <div class="relative">
                                <input type="{{ $field['key'] === 'semaphoreApiKey' ? ($this->showSmsApiKey ? 'text' : 'password') : $field['type'] }}" wire:model.defer="{{ $field['key'] }}"
                                class="w-full rounded-xl border px-3 py-2.5 {{ $field['key'] === 'semaphoreApiKey' ? 'pr-12' : '' }} text-sm {{ $this->editingSms ? 'border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-900/70' : 'cursor-not-allowed border-gray-200 bg-gray-100 text-gray-500 dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-400' }}"
                                @disabled(! $this->editingSms) />
                                @if ($field['key'] === 'semaphoreApiKey')
                                    <button
                                        type="button"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 rounded-md text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-white"
                                        wire:click="toggleSmsApiKeyVisibility"
                                        title="{{ $this->showSmsApiKey ? 'Hide API key' : 'Show API key' }}"
                                    >
                                        <x-filament::icon :icon="$this->showSmsApiKey ? 'heroicon-m-eye-slash' : 'heroicon-m-eye'" class="h-4 w-4" />
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>

