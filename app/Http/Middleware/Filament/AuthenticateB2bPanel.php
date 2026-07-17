<?php

namespace App\Http\Middleware\Filament;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as BaseAuthenticate;
use Filament\Models\Contracts\FilamentUser;

class AuthenticateB2bPanel extends BaseAuthenticate
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);

            return;
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        /** @var \Illuminate\Database\Eloquent\Model $user */
        $user = $guard->user();

        $panel = Filament::getCurrentPanel();

        if (
            $user instanceof FilamentUser
            && ! $user->canAccessPanel($panel)
        ) {
            Filament::auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->unauthenticated($request, $guards);

            return;
        }

        if (
            ! ($user instanceof FilamentUser)
            && config('app.env') !== 'local'
        ) {
            abort(403);
        }
    }
}
