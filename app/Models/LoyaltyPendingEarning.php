<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyPendingEarning extends Model
{
    protected $fillable = [
        'email',
        'order_id',
        'points',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
