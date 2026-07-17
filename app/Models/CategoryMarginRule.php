<?php

namespace App\Models;

use App\Models\Concerns\HasMarginCategoryScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryMarginRule extends Model
{
    use HasMarginCategoryScope;

    protected $fillable = [
        'category_id',
        'margin_percentage',
        'subcategory_scope',
        'include_parent_category',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'margin_percentage' => 'decimal:2',
            'include_parent_category' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected function marginTargetPivotTable(): string
    {
        return 'category_margin_rule_targets';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
