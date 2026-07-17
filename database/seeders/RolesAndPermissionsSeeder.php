<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private array $legacyPermissions = [
        'manage_products',
        'view_products',
        'view_margin',
        'suppliers.view',
        'suppliers.update',
        'margin_rules.view',
        'margin_rules.update',
        'manage_discounts',
        'view_discounts',
        'manage_orders',
        'view_orders',
        'manage_sync',
        'view_sync',
        'export_reports',
        'menus.view',
        'menus.create',
        'menus.update',
        'menus.delete',
        'pages.view',
        'pages.create',
        'pages.update',
        'pages.delete',
        'blog_posts.view',
        'blog_posts.create',
        'blog_posts.update',
        'blog_posts.delete',
    ];

    /**
     * @return array<int, string>
     */
    private function filamentPermissions(): array
    {
        $resources = [
            'products',
            'orders',
            'installment_inquiries',
            'categories',
            'manufacturers',
            'tags',
            'customers',
            'coupons',
            'discounts',
            'blog_posts',
            'shipping_rules',
            'redirects',
            'email_templates',
            'users',
            'attributes',
            'api_sources',
            'api_import_jobs',
            'loyalty_rewards',
        ];

        $permissions = [
            'analytics.view',
            'loyalty.view',
            'loyalty.update',
            'loyalty_cards.view',
            'loyalty_cards.issue',
            'loyalty_cards.block',
            'loyalty_in_store.operate',
        ];

        foreach ($resources as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $permissions[] = "{$resource}.{$action}";
            }
        }

        return $permissions;
    }

    /**
     * @return array<int, string>
     */
    private function allPermissions(): array
    {
        return array_values(array_unique(array_merge(
            $this->legacyPermissions,
            $this->filamentPermissions(),
            $this->b2bPermissions(),
        )));
    }

    /**
     * @return array<int, string>
     */
    private function b2bPermissions(): array
    {
        $resources = [
            'b2b_categories',
            'b2b_products',
            'b2b_campaigns',
            'b2b_customers',
            'b2b_orders',
            'b2b_access_requests',
            'b2b_settings',
        ];

        $permissions = [];

        foreach ($resources as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $permissions[] = "{$resource}.{$action}";
            }
        }

        return $permissions;
    }

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->allPermissions() as $permission) {
            Permission::findOrCreate($permission);
        }

        $all = $this->allPermissions();

        $roles = [
            'Super Admin' => $all,
            'Admin' => $all,
            'Manager' => [
                'manage_products',
                'view_margin',
                'manage_discounts',
                'manage_orders',
                'view_orders',
                'orders.view',
                'orders.update',
                'installment_inquiries.view',
                'installment_inquiries.update',
                'customers.view',
                'loyalty.view',
                'loyalty.update',
                'loyalty_rewards.view',
                'loyalty_rewards.create',
                'loyalty_rewards.update',
                'loyalty_cards.view',
                'loyalty_cards.issue',
                'loyalty_cards.block',
                'loyalty_in_store.operate',
                'export_reports',
                'analytics.view',
            ],
            'Content Editor' => [
                'manage_products',
                'products.view',
                'products.update',
                'menus.view',
                'menus.update',
                'pages.view',
                'pages.create',
                'pages.update',
                'blog_posts.view',
                'blog_posts.create',
                'blog_posts.update',
            ],
            'Warehouse' => [
                'view_products',
                'products.view',
                'manage_orders',
                'view_orders',
                'orders.view',
                'orders.update',
                'installment_inquiries.view',
                'installment_inquiries.update',
            ],
            'Prodavac' => [
                'view_orders',
                'manage_orders',
                'orders.view',
                'orders.update',
                'installment_inquiries.view',
                'installment_inquiries.update',
                'customers.view',
                'loyalty_cards.view',
                'loyalty_in_store.operate',
            ],
            'Analyst' => [
                'view_products',
                'products.view',
                'view_margin',
                'view_discounts',
                'discounts.view',
                'view_orders',
                'orders.view',
                'view_sync',
                'api_sources.view',
                'export_reports',
                'analytics.view',
            ],
            'B2B Admin' => $this->b2bPermissions(),
        ];

        $b2bPermissions = $this->b2bPermissions();
        $roles['Super Admin'] = array_values(array_unique(array_merge($roles['Super Admin'], $b2bPermissions)));
        $roles['Admin'] = array_values(array_unique(array_merge($roles['Admin'], $b2bPermissions)));

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName);
            $role->syncPermissions($rolePermissions);
        }
    }
}
