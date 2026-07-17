<?php

namespace App\Http\Controllers\Admin\Concerns;

trait AuthorizesOrderAdminAccess
{
    protected function authorizeOrderAdminAccess(): void
    {
        abort_unless(auth()->check(), 403);

        $user = auth()->user();

        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return;
        }

        abort_unless(
            $user->can('orders.view') || $user->can('view_orders') || $user->can('manage_orders'),
            403,
        );
    }
}
