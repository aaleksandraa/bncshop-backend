<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bAttributeOption extends Model
{
    protected $fillable = [
        'b2b_attribute_definition_id',
        'value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(B2bAttributeDefinition::class, 'b2b_attribute_definition_id');
    }
}
