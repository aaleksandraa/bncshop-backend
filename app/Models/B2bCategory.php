<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2bCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(B2bProduct::class, 'b2b_category_id');
    }

    public function attributeDefinitions(): BelongsToMany
    {
        return $this->belongsToMany(
            B2bAttributeDefinition::class,
            'b2b_category_attribute',
            'b2b_category_id',
            'b2b_attribute_definition_id',
        )
            ->withPivot('sort_order')
            ->orderBy('b2b_category_attribute.sort_order')
            ->orderBy('b2b_attribute_definitions.sort_order')
            ->orderBy('b2b_attribute_definitions.name');
    }
}
