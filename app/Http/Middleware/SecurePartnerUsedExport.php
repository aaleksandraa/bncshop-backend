<?php

namespace App\Http\Middleware;

use App\Services\Integrations\PartnerExportSecurityService;
use App\Services\Integrations\PartnerExportSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurePartnerUsedExport
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
        if (! $this->settings->isUsedExportEnabled()) {
            return $this->security->error('Partner export API za polovne proizvode je isključen.', 403);
        }

        foreach ([
            fn () => $this->security->rejectQueryCredentials($request),
            fn () => $this->security->rejectInsecureTransport($request),
            fn () => $this->security->rejectTooManyFailedAttempts($request),
        ] as $check) {
            $response = $check();

            if ($response !== null) {
                return $response;
            }
        }

        return $next($request);
    }
}
