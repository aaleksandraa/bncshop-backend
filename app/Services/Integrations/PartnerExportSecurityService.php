<?php

namespace App\Services\Integrations;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\IpUtils;

class PartnerExportSecurityService
{
    public function __construct(
        private readonly PartnerExportSettings $settings,
    ) {}

    public function rejectQueryCredentials(Request $request): ?JsonResponse
    {
        foreach (['api_key', 'token', 'key', 'access_token'] as $parameter) {
            if ($request->query->has($parameter)) {
                $this->logEvent('query_credential_rejected', $request);

                return $this->error('Neispravan ili nedostaje API ključ.', 401);
            }
        }

        return null;
    }

    public function rejectInsecureTransport(Request $request): ?JsonResponse
    {
        if (! $this->settings->requireHttps()) {
            return null;
        }

        if ($request->isSecure()) {
            return null;
        }

        $this->logEvent('insecure_transport_rejected', $request);

        return $this->error('Partner export API zahtijeva HTTPS konekciju.', 403);
    }

    public function rejectMissingIpAllowlist(Request $request): ?JsonResponse
    {
        if (! $this->settings->requiresIpAllowlist()) {
            return null;
        }

        if ($this->settings->allowedIps() !== []) {
            return null;
        }

        $this->logEvent('missing_ip_allowlist', $request);

        return $this->error('Partner export API zahtijeva definisan IP allowlist prije aktivacije u produkciji.', 503);
    }

    public function rejectDisallowedIp(Request $request): ?JsonResponse
    {
        $allowedIps = $this->settings->allowedIps();

        if ($allowedIps === []) {
            return null;
        }

        $clientIp = (string) $request->ip();

        if ($clientIp !== '' && IpUtils::checkIp($clientIp, $allowedIps)) {
            return null;
        }

        $this->logEvent('ip_not_allowed', $request, [
            'client_ip' => $clientIp,
        ]);

        return $this->error('Pristup sa ove IP adrese nije dozvoljen.', 403);
    }

    public function rejectRateLimited(Request $request, ?string $apiKey): ?JsonResponse
    {
        $key = 'partner-export:requests:'.($apiKey ?: (string) $request->ip());

        if (! RateLimiter::tooManyAttempts($key, $this->settings->rateLimitPerMinute())) {
            RateLimiter::hit($key, 60);

            return null;
        }

        $this->logEvent('rate_limit_exceeded', $request);

        return $this->error('Previše zahtjeva. Pokušajte ponovo za minut.', 429);
    }

    public function rejectTooManyFailedAttempts(Request $request): ?JsonResponse
    {
        $key = $this->failedAttemptsKey($request);

        if (! RateLimiter::tooManyAttempts($key, $this->settings->maxFailedAuthPerMinute())) {
            return null;
        }

        $this->logEvent('failed_auth_rate_limit_exceeded', $request);

        return $this->error('Previše neuspjelih pokušaja autentifikacije. Pokušajte ponovo za minut.', 429);
    }

    public function recordFailedAttempt(Request $request): void
    {
        RateLimiter::hit($this->failedAttemptsKey($request), 60);
        $this->logEvent('auth_failed', $request);
    }

    public function clearFailedAttempts(Request $request): void
    {
        RateLimiter::clear($this->failedAttemptsKey($request));
    }

    public function isValidApiKeyFormat(?string $apiKey): bool
    {
        if ($apiKey === null || $apiKey === '') {
            return false;
        }

        return (bool) preg_match('/^bncpe_[A-Za-z0-9]{40}$/', $apiKey);
    }

    public function extractApiKey(Request $request): ?string
    {
        $authorization = $request->header('Authorization');

        if (is_string($authorization) && str_starts_with($authorization, 'Bearer ')) {
            $token = trim(substr($authorization, 7));

            return $token !== '' ? $token : null;
        }

        $headerKey = $request->header('X-API-Key');

        if (filled($headerKey)) {
            return trim((string) $headerKey);
        }

        return null;
    }

    public function recordSuccessfulAccess(Request $request): void
    {
        $this->settings->recordSuccessfulUse((string) $request->ip());
        $this->logEvent('auth_success', $request);
    }

    public function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'data' => null,
            'meta' => [],
            'errors' => [$message],
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logEvent(string $event, Request $request, array $context = []): void
    {
        if (! $this->settings->shouldLogAccess()) {
            return;
        }

        Log::info('partner_export.'.$event, array_merge([
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'user_agent' => $request->userAgent(),
        ], $context));
    }

    private function failedAttemptsKey(Request $request): string
    {
        return 'partner-export:failed-auth:'.(string) $request->ip();
    }
}
