<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bProductAttributeValue extends Model
{
    protected $fillable = [
        'b2b_product_id',
        'b2b_attribute_definition_id',
        'value',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(B2bProduct::class, 'b2b_product_id');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(B2bAttributeDefinition::class, 'b2b_attribute_definition_id');
    }
}
