<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileVerifier
{
    public function isEnabled(): bool
    {
        if (! (bool) config('turnstile.enabled', false)) {
            return false;
        }

        $siteKey = config('turnstile.site_key');
        $secretKey = config('turnstile.secret_key');

        return is_string($siteKey) && trim($siteKey) !== ''
            && is_string($secretKey) && trim($secretKey) !== '';
    }

    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        if ($token === null || trim($token) === '') {
            Log::warning('Turnstile verification failed: empty token');

            return false;
        }

        $secret = config('turnstile.secret_key');

        $response = Http::asForm()
            ->timeout(5)
            ->post((string) config('turnstile.verify_url'), array_filter([
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $remoteIp,
            ]));

        if (! $response->successful()) {
            Log::warning('Turnstile siteverify HTTP request failed', [
                'status' => $response->status(),
            ]);

            return false;
        }

        $success = (bool) $response->json('success', false);

        if (! $success) {
            Log::warning('Turnstile siteverify rejected token', [
                'error_codes' => $response->json('error-codes', []),
            ]);
        }

        return $success;
    }
}
