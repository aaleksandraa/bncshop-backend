<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyCard extends Model
{
    protected $fillable = [
        'customer_id',
        'card_number',
        'status',
        'issued_at',
        'issued_by',
        'blocked_at',
        'block_reason',
        'replaced_by_card_id',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'blocked_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function issuedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function replacedByCard(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_card_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
