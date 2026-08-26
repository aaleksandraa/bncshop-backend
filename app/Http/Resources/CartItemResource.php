<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CartItem */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cart_id' => $this->cart_id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'is_loyalty_reward' => (bool) $this->is_loyalty_reward,
            'is_gratis_gift' => (bool) $this->is_gratis_gift,
            'parent_item_id' => $this->parent_cart_item_id,
            'gratis_label' => $this->when(
                $this->is_gratis_gift,
                fn () => is_array($this->discount_snapshot)
                    ? ($this->discount_snapshot['gratis_label'] ?? 'Gratis')
                    : 'Gratis',
            ),
            'product' => $this->whenLoaded('product', fn () => $this->product
                ? (new ProductCardResource($this->product))->resolve()
                : null),
        ];
    }
}
