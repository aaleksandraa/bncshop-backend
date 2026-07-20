<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiImportJob extends Model
{
    protected $fillable = [
        'api_source_id',
        'type',
        'status',
        'sync_started_at',
        'started_at',
        'completed_at',
        'stats',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'sync_started_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'stats' => 'array',
        ];
    }

    public function apiSource(): BelongsTo
    {
        return $this->belongsTo(ApiSource::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ApiImportJobItem::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(ApiImportJobChange::class);
    }
}
