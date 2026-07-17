<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OlxCategoryMapping extends Model
{
    protected $fillable = [
        'category_id',
        'olx_category_id',
        'olx_category_path',
        'is_enabled',
        'include_descendants',
    ];

    protected function casts(): array
    {
        return [
            'olx_category_id' => 'integer',
            'is_enabled' => 'boolean',
            'include_descendants' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function olxCategory(): BelongsTo
    {
        return $this->belongsTo(OlxCategory::class, 'olx_category_id');
    }
}
