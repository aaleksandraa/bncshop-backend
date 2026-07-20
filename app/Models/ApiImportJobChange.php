<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiImportJobChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'api_import_job_id',
        'product_id',
        'external_product_id',
        'product_name',
        'action',
        'changed_fields',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'changed_fields' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ApiImportJob::class, 'api_import_job_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
