<?php

namespace App\Services\B2b;

use App\Models\B2bOrder;
use App\Models\B2bProduct;
use App\Models\B2bStockMovement;
use App\Models\User;

class B2bStockLedger
{
    public function recordAdminAdjustment(B2bProduct $product, int $quantityBefore, ?User $admin): ?B2bStockMovement
    {
        $quantityAfter = (int) $product->stock_quantity;
        $delta = $quantityAfter - $quantityBefore;

        if ($delta === 0) {
            return null;
        }

        $summary = $delta > 0 ? 'Admin dodao' : 'Admin smanjio';

        if (filled($admin?->name)) {
            $summary .= ' — '.$admin->name;
        }

        return $this->write(
            product: $product,
            delta: $delta,
            quantityBefore: $quantityBefore,
            quantityAfter: $quantityAfter,
            type: B2bStockMovement::TYPE_ADMIN,
            summary: $summary,
            userId: $admin?->id,
        );
    }

    public function recordOrderDeduction(B2bProduct $product, int $quantityBefore, B2bOrder $order): ?B2bStockMovement
    {
        $quantityAfter = (int) $product->stock_quantity;
        $company = $order->company_name ?: 'B2B kupac';

        return $this->write(
            product: $product,
            delta: $quantityAfter - $quantityBefore,
            quantityBefore: $quantityBefore,
            quantityAfter: $quantityAfter,
            type: B2bStockMovement::TYPE_ORDER,
            summary: "Narudžba {$order->order_number} — {$company}",
            orderId: $order->id,
            customerId: $order->b2b_customer_id,
        );
    }

    public function recordOrderRestoration(B2bProduct $product, int $quantityBefore, B2bOrder $order): ?B2bStockMovement
    {
        $quantityAfter = (int) $product->stock_quantity;
        $company = $order->company_name ?: 'B2B kupac';

        return $this->write(
            product: $product,
            delta: $quantityAfter - $quantityBefore,
            quantityBefore: $quantityBefore,
            quantityAfter: $quantityAfter,
            type: B2bStockMovement::TYPE_ORDER_CANCELLED,
            summary: "Otkazana narudžba {$order->order_number} — {$company}",
            orderId: $order->id,
            customerId: $order->b2b_customer_id,
        );
    }

    private function write(
        B2bProduct $product,
        int $delta,
        int $quantityBefore,
        int $quantityAfter,
        string $type,
        string $summary,
        ?int $orderId = null,
        ?int $customerId = null,
        ?int $userId = null,
    ): ?B2bStockMovement {
        if ($delta === 0) {
            return null;
        }

        return B2bStockMovement::query()->create([
            'b2b_product_id' => $product->id,
            'delta' => $delta,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'type' => $type,
            'summary' => $summary,
            'b2b_order_id' => $orderId,
            'b2b_customer_id' => $customerId,
            'user_id' => $userId,
        ]);
    }
}
