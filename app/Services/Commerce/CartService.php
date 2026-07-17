<?php

namespace App\Services\Commerce;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\LoyaltyReward;
use App\Models\Product;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Loyalty\LoyaltySettings;
use App\Services\Pricing\CouponEngine;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Support\Str;
use RuntimeException;

class CartService
{
    /** @var list<string> */
    public const CART_RELATIONS = [
        'items.product.defaultImage',
        'loyaltyReward.product.defaultImage',
    ];

    public function __construct(
        private readonly PriceCalculator $priceCalculator,
        private readonly CouponEngine $couponEngine,
        private readonly LoyaltyService $loyaltyService,
        private readonly LoyaltySettings $loyaltySettings,
    ) {}

    public function getOrCreate(?string $sessionId = null, ?User $user = null): Cart
    {
        if ($user) {
            $cart = Cart::query()->where('user_id', $user->id)->first();
            if ($cart) {
                return $cart->load(self::CART_RELATIONS);
            }
        }

        if ($sessionId) {
            $cart = Cart::query()->where('session_id', $sessionId)->first();
            if ($cart) {
                if ($user && ! $cart->user_id) {
                    $cart->update(['user_id' => $user->id]);
                }

                return $cart->load(self::CART_RELATIONS);
            }
        }

        return Cart::query()->create([
            'session_id' => $sessionId ?? Str::uuid()->toString(),
            'user_id' => $user?->id,
        ])->load(self::CART_RELATIONS);
    }

