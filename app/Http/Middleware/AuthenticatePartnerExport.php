<?php

namespace App\Http\Middleware;

use App\Models\PartnerApiClient;
use App\Services\Integrations\PartnerExportSecurityService;
use App\Services\Integrations\PartnerExportSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePartnerExport
{
    public function __construct(
        private readonly PartnerExportSettings $settings,
        private readonly PartnerExportSecurityService $security,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $this->security->extractApiKey($request);

        if (! $this->security->isValidApiKeyFormat($apiKey)) {
            $this->security->recordFailedAttempt($request);

            return $this->security->error('Neispravan ili nedostaje API ključ.', 401);
        }

        $client = PartnerApiClient::findByPlainApiKey((string) $apiKey);

        if ($client === null || ! $client->enabled || ! $client->hasApiKey()) {
            $this->security->recordFailedAttempt($request);

            return $this->security->error('Neispravan ili nedostaje API ključ.', 401);
        }

        $targetSystemCode = $request->route('targetSystemCode');

        if ($targetSystemCode !== null && $client->code !== $targetSystemCode) {
            $this->security->recordFailedAttempt($request);

            return $this->security->error('Neispravan ili nedostaje API ključ.', 401);
        }

        foreach ([
            fn () => $this->security->rejectMissingIpAllowlist($request, $client),
            fn () => $this->security->rejectDisallowedIp($request, $client),
            fn () => $this->security->rejectRateLimited($request, $client),
        ] as $check) {
            $response = $check();

            if ($response !== null) {
                return $response;
            }
        }

        $this->security->clearFailedAttempts($request);
        $this->security->recordSuccessfulAccess($request, $client);
        $request->attributes->set('partner_api_client', $client);

        return $next($request);
    }
}
