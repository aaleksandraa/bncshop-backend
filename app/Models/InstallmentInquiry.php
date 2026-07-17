<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentInquiry extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'product_id',
        'product_name',
        'product_slug',
        'quantity',
        'base_price',
        'installment_type',
        'months',
        'monthly_amount',
        'total_amount',
        'interest_rate',
        'provision_rate',
        'calculation_snapshot',
        'status',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'base_price' => 'decimal:2',
            'months' => 'integer',
            'monthly_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'provision_rate' => 'decimal:4',
            'calculation_snapshot' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'nova' => 'Nova',
            'kontaktirana' => 'Kontaktirana',
            'zatvorena' => 'Zatvorena',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            'mikrofin' => 'Mikrofin',
            'shopping_card' => 'Shopping kartica',
        ];
    }
}
