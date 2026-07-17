<?php

namespace App\Http\Middleware;

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

        if (! $this->security->isValidApiKeyFormat($apiKey) || ! $this->settings->verifyApiKey((string) $apiKey)) {
            $this->security->recordFailedAttempt($request);

            return $this->security->error('Neispravan ili nedostaje API ključ.', 401);
        }

        $this->security->clearFailedAttempts($request);
        $this->security->recordSuccessfulAccess($request);

        return $next($request);
    }
}