    public function addItem(Cart $cart, Product $product, int $quantity): CartItem
    {
        $priceResult = $this->priceCalculator->calculate($product, $this->resolveCoupon($cart));

        $item = CartItem::query()->where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->where('is_loyalty_reward', false)
            ->first();

        if ($item) {
            $item->update([
                'quantity' => $item->quantity + $quantity,
                'unit_price' => $priceResult->displayPrice,
                'discount_snapshot' => $priceResult->toArray(),
                'price_confirmed' => true,
            ]);

            $item = $item->fresh(['product.defaultImage']);
        } else {
            $item = CartItem::query()->create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $priceResult->displayPrice,
                'discount_snapshot' => $priceResult->toArray(),
                'price_confirmed' => true,
            ])->load(['product.defaultImage']);
        }

        $this->tryActivatePendingCoupon($cart);

        return $item->fresh(['product.defaultImage']);
    }

    public function updateItem(CartItem $item, int $quantity): CartItem
    {
        if ($item->is_loyalty_reward) {
            throw new RuntimeException('Nagrada lojalnosti se ne može mijenjati.');
        }

        if ($quantity <= 0) {
            $item->delete();

            return $item;
        }

        $priceResult = $this->priceCalculator->calculate(
            $item->product,
            $this->resolveCoupon($item->cart)
        );

        $item->update([
            'quantity' => $quantity,
            'unit_price' => $priceResult->displayPrice,
            'discount_snapshot' => $priceResult->toArray(),
            'price_confirmed' => true,
        ]);

        return $item->fresh(['product.defaultImage']);
    }

    public function removeItem(CartItem $item): void
    {
        if ($item->is_loyalty_reward) {
            $cart = $item->cart;
            $item->delete();
            $this->removeLoyaltyReward($cart);

            return;
        }

        $item->delete();
    }

    /**
     * @return array{valid: bool, pending: bool, message: ?string, coupon: ?Coupon}
     */
    public function applyCoupon(Cart $cart, string $code, ?User $user = null): array
    {
        if ($cart->loyalty_reward_id && ! $this->loyaltySettings->get('combine_with_coupons', false)) {
            return [
                'valid' => false,
                'pending' => false,
                'message' => 'Kupon se ne može kombinovati s nagradom lojalnosti.',
                'coupon' => null,
            ];
        }

        $validation = $this->couponEngine->validate($code, $this->subtotalWithoutCoupon($cart), $user, $cart);

        if ($validation['valid']) {
            $cart->update([
                'coupon_code' => $validation['coupon']->code,
                'pending_coupon_code' => null,
            ]);
            $this->validatePrices($cart);

            return array_merge($validation, ['pending' => false]);
        }

        if ($this->shouldStorePendingCoupon($validation, $cart)) {
            $preview = $this->couponEngine->validateForPreview($code, null, $user);

            if ($preview['valid'] && $preview['coupon']) {
                $cart->update([
                    'pending_coupon_code' => $preview['coupon']->code,
                    'coupon_code' => null,
                ]);
                $this->validatePrices($cart);

                return [
                    'valid' => true,
                    'pending' => true,
                    'message' => 'Kupon spremljen — primjenjuje se u korpi.',
                    'coupon' => $preview['coupon'],
                ];
            }
        }

        return array_merge($validation, ['pending' => false]);
    }

    public function removeCoupon(Cart $cart): void
    {
        $cart->update([
            'coupon_code' => null,
            'pending_coupon_code' => null,
        ]);
        $this->validatePrices($cart);
    }

    public function tryActivatePendingCoupon(Cart $cart, ?User $user = null): void
    {
        if (! $cart->pending_coupon_code || $cart->coupon_code) {
            return;
        }

        $this->applyCoupon($cart->fresh(), $cart->pending_coupon_code, $user);
    }

    /**
     * @return array{valid: bool, message: ?string, reward: ?LoyaltyReward}
     */
    public function applyLoyaltyReward(Cart $cart, LoyaltyReward $reward, Customer $customer): array
    {
        if (($cart->coupon_code || $cart->pending_coupon_code) && ! $this->loyaltySettings->get('combine_with_coupons', false)) {
            return ['valid' => false, 'message' => 'Nagrada se ne može kombinovati s kuponom.', 'reward' => null];
        }

        $validation = $this->loyaltyService->validateRedemption($customer, $reward);
        if (! $validation['valid']) {
            return ['valid' => false, 'message' => $validation['message'], 'reward' => null];
        }

        $this->removeLoyaltyReward($cart);
        $cart->update(['loyalty_reward_id' => $reward->id]);

        if ($reward->type === 'free_product' && $reward->product_id) {
            $product = $reward->product;
            if ($product) {
                CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 0,
                    'discount_snapshot' => ['loyalty_reward' => true],
                    'price_confirmed' => true,
                    'is_loyalty_reward' => true,
                ]);
            }
        }

        $this->validatePrices($cart);

        return ['valid' => true, 'message' => null, 'reward' => $reward];
    }

    public function removeLoyaltyReward(Cart $cart): void
    {
        $cart->items()->where('is_loyalty_reward', true)->delete();
        $cart->update(['loyalty_reward_id' => null]);
        $this->validatePrices($cart);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function validatePrices(Cart $cart): array
    {
        $cart->loadMissing('items.product');
        $coupon = $this->resolveCoupon($cart);
        $changes = [];

        foreach ($cart->items as $item) {
            if ($item->is_loyalty_reward) {
                continue;
            }

            $current = $this->priceCalculator->calculate($item->product, $coupon);
            $changed = round((float) $item->unit_price, 2) !== round($current->displayPrice, 2);

            if ($changed) {
                $changes[] = [
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'old_price' => (float) $item->unit_price,
                    'new_price' => $current->displayPrice,
                ];

                $item->update([
                    'unit_price' => $current->displayPrice,
                    'discount_snapshot' => $current->toArray(),
                    'price_confirmed' => false,
                ]);
            }
        }

        return $changes;
    }

    public function hasUnconfirmedPrices(Cart $cart): bool
    {
        return $cart->items()
            ->where('is_loyalty_reward', false)
            ->where('price_confirmed', false)
            ->exists();
    }

    public function confirmPrices(Cart $cart): void
    {
        $cart->items()
            ->where('is_loyalty_reward', false)
            ->update(['price_confirmed' => true]);
    }

    public function subtotal(Cart $cart): float
    {
        $cart->loadMissing('items');

        return round((float) $cart->items
            ->where('is_loyalty_reward', false)
            ->sum(fn (CartItem $item): float => (float) $item->unit_price * (int) $item->quantity), 2);
    }

    public function subtotalWithoutCoupon(Cart $cart): float
    {
        $cart->loadMissing('items.product');

        return round((float) $cart->items
            ->where('is_loyalty_reward', false)
            ->sum(function (CartItem $item): float {
                if (! $item->product) {
                    return (float) $item->unit_price * (int) $item->quantity;
                }

                $price = $this->priceCalculator->calculate($item->product, null)->displayPrice;

                return $price * (int) $item->quantity;
            }), 2);
    }

    public function couponDiscountAmount(Cart $cart): float
    {
        $coupon = $this->resolveCoupon($cart);

        if (! $coupon) {
            return 0.0;
        }

        return max(0, round($this->subtotalWithoutCoupon($cart) - $this->discountedSubtotal($cart), 2));
    }

    public function loyaltyDiscount(Cart $cart): float
    {
        $reward = $this->resolveLoyaltyReward($cart);
        if (! $reward) {
            return 0.0;
        }

        if (in_array($reward->type, ['percentage', 'fixed'], true)) {
            $base = $this->loyaltyBaseSubtotal($cart);

            return $this->loyaltyService->calculateDiscountForCart($cart, $reward, $base);
        }

        return 0.0;
    }

    public function total(Cart $cart, float $shippingFee = 0.0): float
    {
        $subtotal = $this->discountedSubtotal($cart);
        $subtotal = max(0, round($subtotal - $this->loyaltyDiscount($cart), 2));

        return round($subtotal + $shippingFee, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Cart $cart, float $shippingFee = 0.0): array
    {
        $subtotalBeforeCoupon = $this->subtotalWithoutCoupon($cart);
        $couponDiscount = $this->couponDiscountAmount($cart);
        $discountedSubtotal = $this->discountedSubtotal($cart);
        $loyaltyDiscount = $this->loyaltyDiscount($cart);

        return [
            'subtotal' => $subtotalBeforeCoupon,
            'coupon_discount' => $couponDiscount,
            'loyalty_discount' => $loyaltyDiscount,
            'total' => $this->total($cart, $shippingFee),
            'loyalty_reward' => $this->resolveLoyaltyReward($cart),
            'discounted_subtotal' => $discountedSubtotal,
        ];
    }

    public function resolveLoyaltyReward(Cart $cart): ?LoyaltyReward
    {
        if (! $cart->loyalty_reward_id) {
            return null;
        }

        $cart->loadMissing('loyaltyReward.product');

        $reward = $cart->loyaltyReward;
        if (! $reward || ! $reward->isCurrentlyActive()) {
            return null;
        }

        return $reward;
    }

    private function loyaltyBaseSubtotal(Cart $cart): float
    {
        if ($this->loyaltySettings->get('combine_with_discounts', true)) {
            return $this->discountedSubtotal($cart);
        }

        $cart->loadMissing('items.product');

        return round((float) $cart->items
            ->where('is_loyalty_reward', false)
            ->sum(fn (CartItem $item): float => (float) ($item->product?->regular_price ?? $item->unit_price) * (int) $item->quantity), 2);
    }

    public function discountedSubtotal(Cart $cart): float
    {
        $coupon = $this->resolveCoupon($cart);

        if (! $coupon) {
            return $this->subtotal($cart);
        }

        if ($coupon->type === 'fixed') {
            return $this->couponEngine->applyToCart($cart, $coupon);
        }

        return $this->subtotal($cart);
    }

    private function resolveCoupon(Cart $cart): ?Coupon
    {
        $code = $cart->coupon_code ?? $cart->pending_coupon_code;

        if (! $code) {
            return null;
        }

        return Coupon::query()->where('code', $code)->first();
    }

    /**
     * @param  array{valid: bool, message: ?string, coupon: ?Coupon}  $validation
     */
    private function shouldStorePendingCoupon(array $validation, Cart $cart): bool
    {
        $message = $validation['message'] ?? '';

        if ($message === 'Minimalni iznos korpe za kupon nije dostignut.') {
            return false;
        }

        if ($message === 'Kupon nije primjenjiv na proizvode u korpi.') {
            return true;
        }

        return $cart->items()->where('is_loyalty_reward', false)->count() === 0;
    }
}
