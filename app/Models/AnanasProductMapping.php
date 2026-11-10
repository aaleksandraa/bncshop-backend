<?php

namespace App\Models;

use App\Services\Ananas\AnanasSyncSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnanasProductMapping extends Model
{
    public const LOCAL_DISABLED = 'DISABLED';

    public const LOCAL_NOT_ELIGIBLE = 'NOT_ELIGIBLE';

    public const LOCAL_READY = 'READY';

    public const LOCAL_QUEUED = 'QUEUED';

    public const LOCAL_SUBMITTED = 'SUBMITTED';

    public const LOCAL_PENDING_ONBOARDING = 'PENDING_ONBOARDING';

    public const LOCAL_LINKED = 'LINKED';

    public const LOCAL_FAILED = 'FAILED';

    protected $fillable = [
        'product_id',
        'export_enabled',
        'ean',
        'sku',
        'external_id',
        'ananas_product_id',
        'merchant_inventory_id',
        'ananas_code',
        'group_id',
        'remote_status',
        'local_status',
        'remote_environment',
        'ean_exists_on_ananas',
        'last_progress_id',
        'payload_hash',
        'stock_hash',
        'price_hash',
        'last_submitted_at',
        'last_success_at',
        'last_error_at',
        'last_error',
        'remote_modified_at',
        'remote_missing_at',
        'takedown_state_hash',
    ];

    protected function casts(): array
    {
        return [
            'export_enabled' => 'boolean',
            'ean_exists_on_ananas' => 'boolean',
            'last_submitted_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_error_at' => 'datetime',
            'remote_modified_at' => 'datetime',
            'remote_missing_at' => 'datetime',
        ];
    }

    /**
     * Local statuses that already have an Ananas import in progress or linked.
     *
     * @return list<string>
     */
    public static function inFlightStatuses(): array
    {
        return [
            self::LOCAL_SUBMITTED,
            self::LOCAL_PENDING_ONBOARDING,
            self::LOCAL_LINKED,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function discountActions(): HasMany
    {
        return $this->hasMany(AnanasDiscountAction::class);
    }

    /**
     * @param  Builder<AnanasProductMapping>  $query
     * @return Builder<AnanasProductMapping>
     */
    public function scopeForCurrentRemoteEnvironment(Builder $query): Builder
    {
        return $query->where('remote_environment', app(AnanasSyncSettings::class)->environment());
    }

    public function blocksAnotherExport(?string $environment = null): bool
    {
        if (! in_array((string) $this->local_status, self::inFlightStatuses(), true)) {
            return false;
        }

        $environment ??= app(AnanasSyncSettings::class)->environment();

        return $this->remote_environment === null || $this->remote_environment === $environment;
    }

    public function inventoryId(): int
    {
        $inventory = (int) ($this->merchant_inventory_id ?: $this->ananas_product_id);

        return max(0, $inventory);
    }
}
