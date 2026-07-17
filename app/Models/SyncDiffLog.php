<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncDiffLog extends Model
{
    public $timestamps = false;

    protected $table = 'sync_diff_log';

    protected $fillable = [
        'product_id',
        'field_name',
        'api_value',
        'local_value',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
