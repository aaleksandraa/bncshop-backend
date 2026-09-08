<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnanasCategoryMapping extends Model
{
    public const VALIDATION_UNKNOWN = 'unknown';

    public const VALIDATION_PENDING = 'pending';

    public const VALIDATION_VALIDATED = 'validated';

    public const VALIDATION_FAILED = 'failed';

    public const VALIDATION_SKIPPED = 'skipped';

    protected $fillable = [
        'category_id',
        'ananas_product_type',
        'ananas_category',
        'category_validation_status',
        'observed_categories',
        'category_validated_at',
        'category_validation_notes',
        'last_probe_product_id',
        'last_probe_progress_id',
        'is_enabled',
        'include_descendants',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'include_descendants' => 'boolean',
            'observed_categories' => 'array',
            'category_validated_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function lastProbeProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'last_probe_product_id');
    }

    public function probes(): HasMany
    {
        return $this->hasMany(AnanasCategoryProbe::class, 'category_mapping_id');
    }
}
