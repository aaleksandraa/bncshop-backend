<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OlxCategoryAttribute extends Model
{
    protected $fillable = [
        'olx_category_id',
        'olx_attribute_id',
        'name',
        'display_name',
        'input_type',
        'required',
        'options_json',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'olx_category_id' => 'integer',
            'olx_attribute_id' => 'integer',
            'required' => 'boolean',
            'options_json' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(OlxCategory::class, 'olx_category_id');
    }
}
