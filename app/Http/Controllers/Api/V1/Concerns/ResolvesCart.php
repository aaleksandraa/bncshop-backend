<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Cart;
use App\Services\Commerce\CartService;
use Illuminate\Http\Request;

trait ResolvesCart
{
    protected function resolveCart(Request $request, CartService $cartService): Cart
    {
        $sessionId = $request->attributes->get('cart_session_id');
        $user = $request->user();

        return $cartService->getOrCreate($sessionId, $user);
    }

    /**
     * @return array<string, mixed>
     */
    protected function cartMeta(Cart $cart): array
    {
        return [
            'cart_session' => $cart->session_id,
        ];
    }
}
