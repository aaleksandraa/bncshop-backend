<?php

namespace App\Services\B2b;

use App\Models\B2bOrder;

class B2bOrderNumberGenerator
{
    public function generate(): string
    {
        $year = now()->format('Y');

        $lastOrder = B2bOrder::query()
            ->where('order_number', 'like', "B2B-{$year}-%")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $sequence = 1;

        if ($lastOrder) {
            $parts = explode('-', $lastOrder->order_number);
            $sequence = ((int) end($parts)) + 1;
        }

        return sprintf('B2B-%s-%05d', $year, $sequence);
    }
}
