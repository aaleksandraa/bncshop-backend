<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnanasCategoryMapping extends Model
{
    protected $fillable = [
        'category_id',
        'ananas_product_type',
        'ananas_category',
        'is_enabled',
        'include_descendants',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'include_descendants' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
