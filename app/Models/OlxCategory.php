<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OlxCategory extends Model
{
    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'name',
        'slug',
        'parent_id',
        'path',
        'brand_required',
        'show_condition',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'brand_required' => 'boolean',
            'show_condition' => 'boolean',
            'fetched_at' => 'datetime',
        ];
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(OlxCategoryAttribute::class, 'olx_category_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(OlxCategoryMapping::class, 'olx_category_id');
    }
}
