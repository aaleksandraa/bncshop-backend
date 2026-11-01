<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnanasSyncRun extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_CANCELLED = 'cancelled';

    public const JOB_CATALOG = 'catalog';

    public const JOB_TAKEDOWN = 'takedown';

    public const JOB_PRICE_STOCK = 'price_stock';

    public const JOB_DISCOUNTS = 'discounts';

    public const JOB_IMPORT = 'import';

    public const JOB_INTRADAY_STOCK = 'intraday_stock';

    protected $fillable = [
        'job_key',
        'status',
        'triggered_by',
        'triggered_by_user_id',
        'updated_count',
        'skipped_count',
        'failed_item_count',
        'failed_inventory_ids',
        'error_message',
        'meta',
        'last_progress_at',
        'cancel_requested_at',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'failed_inventory_ids' => 'array',
            'meta' => 'array',
            'last_progress_at' => 'datetime',
            'cancel_requested_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }
}
