<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'company_name',
        'jib',
        'loyalty_points_balance',
    ];

    protected function casts(): array
    {
        return [
            'loyalty_points_balance' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function loyaltyRedemptions(): HasMany
    {
        return $this->hasMany(LoyaltyRedemption::class);
    }

    public function loyaltyCards(): HasMany
    {
        return $this->hasMany(LoyaltyCard::class);
    }

    public function activeLoyaltyCard(): ?LoyaltyCard
    {
        return $this->loyaltyCards()->where('status', 'active')->first();
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeEligibleForLoyaltyCard(Builder $query): Builder
    {
        return $query
            ->whereHas('user', fn (Builder $userQuery): Builder => $userQuery
                ->whereNotNull('email')
                ->where('email', '!=', ''))
            ->whereDoesntHave('loyaltyCards', fn (Builder $cardQuery): Builder => $cardQuery
                ->where('status', 'active'));
    }
}
