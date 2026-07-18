<?php

namespace App\Services\Commerce;

use App\Jobs\TrackAnalyticsEventJob;
use App\Mail\OrderConfirmationCustomer;
use App\Mail\TemplatedOrderMail;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Models\LoyaltyReward;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Loyalty\LoyaltySettings;
use App\Services\Marketing\BrevoService;
use App\Services\Marketing\BrevoSettings;
use App\Services\Marketing\MarketingContactSyncService;
use App\Services\Pricing\CouponEngine;
use App\Services\Pricing\PriceCalculator;
use App\Services\Shipping\ShippingCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class CheckoutService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly ShippingCalculator $shippingCalculator,
        private readonly CouponEngine $couponEngine,
        private readonly PriceCalculator $priceCalculator,
        private readonly StockService $stockService,
        private readonly LoyaltyService $loyaltyService,
        private readonly LoyaltySettings $loyaltySettings,
        private readonly MarketingContactSyncService $marketingContactSyncService,
        private readonly BrevoService $brevoService,
        private readonly BrevoSettings $brevoSettings,
        private readonly CheckoutSettings $checkoutSettings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{valid: bool, errors: array<int, string>, shipping?: array<string, mixed>}
     */
    public function validate(array $data, Cart $cart): array
    {
        $errors = [];
        $required = ['first_name', 'last_name', 'phone', 'address', 'city', 'postal_code', 'shipping_method'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = "Polje {$field} je obavezno.";
            }
        }

        if (! empty($data['email']) && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail format nije validan.';
        }

        if (empty($data['accepted_terms'])) {
            $errors[] = 'Morate prihvatiti uslove kupovine i politiku privatnosti.';
        }

        $user = ($data['user'] ?? null) instanceof User ? $data['user'] : null;

        if (! $this->checkoutSettings->get('guest_checkout_enabled', true) && ! $user) {
            $errors[] = 'Kupovina bez registracije trenutno nije dostupna. Prijavite se da nastavite.';
        }

        if (! empty($data['create_account'])) {
            if (empty($data['email'])) {
                $errors[] = 'E-mail je obavezan za kreiranje korisničkog računa.';
            }

            if (empty($data['password'])) {
                $errors[] = 'Lozinka je obavezna za kreiranje korisničkog računa.';
            }
        }

        $cart->loadMissing('items.product', 'loyaltyReward.product');

        if ($cart->items->isEmpty()) {
            $errors[] = 'Korpa je prazna.';
        }

        $priceChanges = $this->cartService->validatePrices($cart);
        if ($priceChanges !== []) {
            $errors[] = 'Cijene u korpi su se promijenile. Potvrdite nove cijene.';
        }

        if ($this->cartService->hasUnconfirmedPrices($cart)) {
            $errors[] = 'Potvrdite nove cijene prije nastavka.';
        }

        $this->cartService->tryActivatePendingCoupon($cart, $user);
        $cart->refresh();

        foreach ($cart->items as $item) {
            if ($item->is_loyalty_reward) {
                continue;
            }

            $product = $item->product;

            if (! $product || ! $product->is_public || $product->status !== 'active') {
                $errors[] = "Proizvod {$item->product?->name} nije dostupan.";
                continue;
            }

            if (! $this->stockService->canFulfill($product, (int) $item->quantity)) {
                $errors[] = "Nedovoljna zaliha za {$product->name}.";
            }
        }

        $coupon = $this->resolveCoupon($cart);
        if ($coupon) {
            $validation = $this->couponEngine->validate(
                $coupon->code,
                $this->cartService->subtotalWithoutCoupon($cart),
                isset($data['user']) && $data['user'] instanceof User ? $data['user'] : null,
                $cart,
            );

            if (! $validation['valid']) {
                $errors[] = $validation['message'] ?? 'Kupon nije validan.';
            }
        }

        $loyaltyReward = $this->cartService->resolveLoyaltyReward($cart);
        if ($loyaltyReward) {
            $customer = $this->resolveCustomer($data['user'] ?? null);
            if (! $customer) {
                $errors[] = 'Nagrada lojalnosti zahtijeva prijavu.';
            } else {
                $loyaltyValidation = $this->loyaltyService->validateRedemption($customer, $loyaltyReward);
                if (! $loyaltyValidation['valid']) {
                    $errors[] = $loyaltyValidation['message'] ?? 'Nagrada lojalnosti nije validna.';
                }
            }

            if ($coupon && ! $this->loyaltySettings->get('combine_with_coupons', false)) {
                $errors[] = 'Nagrada se ne može kombinovati s kuponom.';
            }
        }

        $shipping = $this->shippingCalculator->calculate($cart, (string) ($data['shipping_method'] ?? 'delivery'));

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'shipping' => $shipping->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{order: Order, registered_user: ?User}
     */
    public function createOrder(Cart $cart, array $data, ?User $user = null): array
    {
        $data['user'] = $user;
        $validation = $this->validate($data, $cart);

        if (! $validation['valid']) {
            throw new RuntimeException(implode(' ', $validation['errors']));
        }

        $registeredUser = null;

        if (! empty($data['create_account']) && ! $user) {
            $user = $this->registerCheckoutUser($data);
            $registeredUser = $user;
        }

        $order = DB::transaction(function () use ($cart, $data, $user, $validation): Order {
            $cart->loadMissing('items.product.manufacturer', 'items.product.category', 'items.product.attributeValues', 'items.product.supplierOffers', 'loyaltyReward.product');
            $coupon = $this->resolveCoupon($cart);
            $loyaltyReward = $this->cartService->resolveLoyaltyReward($cart);
            $couponDiscount = $this->cartService->couponDiscountAmount($cart);
            $subtotalAfterCoupon = $this->cartService->discountedSubtotal($cart);
            $discountTotal = $couponDiscount;

            $loyaltyDiscount = $loyaltyReward
                ? $this->cartService->loyaltyDiscount($cart)
                : 0.0;

            $subtotalAfterLoyalty = max(0, round($subtotalAfterCoupon - $loyaltyDiscount, 2));
            $discountTotal = round($discountTotal + $loyaltyDiscount, 2);
            $rawSubtotal = $this->cartService->subtotalWithoutCoupon($cart);

            $shippingResult = $this->shippingCalculator->calculate(
                $cart,
                (string) $data['shipping_method']
            );

            $customer = $this->resolveCustomer($user);
            $customerId = $customer?->id;

            $order = Order::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'tracking_token' => Str::uuid()->toString(),
                'user_id' => $user?->id,
                'customer_id' => $customerId,
                'status' => 'nova',
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'address' => $data['address'],
                'city' => $data['city'],
                'postal_code' => $data['postal_code'],
                'company_name' => $data['company_name'] ?? null,
                'jib' => $data['jib'] ?? null,
                'pdv_number' => $data['pdv_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms_accepted_at' => now(),
                'create_account_requested' => ! empty($data['create_account']),
                'subtotal' => $rawSubtotal,
                'discount_total' => $discountTotal,
                'shipping_fee' => $shippingResult->fee,
                'total' => round($subtotalAfterLoyalty + $shippingResult->fee, 2),
                'shipping_method' => $data['shipping_method'],
                'shipping_rule_snapshot' => $shippingResult->snapshot,
                'coupon_id' => $coupon?->id,
                'payment_method' => $data['payment_method'] ?? 'pay_on_delivery',
                'items_count' => (int) $cart->items->sum('quantity'),
                'loyalty_discount_amount' => $loyaltyDiscount,
                'loyalty_reward_id' => $loyaltyReward?->id,
            ]);

            foreach ($cart->items as $item) {
                $product = $item->product;
                $isLoyaltyItem = (bool) $item->is_loyalty_reward;
                $selectedSupplierSku = $product->supplierOffers
                    ->where('is_selected_price_source', true)
                    ->value('supplier_sku');
                $orderCode = $product->codeForOrder($selectedSupplierSku);

                if ($isLoyaltyItem) {
                    OrderItem::query()->create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'external_product_id' => $product->external_product_id,
                        'product_name' => $product->name,
                        'sku' => $orderCode,
                        'barcode' => $product->barcode,
                        'brand_name' => $product->manufacturer?->name,
                        'category_path' => $product->category?->path ?? $product->category?->full_slug,
                        'unit_price' => 0,
                        'discount_amount' => 0,
                        'final_price' => 0,
                        'quantity' => (int) $item->quantity,
                        'line_total' => 0,
                        'supplier_sku' => $selectedSupplierSku,
                        'supplier_name' => null,
                        'attributes_snapshot' => [],
                        'discount_snapshot' => ['loyalty_reward' => true],
                        'discount_id' => null,
                    ]);

                    $this->stockService->reserve($product, (int) $item->quantity);

                    continue;
                }

                $priceResult = $this->priceCalculator->calculate($product, $coupon);

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'external_product_id' => $product->external_product_id,
                    'product_name' => $product->name,
                    'sku' => $orderCode,
                    'barcode' => $product->barcode,
                    'brand_name' => $product->manufacturer?->name,
                    'category_path' => $product->category?->path ?? $product->category?->full_slug,
                    'unit_price' => (float) $item->unit_price,
                    'discount_amount' => $priceResult->discountAmount,
                    'final_price' => $priceResult->displayPrice,
                    'quantity' => (int) $item->quantity,
                    'line_total' => round((float) $item->unit_price * (int) $item->quantity, 2),
                    'supplier_sku' => $selectedSupplierSku,
                    'supplier_name' => null,
                    'attributes_snapshot' => $product->attributeValues->map(fn ($attr): array => [
                        'name' => $attr->attribute_name_snapshot,
                        'value' => $attr->normalized_value ?? $attr->raw_value,
                    ])->values()->all(),
                    'discount_snapshot' => $item->discount_snapshot,
                    'discount_id' => $priceResult->discount?->id,
                ]);

                $this->stockService->reserve($product, (int) $item->quantity);
            }

            if ($coupon) {
                $coupon->increment('used_count');
                CouponUsage::query()->create([
                    'coupon_id' => $coupon->id,
                    'order_id' => $order->id,
                    'user_id' => $user?->id,
                    'used_at' => now(),
                ]);
            }

            if ($loyaltyReward && $customer) {
                $this->loyaltyService->redeemForCheckout(
                    $customer,
                    $loyaltyReward,
                    $order,
                    $loyaltyDiscount,
                );
            }

            $cart->items()->delete();
            $cart->update(['coupon_code' => null, 'pending_coupon_code' => null, 'loyalty_reward_id' => null]);

            TrackAnalyticsEventJob::dispatch(
                'order_created',
                [
                    'order_id' => $order->id,
                    'total' => (float) $order->total,
                    'item_count' => $order->items_count,
                ],
                $user?->id,
                $cart->session_id,
            );

            $this->sendOrderEmails($order);
            $this->syncMarketingContact($order);

            return $order->fresh(['items']);
        });

        return [
            'order' => $order,
            'registered_user' => $registeredUser,
        ];
    }

    private function resolveCustomer(?User $user): ?Customer
    {
        if (! $user) {
            return null;
        }

        return Customer::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['phone' => $user->phone],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function registerCheckoutUser(array $data): User
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if ($email === '') {
            throw new RuntimeException('E-mail je obavezan za kreiranje korisničkog računa.');
        }

        if (User::query()->where('email', $email)->exists()) {
            throw new RuntimeException('Korisnik s ovim e-mailom već postoji. Prijavite se ili nastavite kao gost.');
        }

        $user = User::createAccount([
            'name' => trim("{$data['first_name']} {$data['last_name']}"),
            'email' => $email,
            'password' => Hash::make((string) $data['password']),
            'phone' => $data['phone'] ?? null,
            'is_customer' => true,
            'is_b2b_customer' => false,
        ]);

        Customer::query()->create([
            'user_id' => $user->id,
            'phone' => $data['phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'jib' => $data['jib'] ?? null,
        ]);

        return $user;
    }

    private function resolveCoupon(Cart $cart): ?Coupon
    {
        if (! $cart->coupon_code) {
            return null;
        }

        return Coupon::query()->where('code', $cart->coupon_code)->first();
    }

    private function generateOrderNumber(): string
    {
        return 'BNC-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    private function sendOrderEmails(Order $order): void
    {
        if ($order->email) {
            Mail::to($order->email)->queue(new OrderConfirmationCustomer($order));
        }

        $sellerEmail = config('bnc.seller_notification_email');
        if ($sellerEmail) {
            Mail::to($sellerEmail)->queue(new TemplatedOrderMail(
                templateSlug: 'order_notification_seller',
                order: $order,
            ));
        }
    }

    private function syncMarketingContact(Order $order): void
    {
        try {
            $contact = $this->marketingContactSyncService->syncFromOrder($order);

            if ($contact === null) {
                return;
            }

            $settings = $this->brevoSettings->all();

            if (($settings['sync_on_order'] ?? false) && $this->brevoService->isConfigured()) {
                $this->brevoService->syncContact($contact);
            }
        } catch (\Throwable) {
            // Marketing sync must not block checkout.
        }
    }
}
