<?php

namespace App\Http\Middleware;

use App\Services\Integrations\PartnerExportSecurityService;
use App\Services\Integrations\PartnerExportSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurePartnerExport
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
        if (! $this->settings->isEnabled()) {
            return $this->security->error('Partner export API je isključen.', 403);
        }

        foreach ([
            fn () => $this->security->rejectQueryCredentials($request),
            fn () => $this->security->rejectInsecureTransport($request),
            fn () => $this->security->rejectMissingIpAllowlist($request),
            fn () => $this->security->rejectDisallowedIp($request),
            fn () => $this->security->rejectTooManyFailedAttempts($request),
        ] as $check) {
            $response = $check();

            if ($response !== null) {
                return $response;
            }
        }

        $apiKey = $this->security->extractApiKey($request);

        $rateLimited = $this->security->rejectRateLimited($request, $apiKey);

        if ($rateLimited !== null) {
            return $rateLimited;
        }

        return $next($request);
    }
}
