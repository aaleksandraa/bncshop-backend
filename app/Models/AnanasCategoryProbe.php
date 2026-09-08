<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnanasCategoryProbe extends Model
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_PENDING = 'pending';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'category_mapping_id',
        'product_id',
        'product_type',
        'category_candidate',
        'progress_id',
        'status',
        'observed_categories',
        'observed_product_type',
        'remote_product_id',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'observed_categories' => 'array',
        ];
    }

    public function categoryMapping(): BelongsTo
    {
        return $this->belongsTo(AnanasCategoryMapping::class, 'category_mapping_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
