<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElineCategoryMapping extends Model
{
    public const CONDITION_REFURBISHED = 'refurbished';

    public const CONDITION_NEW = 'new';

    protected $fillable = [
        'eline_category_id',
        'category_id',
        'is_enabled',
        'product_condition',
        'margin_percentage',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'margin_percentage' => 'decimal:2',
        ];
    }

    public function elineCategory(): BelongsTo
    {
        return $this->belongsTo(ElineCategory::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
