<?php

namespace App\Services\B2b;

use App\Models\B2bCart;
use App\Models\B2bCustomer;
use App\Models\B2bOrder;
use App\Models\B2bOrderItem;
use App\Models\B2bOrderStatusHistory;
use App\Models\B2bProduct;
use App\Support\B2bOrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class B2bCheckoutService
{
    public function __construct(
        private readonly B2bPricingService $pricingService,
        private readonly B2bOrderNumberGenerator $orderNumberGenerator,
        private readonly B2bShippingCalculator $shippingCalculator,
        private readonly B2bOrderInvoicePdf $invoicePdf,
        private readonly B2bAccessMailer $mailer,
    ) {}

    /**
     * @param  array{shipping_address: string, notes?: string|null}  $data
     */
    public function checkout(B2bCustomer $customer, array $data): B2bOrder
    {
        $order = DB::transaction(function () use ($customer, $data): B2bOrder {
            $cart = B2bCart::query()
                ->with(['items.product.campaigns', 'items.product.images'])
                ->where('b2b_customer_id', $customer->id)
                ->lockForUpdate()
                ->first();

            if (! $cart || $cart->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => ['Korpa je prazna.'],
                ]);
            }

            $subtotal = 0.0;
            $discountTotal = 0.0;
            $orderItems = [];

            $productIds = $cart->items->pluck('b2b_product_id')->unique()->values()->all();
            $lockedProducts = B2bProduct::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($cart->items as $item) {
                /** @var B2bProduct|null $product */
                $product = $lockedProducts->get($item->b2b_product_id);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'cart' => ['Jedan od proizvoda u korpi više nije dostupan.'],
                    ]);
                }

                if (! $product->is_active) {
                    throw ValidationException::withMessages([
                        'cart' => ["Proizvod \"{$product->name}\" više nije dostupan."],
                    ]);
                }

                if ($product->stock_quantity < $item->quantity) {
                    throw ValidationException::withMessages([
                        'cart' => ["Nedovoljna zaliha za proizvod \"{$product->name}\"."],
                    ]);
                }

                $pricing = $this->pricingService->calculate($product, $customer);
                $lineTotal = round($pricing['final_price'] * $item->quantity, 2);
                $regularLineTotal = round($pricing['regular_price'] * $item->quantity, 2);

                $subtotal += $regularLineTotal;
                $discountTotal += max(0, $regularLineTotal - $lineTotal);

                $orderItems[] = [
                    'product' => $product,
                    'quantity' => $item->quantity,
                    'pricing' => $pricing,
                    'line_total' => $lineTotal,
                ];
            }

            $itemsTotal = round($subtotal - $discountTotal, 2);
            $shipping = $this->shippingCalculator->calculate($itemsTotal);
            $shippingFee = $shipping['fee'];

            $user = $customer->user;

            $order = B2bOrder::query()->create([
                'order_number' => $this->orderNumberGenerator->generate(),
                'b2b_customer_id' => $customer->id,
                'status' => B2bOrderStatus::NOVA,
                'payment_method' => 'invoice',
                'company_name' => $customer->company_name,
                'company_address' => $customer->company_address,
                'jib' => $customer->jib,
                'pdv_number' => $customer->pdv_number,
                'contact_name' => $user->name,
                'contact_email' => $user->email,
                'contact_phone' => $customer->phone,
                'shipping_address' => $data['shipping_address'],
                'notes' => $data['notes'] ?? null,
                'subtotal' => round($subtotal, 2),
                'discount_total' => round($discountTotal, 2),
                'shipping_fee' => $shippingFee,
                'total' => round($itemsTotal + $shippingFee, 2),
            ]);

            foreach ($orderItems as $itemData) {
                /** @var B2bProduct $product */
                $product = $itemData['product'];

                B2bOrderItem::query()->create([
                    'b2b_order_id' => $order->id,
                    'b2b_product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'quantity' => $itemData['quantity'],
                    'unit_regular_price' => $itemData['pricing']['regular_price'],
                    'unit_final_price' => $itemData['pricing']['final_price'],
                    'line_total' => $itemData['line_total'],
                    'customer_discount_percent' => $itemData['pricing']['customer_discount_percent'],
                ]);

                $product->decrement('stock_quantity', $itemData['quantity']);
            }

            B2bOrderStatusHistory::query()->create([
                'b2b_order_id' => $order->id,
                'from_status' => null,
                'to_status' => B2bOrderStatus::NOVA,
                'changed_by' => $user->id,
                'note' => 'Narudžba kreirana.',
            ]);

            $cart->items()->delete();

            $order->load('items');

            $this->invoicePdf->generateAndStore($order);

            return $order->fresh(['items']);
        });

        $this->mailer->sendOrderConfirmationCustomer($order);
        $this->mailer->sendOrderNotificationAdmin($order);

        return $order;
    }
}
