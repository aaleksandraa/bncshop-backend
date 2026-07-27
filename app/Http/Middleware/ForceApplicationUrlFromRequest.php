<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filament/Livewire read config('app.url') for dynamic JS imports — not only UrlGenerator.
 * When APP_URL still points at api.bncshop.ba but admin opens on api.bnc.ba, tables break (CORS).
 */
class ForceApplicationUrlFromRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if ($host !== '' && ! in_array($host, ['localhost', '127.0.0.1'], true)) {
            $root = $request->getSchemeAndHttpHost();

            URL::forceRootUrl($root);

            if ($request->isSecure()) {
                URL::forceScheme('https');
            }

            config([
                'app.url' => $root,
                'app.asset_url' => null,
            ]);
        }

        return $next($request);
    }
}
