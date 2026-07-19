<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AdminLoginProtection
{
    public function __construct(
        private readonly TurnstileVerifier $turnstileVerifier,
    ) {}

    public function ipRateLimitKey(?string $ip = null): string
    {
        return 'admin-login:'.($ip ?? request()->ip());
    }

    public function ensureIpNotBlocked(): void
    {
        $key = $this->ipRateLimitKey();
        $maxAttempts = max(1, (int) config('admin.login_ip_max_attempts', 10));

        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'data.email' => "Previše pokušaja prijave sa ove IP adrese. Pokušajte ponovo za {$seconds} sekundi.",
        ]);
    }

    public function recordFailedAttempt(?string $email = null): void
    {
        $decaySeconds = max(60, (int) config('admin.login_ip_decay_minutes', 15) * 60);

        RateLimiter::hit($this->ipRateLimitKey(), $decaySeconds);

        Log::warning('Admin login attempt failed', array_filter([
            'ip' => request()->ip(),
            'email' => $email,
            'user_agent' => request()->userAgent(),
        ]));
    }

    public function clearFailedAttempts(): void
    {
        RateLimiter::clear($this->ipRateLimitKey());
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{security_code?: bool}  $options
     */
    public function validateBotProtection(array $data, array $options = []): void
    {
        $checkSecurityCode = $options['security_code'] ?? true;

        if (! $this->isHoneypotEmpty($data['website'] ?? null)) {
            Log::notice('Admin login honeypot triggered', [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            throw ValidationException::withMessages([
                'data.email' => 'Neispravna email adresa ili lozinka.',
            ]);
        }

        if (! $this->isHoneypotEmpty($data['company'] ?? null)) {
            Log::notice('Admin login secondary honeypot triggered', [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            throw ValidationException::withMessages([
                'data.email' => 'Neispravna email adresa ili lozinka.',
            ]);
        }

        if ($checkSecurityCode && ! $this->isSecurityCodeValid($data['security_code'] ?? null)) {
            $this->recordFailedAttempt(is_string($data['email'] ?? null) ? $data['email'] : null);

            throw ValidationException::withMessages([
                'data.security_code' => 'Sigurnosni kod nije ispravan.',
            ]);
        }

        if ($this->turnstileVerifier->isEnabled() && ! $this->turnstileVerifier->verify(
            is_string($data['turnstile_token'] ?? null) ? $data['turnstile_token'] : null,
            request()->ip(),
        )) {
            $this->recordFailedAttempt(is_string($data['email'] ?? null) ? $data['email'] : null);

            throw ValidationException::withMessages([
                'data.turnstile_token' => 'Provjera sigurnosti nije uspjela. Pokušajte ponovo.',
            ]);
        }
    }

    private function isHoneypotEmpty(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    private function isSecurityCodeValid(mixed $value): bool
    {
        $secret = config('admin.login_secret');

        if (! is_string($secret) || $secret === '') {
            return true;
        }

        return hash_equals($secret, (string) ($value ?? ''));
    }
}
