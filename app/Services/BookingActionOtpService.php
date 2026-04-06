<?php

namespace App\Services;

use App\Mail\BookingActionOtp as BookingActionOtpMail;
use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        $booking->loadMissing('guest');
        $guest = $booking->guest;

        if ($guest === null) {
            throw ValidationException::withMessages([
                'email' => ['No guest record linked to this booking. Please contact the hotel.'],
            ]);
        }

        $email = strtolower(trim((string) $guest->email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => ['No valid email on file for this guest. Please contact the hotel.'],
            ]);
        }

        $maxSends = $this->maxSendsBeforeCooldown();
        $cooldownSeconds = $this->cooldownSeconds();

        $this->reserveEmailSendSlot($email, $maxSends, $cooldownSeconds);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $purposeLabel = $purpose === self::PURPOSE_CANCEL
            ? 'Cancellation'
            : 'Reschedule';

        $otpKey = $this->cacheKey($booking->reference_number, $purpose);
        Cache::put($otpKey, hash('sha256', $code), now()->addMinutes(self::TTL_MINUTES));

        try {
            Mail::to($email)->send(new BookingActionOtpMail(
                $code,
                $purposeLabel,
                self::TTL_MINUTES,
                (string) ($guest->full_name ?? 'Guest'),
            ));
        } catch (\Throwable $e) {
            Cache::forget($otpKey);
            $this->rollbackEmailSendReservation($email, $maxSends);
            Log::error('Booking action OTP email failed', ['exception' => $e->getMessage()]);

            throw ValidationException::withMessages([
                'otp' => ['Could not send verification code. Please try again shortly.'],
            ]);
        }
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

    private function emailRateKey(string $normalizedEmail): string
    {
        return hash('sha256', $normalizedEmail);
    }

    private function rateKeys(string $normalizedEmail): array
    {
        $hash = $this->emailRateKey($normalizedEmail);

        return [
            'lock' => self::CACHE_PREFIX.'email_rate_lock:'.$hash,
            'count' => self::CACHE_PREFIX.'email_sends:'.$hash,
            'block' => self::CACHE_PREFIX.'email_blocked_until:'.$hash,
        ];
    }

    private function maxSendsBeforeCooldown(): int
    {
        $n = (int) config('services.booking_action_otp.max_sends_before_cooldown', 3);

        return $n >= 1 ? $n : 3;
    }

    private function cooldownSeconds(): int
    {
        $n = (int) config('services.booking_action_otp.cooldown_seconds', 60);

        return $n >= 1 ? $n : 60;
    }

    private function reserveEmailSendSlot(string $normalizedEmail, int $maxSends, int $cooldownSeconds): void
    {
        $keys = $this->rateKeys($normalizedEmail);

        Cache::lock($keys['lock'], 10)->block(5, function () use ($keys, $maxSends, $cooldownSeconds) {
            $blockedUntil = Cache::get($keys['block']);
            if ($blockedUntil instanceof \Carbon\Carbon && now()->lt($blockedUntil)) {
                $seconds = max(1, (int) ceil($blockedUntil->getTimestamp() - now()->getTimestamp()));
                throw ValidationException::withMessages([
                    'email' => [
                        "Too many verification emails sent. Please try again in {$seconds} second".($seconds === 1 ? '' : 's').'.',
                    ],
                ]);
            }

            if ($blockedUntil !== null) {
                Cache::forget($keys['block']);
                Cache::forget($keys['count']);
            }

            $count = (int) Cache::get($keys['count'], 0) + 1;
            Cache::put($keys['count'], $count, now()->addHours(24));

            if ($count > $maxSends) {
                Cache::put($keys['count'], $count - 1, now()->addHours(24));
                throw ValidationException::withMessages([
                    'email' => ['Too many verification emails sent. Please try again later.'],
                ]);
            }

            if ($count === $maxSends) {
                Cache::put($keys['block'], now()->addSeconds($cooldownSeconds), now()->addSeconds($cooldownSeconds + 120));
                Cache::forget($keys['count']);
            }
        });
    }

    private function rollbackEmailSendReservation(string $normalizedEmail, int $maxSends): void
    {
        $keys = $this->rateKeys($normalizedEmail);

        Cache::lock($keys['lock'], 10)->block(5, function () use ($keys, $maxSends) {
            $blockedUntil = Cache::get($keys['block']);
            if ($blockedUntil instanceof \Carbon\Carbon && now()->lt($blockedUntil)) {
                Cache::forget($keys['block']);
                Cache::put($keys['count'], max(0, $maxSends - 1), now()->addHours(24));

                return;
            }

            $count = (int) Cache::get($keys['count'], 0);
            if ($count > 0) {
                Cache::put($keys['count'], $count - 1, now()->addHours(24));
            }
        });
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
