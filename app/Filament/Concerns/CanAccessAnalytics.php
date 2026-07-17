<?php

namespace App\Filament\Concerns;

trait CanAccessAnalytics
{
    protected static function canAccessAnalytics(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->can('analytics.view') || $user->can('export_reports');
    }
}
