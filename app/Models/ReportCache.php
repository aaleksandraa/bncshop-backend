<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportCache extends Model
{
    public $timestamps = false;

    protected $table = 'report_cache';

    protected $fillable = [
        'report_key',
        'params_hash',
        'data',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
