<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\AuthenticatesApiSession;
use App\Http\Controllers\Api\V1\Concerns\ResolvesCart;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Requests\Api\V1\CheckoutRequest;
use App\Http\Requests\Api\V1\ShippingQuoteRequest;
use App\Services\Commerce\CartService;
use App\Services\Commerce\CheckoutService;
use App\Services\Shipping\ShippingCalculator;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class CheckoutController extends Controller
{
    use AuthenticatesApiSession;
    use ResolvesCart, RespondsWithJson;

    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutService $checkoutService,
        private readonly ShippingCalculator $shippingCalculator,
    ) {}

    public function shippingQuote(ShippingQuoteRequest $request): JsonResponse
    {
        $cart = $this->resolveCart($request, $this->cartService);
        $result = $this->shippingCalculator->calculate($cart, $request->string('shipping_method')->toString());

        return $this->success([
            'shipping' => $result->toArray(),
            'subtotal' => $this->cartService->subtotal($cart),
            'total' => $this->cartService->total($cart, $result->fee),
        ], $this->cartMeta($cart));
    }

    public function store(CheckoutRequest $request): JsonResponse
    {
        $cart = $this->resolveCart($request, $this->cartService);

        try {
            $result = $this->checkoutService->createOrder(
                $cart,
                $request->validated(),
                $request->user()
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        $order = $result['order'];

        if ($result['registered_user']) {
            $this->loginUserSession($request, $result['registered_user']);
        }

        return $this->success([
            'order' => $order,
            'tracking_token' => $order->tracking_token,
        ], $this->cartMeta($cart), 201);
    }
}
