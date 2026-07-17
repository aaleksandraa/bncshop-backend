<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ResolvesCart;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Resources\CartItemResource;
use App\Http\Resources\CartResource;
use App\Http\Requests\Api\V1\ApplyCouponRequest;
use App\Http\Requests\Api\V1\ApplyLoyaltyRewardRequest;
use App\Http\Requests\Api\V1\StoreCartItemRequest;
use App\Http\Requests\Api\V1\UpdateCartItemRequest;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\LoyaltyReward;
use App\Models\Product;
use App\Services\Commerce\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use ResolvesCart, RespondsWithJson;

    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request, $this->cartService);
        $summary = $this->cartService->summary($cart);

        return $this->success([
            'cart' => (new CartResource($cart))->resolve(),
            'subtotal' => $summary['subtotal'],
            'coupon_discount' => $summary['coupon_discount'],
            'loyalty_discount' => $summary['loyalty_discount'],
            'total' => $summary['total'],
            'loyalty_reward' => $summary['loyalty_reward'],
        ], $this->cartMeta($cart));
    }

    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $cart = $this->resolveCart($request, $this->cartService);
        $product = Product::query()->public()->active()->findOrFail($request->integer('product_id'));

        $item = $this->cartService->addItem($cart, $product, $request->integer('quantity'));

        return $this->success([
            'item' => (new CartItemResource($item->load('product.defaultImage')))->resolve(),
            'subtotal' => $this->cartService->subtotal($cart->fresh(CartService::CART_RELATIONS)),
        ], $this->cartMeta($cart), 201);
    }

    public function update(UpdateCartItemRequest $request, int $id): JsonResponse
    {
        $cart = $this->resolveCart($request, $this->cartService);
        $item = $this->findCartItem($cart, $id);

        $updated = $this->cartService->updateItem($item, $request->integer('quantity'));

        return $this->success([
            'item' => $updated->exists ? (new CartItemResource($updated->load('product.defaultImage')))->resolve() : null,
            'subtotal' => $this->cartService->subtotal($cart->fresh(CartService::CART_RELATIONS)),
        ], $this->cartMeta($cart));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $cart = $this->resolveCart($request, $this->cartService);
        $item = $this->findCartItem($cart, $id);

        $this->cartService->removeItem($item);

        return $this->success([
            'subtotal' => $this->cartService->subtotal($cart->fresh(CartService::CART_RELATIONS)),
        ], $this->cartMeta($cart));
    }

    public function applyCoupon(ApplyCouponRequest $request): JsonResponse
    {
        $cart = $this->resolveCart($request, $this->cartService);
        $result = $this->cartService->applyCoupon($cart, $request->string('code')->toString(), $request->user());

        if (! $result['valid']) {
            return $this->error($result['message'] ?? 'Kupon nije validan.');
        }

        $cart = $cart->fresh(CartService::CART_RELATIONS);
        $summary = $this->cartService->summary($cart);

        return $this->success([
            'coupon' => $result['coupon'],
            'pending' => (bool) ($result['pending'] ?? false),
            'message' => $result['message'],
            'cart' => (new CartResource($cart))->resolve(),
            'subtotal' => $summary['subtotal'],
            'coupon_discount' => $summary['coupon_discount'],
            'total' => $summary['total'],
        ], $this->cartMeta($cart));
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request, $this->cartService);
        $this->cartService->removeCoupon($cart);

        return $this->success([
            'subtotal' => $this->cartService->subtotal($cart->fresh(CartService::CART_RELATIONS)),
            'total' => $this->cartService->total($cart->fresh(CartService::CART_RELATIONS)),
        ], $this->cartMeta($cart));
    }

    public function validatePrices(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request, $this->cartService);
        $changes = $this->cartService->validatePrices($cart);
        $summary = $this->cartService->summary($cart);

        return $this->success([
            'changes' => $changes,
            'subtotal' => $summary['subtotal'],
            'total' => $summary['total'],
            'requires_confirmation' => $this->cartService->hasUnconfirmedPrices($cart),
        ], $this->cartMeta($cart));
    }

    public function confirmPrices(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request, $this->cartService);
        $this->cartService->confirmPrices($cart);
        $summary = $this->cartService->summary($cart->fresh(CartService::CART_RELATIONS));

        return $this->success([
            'subtotal' => $summary['subtotal'],
            'total' => $summary['total'],
            'requires_confirmation' => false,
        ], $this->cartMeta($cart));
    }

    public function applyLoyaltyReward(ApplyLoyaltyRewardRequest $request): JsonResponse
    {
        $cart = $this->resolveCart($request, $this->cartService);
        $customer = Customer::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['phone' => $request->user()->phone],
        );

        $reward = LoyaltyReward::query()->findOrFail($request->integer('reward_id'));
        $result = $this->cartService->applyLoyaltyReward($cart, $reward, $customer);

        if (! $result['valid']) {
            return $this->error($result['message'] ?? 'Nagrada nije validna.');
        }

        $cart = $cart->fresh(CartService::CART_RELATIONS);
        $summary = $this->cartService->summary($cart);

        return $this->success([
            'reward' => $result['reward'],
            'cart' => (new CartResource($cart))->resolve(),
            'subtotal' => $summary['subtotal'],
            'loyalty_discount' => $summary['loyalty_discount'],
            'total' => $summary['total'],
        ], $this->cartMeta($cart));
    }

    public function removeLoyaltyReward(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request, $this->cartService);
        $this->cartService->removeLoyaltyReward($cart);

        $cart = $cart->fresh(CartService::CART_RELATIONS);
        $summary = $this->cartService->summary($cart);

        return $this->success([
            'cart' => (new CartResource($cart))->resolve(),
            'subtotal' => $summary['subtotal'],
            'total' => $summary['total'],
        ], $this->cartMeta($cart));
    }

    private function findCartItem(\App\Models\Cart $cart, int $id): CartItem
    {
        return CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('id', $id)
            ->firstOrFail();
    }
}
