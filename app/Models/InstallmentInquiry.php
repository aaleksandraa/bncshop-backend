<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class InstallmentInquiry extends Model
{
    public const STATUS_CANCELLED = 'otkazano';

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
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
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
            'cancelled_at' => 'datetime',
        ];
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function applyStatus(string $status, ?string $reason = null, ?User $actor = null): void
    {
        if (! array_key_exists($status, self::statusOptions())) {
            throw ValidationException::withMessages([
                'status' => ['Odabrani status nije dozvoljen.'],
            ]);
        }

        $reason = $reason !== null ? trim($reason) : null;

        if ($reason === '') {
            $reason = null;
        }

        if ($status === self::STATUS_CANCELLED) {
            if ($reason === null || mb_strlen($reason) < 3) {
                throw ValidationException::withMessages([
                    'cancellation_reason' => ['Razlog otkazivanja mora imati najmanje 3 znaka.'],
                ]);
            }

            if (mb_strlen($reason) > 1000) {
                throw ValidationException::withMessages([
                    'cancellation_reason' => ['Razlog otkazivanja može imati najviše 1000 znakova.'],
                ]);
            }

            $this->fill([
                'status' => $status,
                'cancellation_reason' => $reason,
                'cancelled_at' => $this->status === self::STATUS_CANCELLED && $this->cancelled_at !== null
                    ? $this->cancelled_at
                    : now(),
                'cancelled_by' => $actor?->id ?? $this->cancelled_by,
            ])->save();

            return;
        }

        $this->fill([
            'status' => $status,
            'cancellation_reason' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
        ])->save();
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
            self::STATUS_CANCELLED => 'Otkazano',
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
