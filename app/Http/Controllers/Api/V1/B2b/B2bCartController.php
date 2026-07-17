<?php

namespace App\Http\Controllers\Api\V1\B2b;

use App\Http\Controllers\Api\V1\B2b\Concerns\FormatsB2bResponses;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Models\B2bProduct;
use App\Services\B2b\B2bCartService;
use App\Services\B2b\B2bCheckoutService;
use App\Services\B2b\B2bShippingCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class B2bCartController extends Controller
{
    use FormatsB2bResponses;
    use RespondsWithJson;

    public function __construct(
        private readonly B2bCartService $cartService,
        private readonly B2bCheckoutService $checkoutService,
        private readonly B2bShippingCalculator $shippingCalculator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $customer = $this->b2bCustomer($request);
        $cart = $this->cartService->getOrCreateCart($customer);
        $cart = $this->cartService->loadCart($cart);

        return $this->success($this->formatCart($cart, $customer));
    }

    public function shippingQuote(Request $request): JsonResponse
    {
        $customer = $this->b2bCustomer($request);
        $cart = $this->cartService->getOrCreateCart($customer);
        $cart = $this->cartService->loadCart($cart);
        $formatted = $this->formatCart($cart, $customer);
        $shipping = $this->shippingCalculator->calculate($formatted['total']);

        return $this->success([
            'shipping_fee' => $shipping['fee'],
            'is_free' => $shipping['is_free'],
            'free_threshold' => $shipping['free_threshold'],
            'items_total' => $formatted['total'],
            'subtotal' => $formatted['subtotal'],
            'discount_total' => $formatted['discount_total'],
            'grand_total' => round($formatted['total'] + $shipping['fee'], 2),
            'item_count' => $formatted['item_count'],
            'free_shipping_remaining' => $shipping['is_free']
                ? 0
                : max(0, round($shipping['free_threshold'] - $formatted['total'], 2)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:b2b_products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $customer = $this->b2bCustomer($request);
        $product = B2bProduct::query()->whereKey($validated['product_id'])->where('is_active', true)->firstOrFail();

        $cart = $this->cartService->addItem($customer, $product, $validated['quantity']);

        return $this->success($this->formatCart($cart, $customer));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        $customer = $this->b2bCustomer($request);
        $cart = $this->cartService->updateItem($customer, $id, $validated['quantity']);

        return $this->success($this->formatCart($cart, $customer));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $customer = $this->b2bCustomer($request);
        $cart = $this->cartService->removeItem($customer, $id);

        return $this->success($this->formatCart($cart, $customer));
    }

    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_address' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customer = $this->b2bCustomer($request);
        $order = $this->checkoutService->checkout($customer, $validated);

        return $this->success($this->formatOrder($order->load('items')), [], 201);
    }
}
