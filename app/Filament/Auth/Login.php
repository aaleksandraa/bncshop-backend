<?php

namespace App\Filament\Auth;

use App\Filament\Auth\Concerns\HasAdminAuthProtectionFields;
use App\Services\Security\AdminLoginProtection;
use App\Services\Security\TurnstileVerifier;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    use HasAdminAuthProtectionFields;

    public function getTitle(): string|Htmlable
    {
        return 'Prijava';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Admin panel';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString(
            'Prijavite se na <strong>BNC Shop</strong> admin sistem.'
        );
    }

    protected function getForms(): array
    {
        $schema = [
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getSecurityCodeFormComponent(),
            $this->getHoneypotFormComponent(),
            $this->getSecondaryHoneypotFormComponent(),
            $this->getTurnstileFormComponent(),
            $this->getTurnstileTokenFormComponent(),
            $this->getRememberFormComponent(),
        ];

        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema($schema)
                    ->statePath('data'),
            ),
        ];
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->label('Email adresa')
            ->placeholder('admin@bncshop.ba');
    }

    protected function getPasswordFormComponent(): Component
    {
        $component = parent::getPasswordFormComponent()
            ->label('Lozinka');

        if (filament()->hasPasswordReset()) {
            $component->hint(new HtmlString(
                '<a href="'.e(filament()->getRequestPasswordResetUrl()).'" class="fi-link text-sm font-medium text-primary-600 hover:underline" tabindex="3">Zaboravili ste lozinku?</a>'
            ));
        }

        return $component;
    }

    protected function getSecurityCodeFormComponent(): Component
    {
        return TextInput::make('security_code')
            ->label('Sigurnosni kod')
            ->password()
            ->revealable()
            ->autocomplete('off')
            ->required(fn (): bool => filled(config('admin.login_secret')))
            ->visible(fn (): bool => filled(config('admin.login_secret')))
            ->helperText('Dodatna zaštita admin pristupa.')
            ->extraInputAttributes(['tabindex' => 3]);
    }

    protected function getTurnstileFormComponent(): Component
    {
        return View::make('filament.admin.turnstile-widget')
            ->visible(fn (): bool => app(TurnstileVerifier::class)->isEnabled())
            ->columnSpanFull();
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

    public function authenticate(): ?LoginResponse
    {
        /** @var AdminLoginProtection $protection */
        $protection = app(AdminLoginProtection::class);

        try {
            $this->rateLimit(5, 300);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        try {
            $protection->ensureIpNotBlocked();
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $data = $this->form->getState();

        try {
            $protection->validateBotProtection($data);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        if (! Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $protection->recordFailedAttempt($data['email'] ?? null);
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();

        if (
            ($user instanceof FilamentUser) &&
            (! $user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            Filament::auth()->logout();
            $protection->recordFailedAttempt($data['email'] ?? null);

            throw ValidationException::withMessages([
                'data.email' => 'Račun postoji, ali nema admin ulogu. Na serveru pokrenite: php artisan bnc:grant-admin '.$data['email'],
            ]);
        }

        $protection->clearFailedAttempts();
        session()->regenerate();

        return app(LoginResponse::class);
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => 'Neispravna email adresa ili lozinka.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'email' => $data['email'],
            'password' => $data['password'],
        ];
    }
}
