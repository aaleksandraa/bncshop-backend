<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttributeDefinition extends Model
{
    protected $fillable = [
        'external_attribute_id',
        'name',
        'display_name',
        'display_unit',
        'external_id',
        'olx_id',
        'olx_name',
        'api_type',
        'internal_type',
        'is_public',
        'is_public_locked',
        'is_filter',
        'detail_sort_order',
        'is_mapped',
        'olx_required',
        'options_json',
        'parsed_options',
        'value_mappings',
        'canonical_attribute_definition_id',
    ];

    protected function casts(): array
    {
        return [
            'external_attribute_id' => 'string',
            'olx_id' => 'integer',
            'api_type' => 'integer',
            'is_public' => 'boolean',
            'is_public_locked' => 'boolean',
            'is_filter' => 'boolean',
            'is_mapped' => 'boolean',
            'detail_sort_order' => 'integer',
            'olx_required' => 'boolean',
            'options_json' => 'array',
            'parsed_options' => 'array',
            'value_mappings' => 'array',
            'canonical_attribute_definition_id' => 'integer',
        ];
    }

    public function categoryMappings(): HasMany
    {
        return $this->hasMany(AttributeCategoryMapping::class);
    }

    public function productValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function canonicalDefinition(): BelongsTo
    {
        return $this->belongsTo(self::class, 'canonical_attribute_definition_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(self::class, 'canonical_attribute_definition_id');
    }

    public function isAlias(): bool
    {
        return $this->canonical_attribute_definition_id !== null;
    }

    public function resolveCanonical(): self
    {
        $current = $this;

        for ($depth = 0; $depth < 10; $depth++) {
            if ($current->canonical_attribute_definition_id === null) {
                return $current;
            }

            $current->loadMissing('canonicalDefinition');

            if ($current->canonicalDefinition === null) {
                return $current;
            }

            $current = $current->canonicalDefinition;
        }

        return $current;
    }

    public function resolveCanonicalId(): int
    {
        return $this->resolveCanonical()->id;
    }

    /**
     * @return array<int, int>
     */
    public static function expandedDefinitionIds(int ...$definitionIds): array
    {
        $definitionIds = array_values(array_unique(array_filter($definitionIds)));

        if ($definitionIds === []) {
            return [];
        }

        $aliasIds = self::query()
            ->whereIn('canonical_attribute_definition_id', $definitionIds)
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_merge($definitionIds, $aliasIds)));
    }

    public function scopeCanonical(Builder $query): Builder
    {
        return $query->whereNull('canonical_attribute_definition_id');
    }

    public function publicLabel(): string
    {
        if (filled($this->display_name)) {
            return (string) $this->display_name;
        }

        return (string) $this->name;
    }
}
