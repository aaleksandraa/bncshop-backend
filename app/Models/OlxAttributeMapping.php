<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OlxAttributeMapping extends Model
{
    protected $fillable = [
        'olx_category_id',
        'olx_attribute_id',
        'attribute_definition_id',
        'bnc_attribute_aliases',
        'parser_pattern',
        'default_value',
        'value_mappings',
        'is_required_for_publish',
    ];

    protected function casts(): array
    {
        return [
            'olx_category_id' => 'integer',
            'olx_attribute_id' => 'integer',
            'bnc_attribute_aliases' => 'array',
            'value_mappings' => 'array',
            'is_required_for_publish' => 'boolean',
        ];
    }

    public function attributeDefinition(): BelongsTo
    {
        return $this->belongsTo(AttributeDefinition::class);
    }

    public function olxCategory(): BelongsTo
    {
        return $this->belongsTo(OlxCategory::class, 'olx_category_id');
    }
}
