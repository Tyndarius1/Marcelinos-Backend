<?php

namespace App\Filament\Pages;

use App\Support\EnvEditor;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class Settings extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'settings';

    protected static ?string $title = 'Settings';

    protected string $view = 'filament.pages.settings';

    public bool $editingMail = false;

    public bool $editingSms = false;

    public string $mailHost = '';

    public string $mailPort = '';

    public string $mailUsername = '';

    public string $mailPassword = '';

    public string $mailEncryption = '';

    public string $mailFromAddress = '';

    public string $mailFromName = '';

    public int $mailDailyLimit = 1000;

    public string $semaphoreApiKey = '';

    public string $semaphoreOtpUrl = 'https://api.semaphore.co/api/v4/otp';

    public string $semaphoreSenderName = '';

    public string $emailHealth = 'Unknown';

    public string $smsHealth = 'Unknown';

    public ?float $smsCredits = null;

    public int $smsSentToday = 0;

    public int $emailsSentToday = 0;

    public int $emailsLeftToday = 0;

    public ?string $lastCheckedAt = null;

    public string $testEmailRecipient = '';

    public string $emailAlertThreshold = '80';

    public string $smsLowCreditThreshold = '50';

    public string $activeTab = 'overview';

    public bool $showMailPassword = false;

    public bool $showSmsApiKey = false;

    public function mount(): void
    {
        $this->loadFromEnv();
        $this->refreshHealth();
        $this->testEmailRecipient = $this->mailFromAddress;
        $this->normalizeAlertThresholds();
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['overview', 'actions', 'email', 'sms'], true)) {
            return;
        }

        $this->activeTab = $tab;
    }

    public function enableMailEdit(): void
    {
        $this->editingMail = true;
    }

    public function cancelMailEdit(): void
    {
        $this->editingMail = false;
        $this->showMailPassword = false;
        $this->loadFromEnv();
    }

    public function enableSmsEdit(): void
    {
        $this->editingSms = true;
    }

    public function cancelSmsEdit(): void
    {
        $this->editingSms = false;
        $this->showSmsApiKey = false;
        $this->loadFromEnv();
    }

    public function toggleMailPasswordVisibility(): void
    {
        $this->showMailPassword = ! $this->showMailPassword;
    }

    public function toggleSmsApiKeyVisibility(): void
    {
        $this->showSmsApiKey = ! $this->showSmsApiKey;
    }

    public function saveMailSettings(): void
    {
        if (! $this->editingMail) {
            return;
        }

        $this->validate([
            'mailHost' => ['required', 'string'],
            'mailPort' => ['required', 'string'],
            'mailUsername' => ['required', 'email'],
            'mailPassword' => ['required', 'string'],
            'mailEncryption' => ['nullable', 'string'],
            'mailFromAddress' => ['required', 'email'],
            'mailFromName' => ['required', 'string'],
            'mailDailyLimit' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        EnvEditor::updateMany([
            'MAIL_HOST' => $this->mailHost,
            'MAIL_PORT' => $this->mailPort,
            'MAIL_USERNAME' => $this->mailUsername,
            'MAIL_PASSWORD' => $this->mailPassword,
            'MAIL_ENCRYPTION' => $this->mailEncryption,
            'MAIL_FROM_ADDRESS' => $this->mailFromAddress,
            'MAIL_FROM_NAME' => $this->mailFromName,
            'MAIL_DAILY_LIMIT' => $this->mailDailyLimit,
        ]);

        config([
            'mail.mailers.smtp.host' => $this->mailHost,
            'mail.mailers.smtp.port' => (int) $this->mailPort,
            'mail.mailers.smtp.username' => $this->mailUsername,
            'mail.mailers.smtp.password' => $this->mailPassword,
            'mail.mailers.smtp.encryption' => $this->mailEncryption,
            'mail.from.address' => $this->mailFromAddress,
            'mail.from.name' => $this->mailFromName,
        ]);

        $this->editingMail = false;
        $this->refreshHealth();

        Notification::make()
            ->title('Email settings saved')
            ->success()
            ->send();

        $this->showMailPassword = false;
    }

    public function saveSmsSettings(): void
    {
        if (! $this->editingSms) {
            return;
        }

        $this->validate([
            'semaphoreApiKey' => ['required', 'string'],
            'semaphoreOtpUrl' => ['required', 'url'],
            'semaphoreSenderName' => ['required', 'string', 'max:11'],
        ]);

        EnvEditor::updateMany([
            'SEMAPHORE_API_KEY' => $this->semaphoreApiKey,
            'SEMAPHORE_OTP_URL' => $this->semaphoreOtpUrl,
            'SEMAPHORE_SENDER_NAME' => $this->semaphoreSenderName,
        ]);

        $this->editingSms = false;
        $this->refreshHealth();

        Notification::make()
            ->title('SMS settings saved')
            ->success()
            ->send();

        $this->showSmsApiKey = false;
    }

    public function refreshHealth(): void
    {
        $this->normalizeAlertThresholds();
        $this->emailHealth = $this->checkEmailHealth();
        $this->smsHealth = $this->checkSmsHealth();
        $this->emailsSentToday = $this->resolveEmailsSentToday();
        $this->emailsLeftToday = max(0, $this->mailDailyLimit - $this->emailsSentToday);
        $this->lastCheckedAt = now()->format('Y-m-d H:i:s');
    }

    public function sendTestEmail(): void
    {
        $this->validate([
            'testEmailRecipient' => ['required', 'email'],
        ]);

        try {
            Mail::raw('This is a test email from the Marcelinos Settings dashboard.', function ($message): void {
                $message->to($this->testEmailRecipient)
                    ->subject('Marcelinos Email Health Test');
            });

            Notification::make()
                ->title('Test email sent')
                ->body('Email was sent to '.$this->testEmailRecipient)
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Test email failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }

        $this->refreshHealth();
    }

    public function testSmsConnectivity(): void
    {
        if (trim($this->semaphoreApiKey) === '') {
            Notification::make()
                ->title('SMS API key missing')
                ->warning()
                ->send();

            return;
        }

        $apiHash = md5($this->semaphoreApiKey);
        $cooldownKey = "semaphore_connectivity_test_cooldown_{$apiHash}";
        if (! Cache::add($cooldownKey, 1, now()->addSeconds(20))) {
            Notification::make()
                ->title('Please wait')
                ->body('SMS connectivity test is cooling down to avoid rate limits.')
                ->info()
                ->send();
            return;
        }

        try {
            $response = Http::timeout(10)->get('https://api.semaphore.co/api/v4/account', [
                'apikey' => $this->semaphoreApiKey,
            ]);

            if (! $response->successful()) {
                if ($response->status() === 429) {
                    $retryAfter = $this->parseRetryAfterSeconds($response->header('Retry-After'));
                    $until = now()->addSeconds($retryAfter);
                    Cache::put("semaphore_rate_limited_until_{$apiHash}", $until->timestamp, $until);

                    Notification::make()
                        ->title('Semaphore rate limited')
                        ->body('Too many requests. Try again in ~'.$retryAfter.'s.')
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Semaphore connectivity failed')
                    ->body('HTTP '.$response->status())
                    ->danger()
                    ->send();

                return;
            }

            Notification::make()
                ->title('Semaphore connectivity OK')
                ->body('API access is healthy.')
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Semaphore connectivity failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }

        $this->refreshHealth();
    }

    public function getAlertsProperty(): array
    {
        $alerts = [];

        if (! str_starts_with(strtolower($this->emailHealth), 'online')) {
            $alerts[] = [
                'title' => 'Email service issue',
                'detail' => $this->emailHealth,
                'level' => 'danger',
            ];
        }

        if (! str_starts_with(strtolower($this->smsHealth), 'online')) {
            $smsLevel = Str::contains(strtolower($this->smsHealth), ['rate limit', 'rate-limited', 'throttle'])
                ? 'warning'
                : 'danger';

            $alerts[] = [
                'title' => 'SMS service issue',
                'detail' => $this->smsHealth,
                'level' => $smsLevel,
            ];
        }

        $emailUsagePercent = $this->mailDailyLimit > 0
            ? (int) round(($this->emailsSentToday / $this->mailDailyLimit) * 100)
            : 0;

        if ($emailUsagePercent >= $this->emailAlertThresholdValue()) {
            $alerts[] = [
                'title' => 'Email quota is high',
                'detail' => "Usage is at {$emailUsagePercent}% today.",
                'level' => 'warning',
            ];
        }

        if ($this->smsCredits !== null && $this->smsCredits <= $this->smsLowCreditThresholdValue()) {
            $alerts[] = [
                'title' => 'SMS credits are low',
                'detail' => 'Current credits: '.number_format($this->smsCredits, 2),
                'level' => 'warning',
            ];
        }

        return $alerts;
    }

    public function getRecommendationsProperty(): array
    {
        $items = [];

        if (! str_starts_with(strtolower($this->emailHealth), 'online')) {
            $items[] = 'Check Hostinger SMTP host, port, and encryption settings.';
        }

        if (! str_starts_with(strtolower($this->smsHealth), 'online')) {
            $items[] = 'Verify Semaphore API key and try SMS connectivity test.';
        }

        if ($this->mailDailyLimit > 0 && ((int) round(($this->emailsSentToday / $this->mailDailyLimit) * 100)) >= 85) {
            $items[] = 'Email quota is nearing limit. Consider spreading sends or increasing mailbox tier.';
        }

        if ($this->smsCredits !== null && $this->smsCredits <= $this->smsLowCreditThresholdValue()) {
            $items[] = 'SMS credits are low. Top up Semaphore credits to avoid delivery failures.';
        }

        if ($items === []) {
            $items[] = 'All systems look healthy. Keep credentials locked until needed.';
        }

        return $items;
    }

    private function loadFromEnv(): void
    {
        $this->mailHost = (string) env('MAIL_HOST', 'smtp.hostinger.com');
        $this->mailPort = (string) env('MAIL_PORT', '465');
        $this->mailUsername = (string) env('MAIL_USERNAME', '');
        $this->mailPassword = (string) env('MAIL_PASSWORD', '');
        $this->mailEncryption = (string) env('MAIL_ENCRYPTION', 'ssl');
        $this->mailFromAddress = (string) env('MAIL_FROM_ADDRESS', '');
        $this->mailFromName = (string) env('MAIL_FROM_NAME', '');
        $this->mailDailyLimit = (int) env('MAIL_DAILY_LIMIT', 1000);

        $this->semaphoreApiKey = (string) env('SEMAPHORE_API_KEY', '');
        $this->semaphoreOtpUrl = (string) env('SEMAPHORE_OTP_URL', 'https://api.semaphore.co/api/v4/otp');
        $this->semaphoreSenderName = (string) env('SEMAPHORE_SENDER_NAME', '');
    }

    private function checkEmailHealth(): string
    {
        $host = trim($this->mailHost);
        $port = (int) $this->mailPort;

        if ($host === '' || $port <= 0) {
            return 'Misconfigured';
        }

        $cacheKey = 'mail_health_'.md5(strtolower($host).'|'.$port.'|'.strtolower((string) $this->mailEncryption));

        return (string) Cache::remember($cacheKey, now()->addSeconds(45), function () use ($host, $port): string {
            $transport = strtolower($this->mailEncryption) === 'ssl' ? 'ssl://' : '';

            $connection = @fsockopen($transport.$host, $port, $errorNumber, $errorMessage, 3);

            if ($connection === false) {
                return 'Offline ('.$errorNumber.' '.$errorMessage.')';
            }

            fclose($connection);

            return 'Online';
        });
    }

    private function checkSmsHealth(): string
    {
        if (trim($this->semaphoreApiKey) === '') {
            $this->smsCredits = null;
            $this->smsSentToday = 0;

            return 'Missing API key';
        }

        try {
            $apiHash = md5($this->semaphoreApiKey);
            $today = now()->toDateString();

            $rateLimitedUntil = Cache::get("semaphore_rate_limited_until_{$apiHash}");
            if (is_numeric($rateLimitedUntil) && (int) $rateLimitedUntil > now()->timestamp) {
                $this->applyCachedSmsSnapshot($apiHash, $today);
                return 'Rate limited (retry soon)';
            }

            $accountPayload = Cache::remember(
                "semaphore_account_{$apiHash}",
                now()->addMinutes(5),
                function (): array {
                    $response = Http::timeout(10)
                        ->get('https://api.semaphore.co/api/v4/account', [
                            'apikey' => $this->semaphoreApiKey,
                        ]);

                    return [
                        'ok' => $response->successful(),
                        'status' => $response->status(),
                        'retry_after' => $this->parseRetryAfterSeconds($response->header('Retry-After')),
                        'data' => $response->successful() ? $response->json() : null,
                    ];
                }
            );

            if (! ($accountPayload['ok'] ?? false)) {
                if ((int) ($accountPayload['status'] ?? 0) === 429) {
                    $retryAfter = (int) ($accountPayload['retry_after'] ?? 60);
                    $until = now()->addSeconds(max(15, $retryAfter));
                    Cache::put("semaphore_rate_limited_until_{$apiHash}", $until->timestamp, $until);

                    $this->applyCachedSmsSnapshot($apiHash, $today);
                    return 'Rate limited (HTTP 429)';
                }

                $this->smsCredits = null;
                $this->smsSentToday = 0;

                return 'Offline (HTTP '.(int) ($accountPayload['status'] ?? 0).')';
            }

            $account = is_array($accountPayload['data'] ?? null) ? $accountPayload['data'] : [];
            $this->smsCredits = isset($account['credit_balance']) ? (float) $account['credit_balance'] : null;
            Cache::put("semaphore_last_ok_account_{$apiHash}", ['credits' => $this->smsCredits], now()->addHours(6));

            $messagesPayload = Cache::remember(
                "semaphore_messages_{$apiHash}_{$today}",
                now()->addMinutes(2),
                function () use ($today): array {
                    $response = Http::timeout(10)
                        ->get('https://api.semaphore.co/api/v4/messages', [
                            'apikey' => $this->semaphoreApiKey,
                            'startDate' => $today,
                            'endDate' => $today,
                            'limit' => 1000,
                        ]);

                    return [
                        'ok' => $response->successful(),
                        'status' => $response->status(),
                        'retry_after' => $this->parseRetryAfterSeconds($response->header('Retry-After')),
                        'data' => $response->successful() ? $response->json() : null,
                    ];
                }
            );

            if (! ($messagesPayload['ok'] ?? false) && (int) ($messagesPayload['status'] ?? 0) === 429) {
                $retryAfter = (int) ($messagesPayload['retry_after'] ?? 60);
                $until = now()->addSeconds(max(15, $retryAfter));
                Cache::put("semaphore_rate_limited_until_{$apiHash}", $until->timestamp, $until);

                $this->applyCachedSmsSnapshot($apiHash, $today);
                return 'Rate limited (HTTP 429)';
            }

            $messages = $messagesPayload['data'] ?? null;
            $this->smsSentToday = is_array($messages) ? count($messages) : 0;
            Cache::put("semaphore_last_ok_sent_today_{$apiHash}_{$today}", $this->smsSentToday, now()->addHours(6));

            return 'Online';
        } catch (\Throwable) {
            $this->smsCredits = null;
            $this->smsSentToday = 0;

            return 'Offline';
        }
    }

    private function applyCachedSmsSnapshot(string $apiHash, string $today): void
    {
        $account = Cache::get("semaphore_last_ok_account_{$apiHash}");
        if (is_array($account) && array_key_exists('credits', $account)) {
            $this->smsCredits = is_numeric($account['credits']) ? (float) $account['credits'] : null;
        } else {
            $this->smsCredits = null;
        }

        $sentToday = Cache::get("semaphore_last_ok_sent_today_{$apiHash}_{$today}");
        $this->smsSentToday = is_numeric($sentToday) ? (int) $sentToday : 0;
    }

    private function parseRetryAfterSeconds(?string $retryAfterHeader): int
    {
        $value = trim((string) $retryAfterHeader);

        if ($value === '') {
            return 60;
        }

        if (ctype_digit($value)) {
            return max(15, min(3600, (int) $value));
        }

        try {
            $parsed = \Carbon\Carbon::parse($value);
            $diff = now()->diffInSeconds($parsed, false);
            return max(15, min(3600, $diff > 0 ? $diff : 60));
        } catch (\Throwable) {
            return 60;
        }
    }

    private function resolveEmailsSentToday(): int
    {
        $key = 'mail_sent_count_'.now()->toDateString();

        return (int) Cache::get($key, 0);
    }

    private function normalizeAlertThresholds(): void
    {
        $this->emailAlertThreshold = (string) $this->emailAlertThresholdValue();
        $this->smsLowCreditThreshold = number_format($this->smsLowCreditThresholdValue(), 2, '.', '');
    }

    private function emailAlertThresholdValue(): int
    {
        return max(1, min(100, (int) $this->emailAlertThreshold));
    }

    private function smsLowCreditThresholdValue(): float
    {
        return max(0, (float) $this->smsLowCreditThreshold);
    }
}

