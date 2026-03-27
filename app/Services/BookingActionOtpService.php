<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BookingActionOtpService
{
    public const PURPOSE_CANCEL = 'cancel';

    public const PURPOSE_RESCHEDULE = 'reschedule';

    private const CACHE_PREFIX = 'booking_action_otp:';

    private const TTL_MINUTES = 10;

    public function send(Booking $booking, string $purpose): void
    {
        $this->assertValidPurpose($purpose);

        $apiKey = config('services.semaphore.api_key');
        if (empty($apiKey)) {
            Log::error('Semaphore API key not configured');
            throw ValidationException::withMessages([
                'otp' => ['SMS verification is temporarily unavailable. Please try again later.'],
            ]);
        }

        $booking->loadMissing('guest');
        $guest = $booking->guest;

        if ($guest === null) {
            throw ValidationException::withMessages([
                'phone' => ['No guest record linked to this booking. Please contact the hotel.'],
            ]);
        }

        // Semaphore `number` must be the guest's saved contact number (SMS recipient).
        $rawContact = $guest->contact_num;
        $recipient = self::normalizePhilippineMobile($rawContact);

        if ($recipient === null) {
            throw ValidationException::withMessages([
                'phone' => ['No valid mobile number on file for this guest. Please contact the hotel.'],
            ]);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $message = 'Your Marcelino\'s booking OTP is {otp}. Valid for '.self::TTL_MINUTES.' minutes.';

        $payload = [
            'apikey' => $apiKey,
            'number' => $recipient,
            'message' => $message,
            'code' => $code,
        ];

        $senderName = config('services.semaphore.sender_name');
        if (! empty($senderName)) {
            $payload['sendername'] = $senderName;
        }

        $url = config('services.semaphore.otp_url');

        try {
            $response = Http::asForm()
                ->timeout(20)
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::error('Semaphore OTP request failed', ['exception' => $e->getMessage()]);

            throw ValidationException::withMessages([
                'otp' => ['Could not send verification code. Please try again shortly.'],
            ]);
        }

        if (! $response->successful()) {
            Log::warning('Semaphore OTP HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw ValidationException::withMessages([
                'otp' => ['Could not send verification code. Please try again shortly.'],
            ]);
        }

        $key = $this->cacheKey($booking->reference_number, $purpose);
        Cache::put($key, hash('sha256', $code), now()->addMinutes(self::TTL_MINUTES));
    }

    public function verifyAndConsume(string $reference, string $purpose, string $input): bool
    {
        $this->assertValidPurpose($purpose);

        $normalized = preg_replace('/\D/', '', trim($input));
        if ($normalized === '' || $normalized === null) {
            return false;
        }

        $candidates = [$normalized];
        if (strlen($normalized) < 6) {
            $candidates[] = str_pad($normalized, 6, '0', STR_PAD_LEFT);
        }

        $key = $this->cacheKey($reference, $purpose);
        $storedHash = Cache::get($key);

        if (! is_string($storedHash) || $storedHash === '') {
            return false;
        }

        foreach ($candidates as $candidate) {
            if (strlen($candidate) !== 6) {
                continue;
            }
            if (hash_equals($storedHash, hash('sha256', $candidate))) {
                Cache::forget($key);

                return true;
            }
        }

        return false;
    }

    public static function normalizePhilippineMobile(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', trim($raw));
        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($digits, '63') && strlen($digits) >= 12) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '63'.substr($digits, 1);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return '63'.$digits;
        }

        return null;
    }

    private function cacheKey(string $reference, string $purpose): string
    {
        return self::CACHE_PREFIX.$reference.':'.$purpose;
    }

    private function assertValidPurpose(string $purpose): void
    {
        if (! in_array($purpose, [self::PURPOSE_CANCEL, self::PURPOSE_RESCHEDULE], true)) {
            throw ValidationException::withMessages([
                'purpose' => ['Invalid verification purpose.'],
            ]);
        }
    }
}
