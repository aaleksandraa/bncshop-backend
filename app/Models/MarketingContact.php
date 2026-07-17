<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingContact extends Model
{
    public const TYPE_REGISTERED = 'registered';

    public const TYPE_GUEST = 'guest';

    protected $fillable = [
        'email',
        'type',
        'customer_id',
        'name',
        'phone',
        'company_name',
        'orders_count',
        'orders_total',
        'last_order_at',
        'registered_at',
        'brevo_contact_id',
        'brevo_synced_at',
        'marketing_opt_in',
    ];

    protected function casts(): array
    {
        return [
            'orders_count' => 'integer',
            'orders_total' => 'decimal:2',
            'last_order_at' => 'datetime',
            'registered_at' => 'datetime',
            'brevo_synced_at' => 'datetime',
            'marketing_opt_in' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isRegistered(): bool
    {
        return $this->type === self::TYPE_REGISTERED;
    }

    public function isGuest(): bool
    {
        return $this->type === self::TYPE_GUEST;
    }

    public function isSyncedWithBrevo(): bool
    {
        return $this->brevo_synced_at !== null;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_REGISTERED => 'Registrovan',
            self::TYPE_GUEST => 'Gost',
            default => $this->type,
        };
    }

    /**
     * @param  Builder<MarketingContact>  $query
     * @return Builder<MarketingContact>
     */
    public function scopeRegistered(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_REGISTERED);
    }

    /**
     * @param  Builder<MarketingContact>  $query
     * @return Builder<MarketingContact>
     */
    public function scopeGuest(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_GUEST);
    }
}
