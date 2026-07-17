<?php

namespace App\Http\Resources;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Cart */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'coupon_code' => $this->coupon_code,
            'pending_coupon_code' => $this->pending_coupon_code,
            'loyalty_reward_id' => $this->loyalty_reward_id,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'loyalty_reward' => $this->whenLoaded('loyaltyReward', fn () => $this->loyaltyReward ? [
                'id' => $this->loyaltyReward->id,
                'name' => $this->loyaltyReward->name,
                'description' => $this->loyaltyReward->description,
                'type' => $this->loyaltyReward->type,
                'points_required' => $this->loyaltyReward->points_required,
                'reward_value' => $this->loyaltyReward->reward_value,
                'product' => $this->loyaltyReward->relationLoaded('product') && $this->loyaltyReward->product
                    ? (new ProductCardResource($this->loyaltyReward->product))->resolve()
                    : null,
            ] : null),
        ];
    }
}
