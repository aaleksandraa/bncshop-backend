<?php

namespace App\Filament\Auth;

use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Pages\Auth\PasswordReset\ResetPassword as BaseResetPassword;
use Illuminate\Contracts\Support\Htmlable;

class ResetPassword extends BaseResetPassword
{
    public function getTitle(): string|Htmlable
    {
        return 'Nova lozinka';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Postavite novu lozinku';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Unesite novu lozinku za admin pristup.';
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->label('Email adresa');
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label('Nova lozinka');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()
            ->label('Potvrdite lozinku');
    }

    public function getResetPasswordFormAction(): Action
    {
        return parent::getResetPasswordFormAction()
            ->label('Sačuvaj lozinku');
    }
}
