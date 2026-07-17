<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait AuthenticatesApiSession
{
    protected function loginUserSession(Request $request, User $user): void
    {
        Auth::login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
    }

    protected function logoutUserSession(Request $request): void
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
