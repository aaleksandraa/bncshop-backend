<?php

namespace App\Filament\Auth\Concerns;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;

trait HasAdminAuthProtectionFields
{
    protected function getHoneypotFormComponent(): Component
    {
        return TextInput::make('website')
            ->label('')
            ->hiddenLabel()
            ->extraFieldWrapperAttributes(['class' => 'bnc-admin-honeypot-wrap'])
            ->extraInputAttributes([
                'tabindex' => '-1',
                'autocomplete' => 'off',
                'aria-hidden' => 'true',
            ])
            ->dehydrated();
    }

    protected function getSecondaryHoneypotFormComponent(): Component
    {
        return TextInput::make('company')
            ->label('')
            ->hiddenLabel()
            ->extraFieldWrapperAttributes(['class' => 'bnc-admin-honeypot-wrap'])
            ->extraInputAttributes([
                'tabindex' => '-1',
                'autocomplete' => 'off',
                'aria-hidden' => 'true',
            ])
            ->dehydrated();
    }

    protected function getTurnstileTokenFormComponent(): Component
    {
        return Hidden::make('turnstile_token')
            ->dehydrated();
    }
}
