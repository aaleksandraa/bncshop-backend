<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiImportJobItem extends Model
{
    protected $fillable = [
        'api_import_job_id',
        'page',
        'records_count',
        'duration_ms',
        'errors',
    ];

    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'records_count' => 'integer',
            'duration_ms' => 'integer',
            'errors' => 'array',
        ];
    }

    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ApiImportJob::class, 'api_import_job_id');
    }
}
