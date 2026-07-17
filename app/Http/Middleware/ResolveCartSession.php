<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResolveCartSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $sessionId = $request->header('X-Cart-Session')
            ?? $request->cookie('cart_session')
            ?? $request->cookie('bnc_cart_session');

        $shouldPersistCookie = $sessionId === null;

        if ($sessionId === null) {
            $sessionId = (string) Str::uuid();
        }

        $request->attributes->set('cart_session_id', $sessionId);

        /** @var Response $response */
        $response = $next($request);

        if ($shouldPersistCookie || ! $request->hasCookie('cart_session')) {
            $response->headers->setCookie($this->makeCartSessionCookie('cart_session', $sessionId));
            $response->headers->setCookie($this->makeCartSessionCookie('bnc_cart_session', $sessionId));
            $response->headers->set('X-Cart-Session', $sessionId);
        }

        return $response;
    }

    private function makeCartSessionCookie(string $name, string $value): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(
            name: $name,
            value: $value,
            minutes: 60 * 24 * 30,
            path: '/',
            secure: app()->environment('production'),
            httpOnly: true,
            sameSite: 'lax',
        );
    }
}
