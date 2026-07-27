<?php

namespace App\Http\Middleware;

use App\Support\ApplicationUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filament/Livewire read config('app.url') for dynamic JS imports — not only UrlGenerator.
 * When APP_URL still points at api.bncshop.ba but admin opens on api.bnc.ba, tables break (CORS).
 */
class ForceApplicationUrlFromRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        ApplicationUrl::syncFromRequest($request);

        return $next($request);
    }
}
