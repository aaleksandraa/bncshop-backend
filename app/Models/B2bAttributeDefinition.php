<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2bAttributeDefinition extends Model
{
    public const INPUT_SELECT = 'select';

    public const INPUT_MULTISELECT = 'multiselect';

    public const INPUT_TEXT = 'text';

    protected $fillable = [
        'name',
        'slug',
        'input_type',
        'is_filterable',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_filterable' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(B2bAttributeOption::class, 'b2b_attribute_definition_id')
            ->orderBy('sort_order')
            ->orderBy('value');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            B2bCategory::class,
            'b2b_category_attribute',
            'b2b_attribute_definition_id',
            'b2b_category_id',
        )->withPivot('sort_order')->withTimestamps(false);
    }

    public function productValues(): HasMany
    {
        return $this->hasMany(B2bProductAttributeValue::class, 'b2b_attribute_definition_id');
    }

    public function isMultiselect(): bool
    {
        return $this->input_type === self::INPUT_MULTISELECT;
    }

    public function isText(): bool
    {
        return $this->input_type === self::INPUT_TEXT;
    }
}
