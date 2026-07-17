<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSeller
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'data' => null,
                'meta' => [],
                'errors' => ['Niste prijavljeni.'],
            ], 401);
        }

        if ($user->is_customer) {
            return response()->json([
                'data' => null,
                'meta' => [],
                'errors' => ['Ovaj račun nema pristup prodavačkom panelu.'],
            ], 403);
        }

        if (! $user->can('view_orders') && ! $user->can('manage_orders')) {
            return response()->json([
                'data' => null,
                'meta' => [],
                'errors' => ['Nemate dozvolu za pristup narudžbama.'],
            ], 403);
        }

        $accessToken = $user->currentAccessToken();
        if ($accessToken instanceof \Laravel\Sanctum\PersonalAccessToken) {
            if ($accessToken->abilities === ['*'] || ! $accessToken->can('seller:access')) {
                return response()->json([
                    'data' => null,
                    'meta' => [],
                    'errors' => ['Nevažeći prodavački token.'],
                ], 403);
            }
        }

        return $next($request);
    }
}
