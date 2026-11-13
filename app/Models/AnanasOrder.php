<?php

namespace App\Models;

use App\Support\Ananas\AnanasOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnanasOrder extends Model
{
    protected $fillable = [
        'ananas_order_id',
        'remote_environment',
        'suborder_id',
        'ananas_created_at',
        'total_price',
        'currency',
        'payment_methods',
        'billing_address',
        'remote_status',
        'status_bucket',
        'is_terminal',
        'notified_at',
        'confirmed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'ananas_created_at' => 'datetime',
            'total_price' => 'decimal:2',
            'payment_methods' => 'array',
            'billing_address' => 'array',
            'is_terminal' => 'boolean',
            'notified_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AnanasOrderItem::class);
    }

    public function customerName(): string
    {
        $address = $this->billing_address ?? [];
        $first = trim((string) ($address['firstName'] ?? ''));
        $last = trim((string) ($address['lastName'] ?? ''));
        $name = trim("{$first} {$last}");

        if ($name !== '') {
            return $name;
        }

        return trim((string) ($address['buyerName'] ?? '')) ?: '—';
    }

    public function customerCity(): string
    {
        return trim((string) ($this->billing_address['city'] ?? '')) ?: '—';
    }

    public function statusLabel(): string
    {
        return AnanasOrderStatus::bucketLabel((string) $this->status_bucket);
    }

    public function paymentLabel(): string
    {
        $methods = is_array($this->payment_methods) ? $this->payment_methods : [];

        return AnanasOrderStatus::paymentLabel(array_map(strval(...), $methods));
    }

    public function canConfirm(): bool
    {
        if ($this->is_terminal) {
            return false;
        }

        if ($this->confirmed_at !== null) {
            return false;
        }

        return $this->status_bucket === AnanasOrderStatus::BUCKET_AWAITING
            || strtoupper((string) $this->remote_status) === 'CREATED';
    }
}
