<?php

namespace App\Services\B2b;

use App\Models\B2bCart;
use App\Models\B2bCartItem;
use App\Models\B2bCustomer;
use App\Models\B2bProduct;
use Illuminate\Validation\ValidationException;

class B2bCartService
{
    public function getOrCreateCart(B2bCustomer $customer): B2bCart
    {
        return B2bCart::query()->firstOrCreate([
            'b2b_customer_id' => $customer->id,
        ]);
    }

    public function addItem(B2bCustomer $customer, B2bProduct $product, int $quantity): B2bCart
    {
        $this->assertProductAvailable($product, $quantity);

        $cart = $this->getOrCreateCart($customer);

        $item = B2bCartItem::query()->firstOrNew([
            'b2b_cart_id' => $cart->id,
            'b2b_product_id' => $product->id,
        ]);

        $newQuantity = ($item->exists ? $item->quantity : 0) + $quantity;

        if ($product->stock_quantity < $newQuantity) {
            throw ValidationException::withMessages([
                'quantity' => ['Nedovoljna zaliha.'],
            ]);
        }

        $item->quantity = $newQuantity;
        $item->save();

        return $this->loadCart($cart);
    }

    public function updateItem(B2bCustomer $customer, int $itemId, int $quantity): B2bCart
    {
        $cart = $this->getOrCreateCart($customer);

        $item = B2bCartItem::query()
            ->where('b2b_cart_id', $cart->id)
            ->whereKey($itemId)
            ->firstOrFail();

        if ($quantity <= 0) {
            $item->delete();

            return $this->loadCart($cart);
        }

        $product = $item->product;

        if ($product->stock_quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['Nedovoljna zaliha.'],
            ]);
        }

        $item->update(['quantity' => $quantity]);

        return $this->loadCart($cart);
    }

    public function removeItem(B2bCustomer $customer, int $itemId): B2bCart
    {
        $cart = $this->getOrCreateCart($customer);

        B2bCartItem::query()
            ->where('b2b_cart_id', $cart->id)
            ->whereKey($itemId)
            ->delete();

        return $this->loadCart($cart);
    }

    public function loadCart(B2bCart $cart): B2bCart
    {
        return $cart->load(['items.product.images', 'items.product.campaigns', 'items.product.category']);
    }

    private function assertProductAvailable(B2bProduct $product, int $quantity): void
    {
        if (! $product->is_active) {
            throw ValidationException::withMessages([
                'product' => ['Proizvod nije dostupan.'],
            ]);
        }

        if ($product->stock_quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['Nedovoljna zaliha.'],
            ]);
        }
    }
}
