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

    public function isOlxExportJob(): bool
    {
        return in_array($this->type, ['olx_incremental', 'olx_full', 'olx_stock'], true);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'olx_stock' => 'OLX zaliha',
            'olx_incremental' => 'OLX incremental',
            'olx_full' => 'OLX full',
            'incremental' => 'Incremental',
            'full' => 'Full',
            default => (string) $this->type,
        };
    }

    public function summaryCreated(): ?int
    {
        if ($this->isOlxExportJob()) {
            return (int) data_get($this->stats, 'actions.created', 0);
        }

        return $this->nullableStatInt('products.created');
    }

    public function summaryUpdated(): ?int
    {
        if ($this->isOlxExportJob()) {
            return (int) data_get($this->stats, 'actions.updated', 0)
                + (int) data_get($this->stats, 'actions.unhidden', 0);
        }

        return $this->nullableStatInt('products.updated');
    }

    public function summaryDeactivated(): ?int
    {
        if ($this->isOlxExportJob()) {
            return (int) data_get($this->stats, 'actions.hidden', 0)
                + (int) data_get($this->stats, 'actions.deleted', 0);
        }

        return $this->nullableStatInt('products.deactivated');
    }

    private function nullableStatInt(string $path): ?int
    {
        $value = data_get($this->stats, $path);

        return is_numeric($value) ? (int) $value : null;
    }
}
