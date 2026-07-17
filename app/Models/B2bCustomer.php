<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class B2bCustomer extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'company_address',
        'jib',
        'pdv_number',
        'phone',
        'discount_percent',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cart(): HasOne
    {
        return $this->hasOne(B2bCart::class, 'b2b_customer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(B2bOrder::class, 'b2b_customer_id');
    }

    public function effectiveDiscountPercent(): float
    {
        if ($this->discount_percent !== null) {
            return (float) $this->discount_percent;
        }

        return (float) B2bSetting::instance()->default_customer_discount_percent;
    }
}
