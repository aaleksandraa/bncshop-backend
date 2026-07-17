<?php

namespace App\Services\Pricing;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CategoryScopeResolver;

class CouponEngine
{
    public function __construct(
        private readonly DiscountEngine $discountEngine,
        private readonly CategoryScopeResolver $categoryScopeResolver,
    ) {}

    /**
     * @return array{valid: bool, message: ?string, coupon: ?Coupon}
     */
    public function validate(string $code, float $cartSubtotal, ?User $user = null, ?Cart $cart = null): array
    {
        $coupon = $this->findCoupon($code);

        if (! $coupon) {
            return ['valid' => false, 'message' => 'Kupon nije pronađen.', 'coupon' => null];
        }

        $baseValidation = $this->validateBaseRules($coupon, $user);

        if (! $baseValidation['valid']) {
            return $baseValidation;
        }

        if ($coupon->min_cart_amount !== null && $cartSubtotal < (float) $coupon->min_cart_amount) {
            return [
                'valid' => false,
                'message' => 'Minimalni iznos korpe za kupon nije dostignut.',
                'coupon' => null,
            ];
        }

        if ($cart && ! $this->cartHasApplicableProducts($coupon, $cart)) {
            return [
                'valid' => false,
                'message' => 'Kupon nije primjenjiv na proizvode u korpi.',
                'coupon' => null,
            ];
        }

        return ['valid' => true, 'message' => null, 'coupon' => $coupon];
    }

    /**
     * @return array{valid: bool, message: ?string, coupon: ?Coupon}
     */
    public function validateForPreview(string $code, ?Product $product = null, ?User $user = null): array
    {
        $coupon = $this->findCoupon($code);

        if (! $coupon) {
            return ['valid' => false, 'message' => 'Kupon nije pronađen.', 'coupon' => null];
        }

        $baseValidation = $this->validateBaseRules($coupon, $user);

        if (! $baseValidation['valid']) {
            return $baseValidation;
        }

        if ($product && ! $this->isApplicableToProduct($coupon, $product)) {
            return [
                'valid' => false,
                'message' => 'Kupon nije primjenjiv na ovaj proizvod.',
                'coupon' => null,
            ];
        }

        return ['valid' => true, 'message' => null, 'coupon' => $coupon];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function previewForProduct(string $code, Product $product, ?User $user = null): ?array
    {
        $validation = $this->validateForPreview($code, $product, $user);

        if (! $validation['valid'] || ! $validation['coupon']) {
            return [
                'code' => strtoupper(trim($code)),
                'applicable' => false,
                'message' => $validation['message'],
            ];
        }

        $coupon = $validation['coupon'];
        $priceCalculator = app(PriceCalculator::class);
        $baseResult = $priceCalculator->calculate($product, null);
        $couponResult = $priceCalculator->calculate($product, $coupon);

        $discountAmount = round(max(0, $baseResult->displayPrice - $couponResult->displayPrice), 2);
        $applicable = $coupon->type === 'fixed'
            ? $this->isApplicableToProduct($coupon, $product)
            : $discountAmount > 0;

        return [
            'code' => $coupon->code,
            'applicable' => $applicable,
            'price' => $coupon->type === 'fixed' && $applicable
                ? $baseResult->displayPrice
                : $couponResult->displayPrice,
            'discount_amount' => $discountAmount,
            'min_cart_amount' => $coupon->min_cart_amount,
            'type' => $coupon->type,
            'value' => $coupon->value,
            'message' => $coupon->type === 'fixed' && $applicable
                ? 'Fiksni popust primjenjuje se u korpi.'
                : null,
        ];
    }

    public function apply(float $price, Coupon $coupon, ?Product $product = null): float
    {
        if ($product && ! $this->isApplicableToProduct($coupon, $product)) {
            return $price;
        }

        if ($product && ! config('bnc.coupon_combines_with_sale', false)) {
            $discount = $this->discountEngine->bestForProduct($product);
            if ($discount && ! $discount->combines_with_coupons) {
                return $price;
            }
        }

        if ($coupon->type === 'percentage') {
            return round($price * (1 - ((float) $coupon->value / 100)), 2);
        }

        return $price;
    }

    public function applyToCartSubtotal(float $subtotal, Coupon $coupon, ?Cart $cart = null): float
    {
        if ($cart) {
            return $this->applyToCart($cart, $coupon);
        }

        if ($coupon->type === 'percentage') {
            return round($subtotal * (1 - ((float) $coupon->value / 100)), 2);
        }

        return max(0, round($subtotal - (float) $coupon->value, 2));
    }

    public function applyToCart(Cart $cart, Coupon $coupon): float
    {
        $cart->loadMissing('items.product');
        $scope = $this->resolveScope($coupon);

        $totalSubtotal = 0.0;
        $applicableSubtotal = 0.0;

        foreach ($cart->items as $item) {
            if ($item->is_loyalty_reward || ! $item->product) {
                continue;
            }

            $lineTotal = $this->lineBaseTotal($item, $coupon);
            $totalSubtotal += $lineTotal;

            if ($scope === 'all' || $this->isApplicableToProduct($coupon, $item->product)) {
                $applicableSubtotal += $lineTotal;
            }
        }

        if ($coupon->type === 'percentage') {
            $discount = round($applicableSubtotal * ((float) $coupon->value / 100), 2);

            return max(0, round($totalSubtotal - $discount, 2));
        }

        return max(0, round($totalSubtotal - min((float) $coupon->value, $applicableSubtotal), 2));
    }

    public function discountAmount(float $subtotal, Coupon $coupon, ?Cart $cart = null): float
    {
        return round($subtotal - $this->applyToCartSubtotal($subtotal, $coupon, $cart), 2);
    }

    public function cartHasApplicableProducts(Coupon $coupon, Cart $cart): bool
    {
        $scope = $this->resolveScope($coupon);

        if ($scope === 'all') {
            return $cart->items()->where('is_loyalty_reward', false)->exists();
        }

        $cart->loadMissing('items.product.category', 'items.product.tags');

        foreach ($cart->items as $item) {
            if ($item->is_loyalty_reward) {
                continue;
            }

            if ($item->product && $this->isApplicableToProduct($coupon, $item->product)) {
                return true;
            }
        }

        return false;
    }

    public function isApplicableToProduct(Coupon $coupon, Product $product): bool
    {
        $applicable = $coupon->applicable_to;

        if (! is_array($applicable) || $applicable === []) {
            return true;
        }

        $scope = $this->resolveScope($coupon);

        return match ($scope) {
            'all' => true,
            'products' => in_array(
                $product->id,
                array_map(intval(...), $applicable['product_ids'] ?? []),
                true,
            ),
            'categories' => $this->categoryScopeResolver->matchesAnyCategory(
                $product,
                array_map(intval(...), $applicable['category_ids'] ?? []),
                (bool) ($applicable['include_subcategories'] ?? false),
            ),
            'brands' => $this->matchesAnyManufacturer(
                $product,
                array_map(intval(...), $applicable['manufacturer_ids'] ?? []),
            ),
            'tags' => $this->matchesAnyTag(
                $product,
                array_map(intval(...), $applicable['tag_ids'] ?? []),
            ),
            default => $this->matchesLegacyRules($applicable, $product),
        };
    }

    /**
     * @return array{valid: bool, message: ?string, coupon: ?Coupon}
     */
    private function validateBaseRules(Coupon $coupon, ?User $user): array
    {
        if (! $coupon->is_active) {
            return ['valid' => false, 'message' => 'Kupon nije aktivan.', 'coupon' => null];
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return ['valid' => false, 'message' => 'Kupon još nije aktivan.', 'coupon' => null];
        }

        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            return ['valid' => false, 'message' => 'Kupon je istekao.', 'coupon' => null];
        }

        if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
            return ['valid' => false, 'message' => 'Kupon je iskorišten.', 'coupon' => null];
        }

