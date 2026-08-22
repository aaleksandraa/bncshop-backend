<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['view', 'create', 'update', 'delete'] as $action) {
            Permission::findOrCreate("shop_campaigns.{$action}");
        }

        $permissions = collect(['view', 'create', 'update', 'delete'])
            ->map(fn (string $action): string => "shop_campaigns.{$action}")
            ->all();

        foreach (['Super Admin', 'Admin'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->first();

            if ($role !== null) {
                $role->givePermissionTo($permissions);
            }
        }

        $contentEditor = Role::query()->where('name', 'Content Editor')->first();

        if ($contentEditor !== null) {
            $contentEditor->givePermissionTo([
                'shop_campaigns.view',
                'shop_campaigns.create',
                'shop_campaigns.update',
            ]);
        }
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::query()
            ->where('name', 'like', 'shop_campaigns.%')
            ->delete();
    }
};
