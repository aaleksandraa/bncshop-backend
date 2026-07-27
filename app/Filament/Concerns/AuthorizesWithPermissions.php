<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

trait AuthorizesWithPermissions
{
    abstract protected static function permissionPrefix(): string;

    public static function canViewAny(): bool
    {
        return static::userCan('view');
    }

    public static function canView(Model $record): bool
    {
        return static::userCan('view');
    }

    public static function canCreate(): bool
    {
        return static::userCan('create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCan('update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCan('delete');
    }

    protected static function userCan(string $action): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        $prefix = static::permissionPrefix();

        if ($user->can("{$prefix}.{$action}")) {
            return true;
        }

        foreach (static::legacyPermissionsFor($action) as $legacy) {
            if ($user->can($legacy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    protected static function legacyPermissionsFor(string $action): array
    {
        $prefix = static::permissionPrefix();

        return match ($prefix) {
            'orders' => match ($action) {
                'view' => ['view_orders', 'manage_orders'],
                'create', 'update', 'delete' => ['manage_orders'],
                default => [],
            },
            'installment_inquiries' => match ($action) {
                'view' => ['view_orders', 'manage_orders', 'installment_inquiries.view'],
                'create', 'update', 'delete' => ['manage_orders', 'installment_inquiries.update'],
                default => [],
            },
            'products' => match ($action) {
                'view' => ['view_products', 'manage_products'],
                'create', 'update', 'delete' => ['manage_products'],
                default => [],
            },
            'discounts' => match ($action) {
                'view' => ['view_discounts', 'manage_discounts'],
                'create', 'update', 'delete' => ['manage_discounts'],
                default => [],
            },
            'loyalty_rewards' => match ($action) {
                'view' => ['loyalty.view', 'loyalty.update'],
                'create', 'update', 'delete' => ['loyalty.update'],
                default => [],
            },
            'api_sources', 'api_import_jobs' => match ($action) {
                'view' => ['view_sync', 'manage_sync'],
                'create', 'update', 'delete' => ['manage_sync'],
                default => [],
            },
            'email_logs' => match ($action) {
                'view' => ['manage_orders', 'view_orders'],
                default => [],
            },
            default => [],
        };
    }
}
