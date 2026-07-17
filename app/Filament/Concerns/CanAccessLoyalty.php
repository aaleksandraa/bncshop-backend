<?php

namespace App\Filament\Concerns;

trait CanAccessLoyalty
{
    protected static function canAccessLoyalty(bool $requireUpdate = false): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        if ($requireUpdate) {
            return $user->can('loyalty.update')
                || $user->can('loyalty_rewards.create')
                || $user->can('loyalty_rewards.update')
                || $user->can('loyalty_rewards.delete')
                || $user->can('loyalty_cards.issue')
                || $user->can('loyalty_in_store.operate');
        }

        return $user->can('loyalty.view')
            || $user->can('loyalty.update')
            || $user->can('loyalty_rewards.view')
            || $user->can('loyalty_rewards.create')
            || $user->can('loyalty_rewards.update')
            || $user->can('loyalty_cards.view')
            || $user->can('loyalty_in_store.operate');
    }

    protected static function canAccessLoyaltyCards(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        return $user->can('loyalty_cards.view')
            || $user->can('loyalty_cards.issue')
            || $user->can('loyalty.view');
    }

    protected static function canIssueCards(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole(['Super Admin', 'Admin', 'Manager'])
            || $user->can('loyalty_cards.issue');
    }

    protected static function canBlockCards(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole(['Super Admin', 'Admin', 'Manager'])
            || $user->can('loyalty_cards.block');
    }

    protected static function canOperateInStore(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasRole(['Super Admin', 'Admin', 'Manager'])) {
            return true;
        }

        return $user->can('loyalty_in_store.operate');
    }
}
