<?php

namespace App\Http\Controllers\Admin\Concerns;

trait AuthorizesB2bAdminAccess
{
    protected function authorizeB2bAdminAccess(string $permission = 'b2b_orders.view'): void
    {
        abort_unless(auth()->check(), 403);

        $user = auth()->user();

        if ($user->hasRole(['Super Admin', 'Admin', 'B2B Admin'])) {
            return;
        }

        abort_unless(
            $user->can($permission) || $user->can('b2b_settings.view'),
            403,
        );
    }
}
