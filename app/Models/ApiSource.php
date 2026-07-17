<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiSource extends Model
{
    protected $fillable = [
        'name',
        'target_system_code',
        'base_url',
        'username',
        'password',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'last_successful_sync_at',
        'page_size',
        'sync_interval_minutes',
        'auto_sync_enabled',
        'is_active',
        'connection_status',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'username' => 'encrypted',
            'password' => 'encrypted',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_successful_sync_at' => 'datetime',
            'page_size' => 'integer',
            'sync_interval_minutes' => 'integer',
            'auto_sync_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function importJobs(): HasMany
    {
        return $this->hasMany(ApiImportJob::class);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeA1Integration($query)
    {
        return $query->where('target_system_code', '!=', 'eline');
    }

    public function nextSyncAt(): ?\Illuminate\Support\Carbon
    {
        if ($this->last_successful_sync_at === null) {
            return null;
        }

        $intervalMinutes = max(1, (int) ($this->sync_interval_minutes ?? 60));

        return $this->last_successful_sync_at->copy()->addMinutes($intervalMinutes);
    }
}
