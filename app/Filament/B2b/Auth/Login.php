<?php

namespace App\Filament\B2b\Auth;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Models\Contracts\FilamentUser;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function mount(): void
    {
        $user = Filament::auth()->user();

        if ($user !== null) {
            if (
                $user instanceof FilamentUser
                && $user->canAccessPanel(Filament::getCurrentPanel())
            ) {
                redirect()->intended(Filament::getUrl());
            }

            Filament::auth()->logout();
        }

        $this->form->fill();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Prijava';
    }

    public function getHeading(): string|Htmlable
    {
        return 'B2B admin panel';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString(
            'Prijavite se na <strong>BNC Shop</strong> B2B admin sistem.'
        );
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->label('Email adresa')
            ->placeholder('admin@bncshop.ba');
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label('Lozinka');
    }

    protected function getRememberFormComponent(): Component
    {
        return parent::getRememberFormComponent()
            ->label('Zapamti me');
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()
            ->label('Prijavi se');
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => 'Neispravna email adresa ili lozinka, ili nalog nema pristup B2B admin panelu.',
        ]);
    }
}
