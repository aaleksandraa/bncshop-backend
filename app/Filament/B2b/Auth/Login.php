<?php

namespace App\Filament\B2b\Auth;

use Filament\Facades\Filament;
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

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString(
            'Prijavite se B2B admin nalogom (<strong>b2badmin@bncshop.test</strong>) '
            .'ili glavnim admin nalogom. B2B kupci koriste <a href="/b2b" class="text-primary-600 hover:underline">B2B portal</a>.'
        );
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => 'Neispravni podaci ili nalog nema pristup B2B admin panelu.',
        ]);
    }
}
