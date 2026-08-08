<?php

namespace App\Filament\B2b\Auth;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
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

    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();

        if (! Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();

        if ($user instanceof FilamentUser && ! $user->canAccessPanel(Filament::getCurrentPanel())) {
            Filament::auth()->logout();

            if ($user->is_b2b_customer) {
                throw ValidationException::withMessages([
                    'data.email' => 'Ovaj email pripada B2B kupcu. Prijavite se na webshop portal (/b2b), ne na B2B admin panel.',
                ]);
            }

            if ($user->is_customer) {
                throw ValidationException::withMessages([
                    'data.email' => 'Ovaj email pripada retail kupcu. Koristite /nalog/prijava na webshopu.',
                ]);
            }

            throw ValidationException::withMessages([
                'data.email' => 'Račun postoji, ali nema ulogu B2B Admin. U glavnom admin panelu (Sistem → Admin korisnici) dodijelite ulogu "B2B Admin", ili pokrenite: php artisan bnc:grant-admin '.$user->email.' --role="B2B Admin"',
            ]);
        }

        session()->regenerate();

        return app(LoginResponse::class);
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
            'Prijavite se na <strong>BNC Shop</strong> B2B admin sistem.<br><span class="text-sm text-gray-500">URL: <code>/b2b-admin/login</code> na backend domeni (npr. api.bncshop.ba).</span>'
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
            'data.email' => 'Neispravna email adresa ili lozinka.',
        ]);
    }
}
