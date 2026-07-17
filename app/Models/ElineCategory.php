<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ElineCategory extends Model
{
    protected $fillable = [
        'name',
        'product_count',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'product_count' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    public function mapping(): HasOne
    {
        return $this->hasOne(ElineCategoryMapping::class);
    }
}
