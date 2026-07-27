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

        $permission = Permission::findOrCreate('seller.edit_eline_products');
        $role = Role::findOrCreate('Prodavac');

        if (! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::query()->where('name', 'seller.edit_eline_products')->first();
        $role = Role::query()->where('name', 'Prodavac')->first();

        if ($role !== null && $permission !== null) {
            $role->revokePermissionTo($permission);
        }
    }
};
