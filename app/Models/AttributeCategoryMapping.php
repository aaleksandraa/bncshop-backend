<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeCategoryMapping extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'attribute_definition_id',
        'category_id',
        'external_category_id',
        'category_name',
        'is_filter_enabled',
        'is_public_enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'external_category_id' => 'string',
            'is_filter_enabled' => 'boolean',
            'is_public_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function attributeDefinition(): BelongsTo
    {
        return $this->belongsTo(AttributeDefinition::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
