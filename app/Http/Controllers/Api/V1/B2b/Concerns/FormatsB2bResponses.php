<?php

namespace App\Http\Controllers\Api\V1\B2b\Concerns;

use App\Models\B2bCart;
use App\Models\B2bCategory;
use App\Models\B2bCustomer;
use App\Models\B2bOrder;
use App\Models\B2bProduct;
use App\Services\B2b\B2bPricingService;
use App\Services\B2b\B2bProductAttributeService;
use App\Support\B2bOrderStatus;
use App\Support\B2bPaymentMethod;
use App\Support\SafeHtml;
use Illuminate\Http\Request;

trait FormatsB2bResponses
{
    private ?B2bPricingService $pricingServiceInstance = null;

    protected function pricingService(): B2bPricingService
    {
        return $this->pricingServiceInstance ??= app(B2bPricingService::class);
    }

    protected function b2bCustomer(Request $request): B2bCustomer
    {
        /** @var B2bCustomer $customer */
        $customer = $request->attributes->get('b2b_customer');

        return $customer;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatCustomer(B2bCustomer $customer): array
    {
        $user = $customer->user;

        return [
            'id' => $customer->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $customer->phone,
            'company_name' => $customer->company_name,
            'company_address' => $customer->company_address,
            'jib' => $customer->jib,
            'pdv_number' => $customer->pdv_number,
            'discount_percent' => $customer->effectiveDiscountPercent(),
            'is_active' => $customer->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatCategory(B2bCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'product_count' => $category->products_count ?? $category->products()->where('is_active', true)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $pricing
     * @return array<string, mixed>
     */
    protected function formatProductList(B2bProduct $product, ?B2bCustomer $customer = null, ?array $pricing = null): array
    {
        $pricing ??= $this->pricingService()->calculate($product, $customer);
        $primaryImage = $product->primaryImage();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'category' => $product->relationLoaded('category') && $product->category
                ? ['id' => $product->category->id, 'name' => $product->category->name, 'slug' => $product->category->slug]
                : null,
            'regular_price' => $pricing['regular_price'],
            'final_price' => $pricing['final_price'],
            'has_sale' => $pricing['has_sale'],
            'badge_text' => $pricing['badge_text'],
            'customer_discount_percent' => $pricing['customer_discount_percent'],
            'exclude_customer_discount' => $pricing['exclude_customer_discount'],
            'stock_quantity' => $product->stock_quantity,
            'in_stock' => $product->isInStock(),
            'image_url' => $primaryImage?->url(),
            'attributes' => $this->formatProductAttributes($product),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $pricing
     * @return array<string, mixed>
     */
    protected function formatProduct(B2bProduct $product, ?B2bCustomer $customer = null, ?array $pricing = null): array
    {
        $pricing ??= $this->pricingService()->calculate($product, $customer);
        $primaryImage = $product->primaryImage();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => SafeHtml::clean($product->description),
            'sku' => $product->sku,
            'category' => $product->relationLoaded('category') && $product->category
                ? ['id' => $product->category->id, 'name' => $product->category->name, 'slug' => $product->category->slug]
                : null,
            'regular_price' => $pricing['regular_price'],
            'final_price' => $pricing['final_price'],
            'has_sale' => $pricing['has_sale'],
            'badge_text' => $pricing['badge_text'],
            'customer_discount_percent' => $pricing['customer_discount_percent'],
            'exclude_customer_discount' => $pricing['exclude_customer_discount'],
            'stock_quantity' => $product->stock_quantity,
            'in_stock' => $product->isInStock(),
            'image_url' => $primaryImage?->url(),
            'images' => $product->relationLoaded('images')
                ? $product->images->map(fn ($image) => [
                    'id' => $image->id,
                    'url' => $image->url(),
                    'is_primary' => $image->is_primary,
                ])->values()->all()
                : [],
            'attributes' => $this->formatProductAttributes($product),
        ];
    }

    /**
     * @return array<int, array{slug: string, name: string, values: array<int, string>}>
     */
    protected function formatProductAttributes(B2bProduct $product): array
    {
        return app(B2bProductAttributeService::class)->formatForProduct($product);
    }

    /**
     * Lightweight product payload for cart lines (no description / full gallery).
     *
     * @param  array<string, mixed>|null  $pricing
     * @return array<string, mixed>
     */
    protected function formatCartProduct(B2bProduct $product, ?B2bCustomer $customer = null, ?array $pricing = null): array
    {
        return $this->formatProductList($product, $customer, $pricing);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatCart(B2bCart $cart, B2bCustomer $customer): array
    {
        $items = [];
        $subtotal = 0.0;
        $total = 0.0;

        foreach ($cart->items as $item) {
            $product = $item->product;
            $pricing = $this->pricingService()->calculate($product, $customer);
            $lineTotal = round($pricing['final_price'] * $item->quantity, 2);

            $subtotal += round($pricing['regular_price'] * $item->quantity, 2);
            $total += $lineTotal;

            $items[] = [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'line_total' => $lineTotal,
                'product' => $this->formatCartProduct($product, $customer, $pricing),
            ];
        }

        return [
            'id' => $cart->id,
            'items' => $items,
            'item_count' => collect($items)->sum('quantity'),
            'subtotal' => round($subtotal, 2),
            'discount_total' => round(max(0, $subtotal - $total), 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Order list summary — no line items (use show for full detail).
     *
     * @return array<string, mixed>
     */
    protected function formatOrderSummary(B2bOrder $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_label' => B2bOrderStatus::label($order->status),
            'payment_method' => $order->payment_method,
            'payment_method_label' => B2bPaymentMethod::label($order->payment_method),
            'company_name' => $order->company_name,
            'subtotal' => (float) $order->subtotal,
            'discount_total' => (float) $order->discount_total,
            'shipping_fee' => (float) $order->shipping_fee,
            'total' => (float) $order->total,
            'items_count' => (int) ($order->items_count ?? 0),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatOrder(B2bOrder $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_label' => B2bOrderStatus::label($order->status),
            'payment_method' => $order->payment_method,
            'payment_method_label' => B2bPaymentMethod::label($order->payment_method),
            'company_name' => $order->company_name,
            'company_address' => $order->company_address,
            'jib' => $order->jib,
            'pdv_number' => $order->pdv_number,
            'contact_name' => $order->contact_name,
            'contact_email' => $order->contact_email,
            'contact_phone' => $order->contact_phone,
            'shipping_address' => $order->shipping_address,
            'notes' => $order->notes,
            'subtotal' => (float) $order->subtotal,
            'discount_total' => (float) $order->discount_total,
            'shipping_fee' => (float) $order->shipping_fee,
            'total' => (float) $order->total,
            'created_at' => $order->created_at?->toIso8601String(),
            'items' => $order->relationLoaded('items')
                ? $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'quantity' => $item->quantity,
                    'unit_regular_price' => (float) $item->unit_regular_price,
                    'unit_final_price' => (float) $item->unit_final_price,
                    'line_total' => (float) $item->line_total,
                    'customer_discount_percent' => (float) $item->customer_discount_percent,
                ])->values()->all()
                : [],
        ];
    }
}
