<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureB2bCustomer
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

        if (! $user->is_b2b_customer) {
            return response()->json([
                'data' => null,
                'meta' => [],
                'errors' => ['Ovaj račun nema pristup B2B portalu.'],
            ], 403);
        }

        $accessToken = $user->currentAccessToken();
        if ($accessToken instanceof \Laravel\Sanctum\PersonalAccessToken) {
            if ($accessToken->abilities === ['*'] || ! $accessToken->can('b2b:access')) {
                return response()->json([
                    'data' => null,
                    'meta' => [],
                    'errors' => ['Nevažeći B2B token.'],
                ], 403);
            }
        }

        $customer = $user->b2bCustomer;

        if (! $customer || ! $customer->is_active) {
            return response()->json([
                'data' => null,
                'meta' => [],
                'errors' => ['Vaš B2B račun nije aktivan.'],
            ], 403);
        }

        $request->attributes->set('b2b_customer', $customer);

        return $next($request);
    }
}
