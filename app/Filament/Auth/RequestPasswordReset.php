<?php

namespace App\Filament\Auth;

use App\Filament\Auth\Concerns\HasAdminAuthProtectionFields;
use App\Services\Security\AdminLoginProtection;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\View;
use Filament\Pages\Auth\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    use HasAdminAuthProtectionFields;

    public function getTitle(): string|Htmlable
    {
        return 'Zaboravljena lozinka';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Resetovanje lozinke';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Unesite email admin računa. Poslat ćemo vam link za postavljanje nove lozinke.';
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getEmailFormComponent(),
                        $this->getHoneypotFormComponent(),
                        $this->getSecondaryHoneypotFormComponent(),
                        $this->getTurnstileFormComponent(),
                        $this->getTurnstileTokenFormComponent(),
                    ])
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

    protected function getTurnstileFormComponent(): Component
    {
        return View::make('filament.admin.turnstile-widget')
            ->visible(fn (): bool => (bool) config('turnstile.enabled'))
            ->columnSpanFull();
    }

    protected function getRequestFormAction(): Action
    {
        return parent::getRequestFormAction()
            ->label('Pošalji link');
    }

    public function loginAction(): Action
    {
        return parent::loginAction()
            ->label('Nazad na prijavu');
    }

    public function request(): void
    {
        /** @var AdminLoginProtection $protection */
        $protection = app(AdminLoginProtection::class);

        try {
            $this->rateLimit(3, 300);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        try {
            $protection->ensureIpNotBlocked();
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $data = $this->form->getState();

        try {
            $protection->validateBotProtection($data, ['security_code' => false]);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        parent::request();
    }

    protected function getSentNotification(string $status): ?\Filament\Notifications\Notification
    {
        if ($status !== Password::RESET_LINK_SENT) {
            return parent::getSentNotification($status);
        }

        return \Filament\Notifications\Notification::make()
            ->title('Link za reset je poslan')
            ->body('Ako postoji admin račun sa tim emailom, primit ćete uputstvo za reset lozinke.')
            ->success();
    }

    protected function getFailureNotification(string $status): ?\Filament\Notifications\Notification
    {
        return \Filament\Notifications\Notification::make()
            ->title('Link za reset je poslan')
            ->body('Ako postoji admin račun sa tim emailom, primit ćete uputstvo za reset lozinke.')
            ->success();
    }
}
