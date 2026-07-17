<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Http;

class TurnstileVerifier
{
    public function isEnabled(): bool
    {
        return (bool) config('turnstile.enabled', false);
    }

    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        if ($token === null || trim($token) === '') {
            return false;
        }

        $secret = config('turnstile.secret_key');

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $response = Http::asForm()
            ->timeout(5)
            ->post((string) config('turnstile.verify_url'), array_filter([
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $remoteIp,
            ]));

        if (! $response->successful()) {
            return false;
        }

        return (bool) $response->json('success', false);
    }
}
