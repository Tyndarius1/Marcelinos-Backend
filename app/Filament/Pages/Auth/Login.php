<?php

namespace App\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\MultiFactor\Contracts\HasBeforeChallengeHook;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\RateLimiter;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        $failedKey = $this->failedLoginRateLimiterKey($data['email'] ?? '');

        if (RateLimiter::tooManyAttempts($failedKey, $this->maxFailedLoginAttempts())) {
            $this->getRateLimitedNotification(new TooManyRequestsException(
                static::class,
                'authenticate',
                request()->ip(),
                RateLimiter::availableIn($failedKey),
            ))?->send();

            return null;
        }

        return $this->authenticateWithCredentials($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function authenticateWithCredentials(array $data): ?LoginResponse
    {
        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();

        $authProvider = $authGuard->getProvider(); /** @phpstan-ignore-line */
        $credentials = $this->getCredentialsFromFormData($data);

        $user = $authProvider->retrieveByCredentials($credentials);

        if ((! $user) || (! $authProvider->validateCredentials($user, $credentials))) {
            $this->userUndertakingMultiFactorAuthentication = null;

            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        if (
            filled($this->userUndertakingMultiFactorAuthentication) &&
            (decrypt($this->userUndertakingMultiFactorAuthentication) === $user->getAuthIdentifier())
        ) {
            if ($this->isMultiFactorChallengeRateLimited($user)) {
                return null;
            }

            $this->multiFactorChallengeForm->validate();
        } else {
            foreach (Filament::getMultiFactorAuthenticationProviders() as $multiFactorAuthenticationProvider) {
                if (! $multiFactorAuthenticationProvider->isEnabled($user)) {
                    continue;
                }

                $this->userUndertakingMultiFactorAuthentication = encrypt($user->getAuthIdentifier());

                if ($multiFactorAuthenticationProvider instanceof HasBeforeChallengeHook) {
                    $multiFactorAuthenticationProvider->beforeChallenge($user);
                }

                break;
            }

            if (filled($this->userUndertakingMultiFactorAuthentication)) {
                $this->multiFactorChallengeForm->fill();

                return null;
            }
        }

        if (! $authGuard->attemptWhen($credentials, function (Authenticatable $user): bool {
            if (! ($user instanceof FilamentUser)) {
                return true;
            }

            return $user->canAccessPanel(Filament::getCurrentOrDefaultPanel());
        }, $data['remember'] ?? false)) {
            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        session()->regenerate();

        RateLimiter::clear($this->failedLoginRateLimiterKey($data['email'] ?? ''));

        return app(LoginResponse::class);
    }

    protected function throwFailureValidationException(): never
    {
        $data = $this->form->getState();
        RateLimiter::hit(
            $this->failedLoginRateLimiterKey($data['email'] ?? ''),
            $this->failedLoginDecaySeconds(),
        );

        parent::throwFailureValidationException();
    }

    protected function failedLoginRateLimiterKey(?string $email): string
    {
        $normalized = strtolower(trim((string) $email));

        return 'filament-login-failed:'.sha1($normalized.'|'.request()->ip());
    }

    protected function maxFailedLoginAttempts(): int
    {
        return max(1, (int) config('login.max_attempts', 5));
    }

    protected function failedLoginDecaySeconds(): int
    {
        return max(60, (int) config('login.decay_seconds', 900));
    }
}