        if ($user && $coupon->single_use_per_customer) {
            $alreadyUsed = $coupon->usages()->where('user_id', $user->id)->exists();
            if ($alreadyUsed) {
                return ['valid' => false, 'message' => 'Kupon ste već iskoristili.', 'coupon' => null];
            }
        }

        return ['valid' => true, 'message' => null, 'coupon' => $coupon];
    }

    private function findCoupon(string $code): ?Coupon
    {
        return Coupon::query()
            ->where('code', strtoupper(trim($code)))
            ->first();
    }

    private function lineBaseTotal(CartItem $item, Coupon $coupon): float
    {
        $priceCalculator = app(PriceCalculator::class);
        $basePrice = $priceCalculator->calculate($item->product, null)->displayPrice;

        if ($coupon->type === 'percentage') {
            return round($basePrice * (int) $item->quantity, 2);
        }

        return round((float) $item->unit_price * (int) $item->quantity, 2);
    }

    /**
     * @param  array<string, mixed>  $applicable
     */
    private function matchesLegacyRules(array $applicable, Product $product): bool
    {
        $productIds = $applicable['product_ids'] ?? [];
        if ($productIds !== [] && ! in_array($product->id, $productIds, true)) {
            return false;
        }

        $categoryIds = $applicable['category_ids'] ?? [];
        if ($categoryIds !== [] && ! $this->categoryScopeResolver->matchesAnyCategory(
            $product,
            array_map(intval(...), $categoryIds),
            (bool) ($applicable['include_subcategories'] ?? false),
        )) {
            return false;
        }

        $brandIds = $applicable['manufacturer_ids'] ?? [];
        if ($brandIds !== [] && ! $this->matchesAnyManufacturer($product, array_map(intval(...), $brandIds))) {
            return false;
        }

        $tagIds = $applicable['tag_ids'] ?? [];
        if ($tagIds !== [] && ! $this->matchesAnyTag($product, array_map(intval(...), $tagIds))) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<int, int>  $manufacturerIds
     */
    private function matchesAnyManufacturer(Product $product, array $manufacturerIds): bool
    {
        if ($manufacturerIds === [] || $product->manufacturer_id === null) {
            return false;
        }

        return in_array((int) $product->manufacturer_id, $manufacturerIds, true);
    }

    /**
     * @param  array<int, int>  $tagIds
     */
    private function matchesAnyTag(Product $product, array $tagIds): bool
    {
        if ($tagIds === []) {
            return false;
        }

        if ($product->relationLoaded('tags')) {
            return $product->tags->contains(fn ($tag): bool => in_array((int) $tag->id, $tagIds, true));
        }

        return $product->tags()->whereIn('tags.id', $tagIds)->exists();
    }

    private function resolveScope(Coupon $coupon): string
    {
        $applicable = $coupon->applicable_to;

        if (! is_array($applicable) || $applicable === []) {
            return 'all';
        }

        if (isset($applicable['scope']) && is_string($applicable['scope'])) {
            return $applicable['scope'];
        }

        if (($applicable['product_ids'] ?? []) !== []) {
            return 'products';
        }

        if (($applicable['category_ids'] ?? []) !== []) {
            return 'categories';
        }

        if (($applicable['manufacturer_ids'] ?? []) !== []) {
            return 'brands';
        }

        if (($applicable['tag_ids'] ?? []) !== []) {
            return 'tags';
        }

        return 'all';
    }
}
