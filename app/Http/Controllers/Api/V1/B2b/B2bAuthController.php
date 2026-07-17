<?php

namespace App\Http\Controllers\Api\V1\B2b;

use App\Http\Controllers\Api\V1\B2b\Concerns\FormatsB2bResponses;
use App\Http\Controllers\Api\V1\Concerns\AuthenticatesApiSession;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Concerns\ValidatesBotProtection;
use App\Mail\B2b\B2bPasswordResetMail;
use App\Models\B2bPasswordResetToken;
use App\Models\B2bPasswordSetupToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class B2bAuthController extends Controller
{
    use AuthenticatesApiSession;
    use FormatsB2bResponses;
    use RespondsWithJson;
    use ValidatesBotProtection;

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            ...$this->botProtectionRules(),
        ]);

        if (! Auth::attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
        ])) {
            return $this->error('Neispravni email ili lozinka.', 422);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_b2b_customer || ! $user->b2bCustomer?->is_active) {
            Auth::logout();

            return $this->error('Neispravni email ili lozinka.', 422);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return $this->success([
            'user' => $this->formatCustomer($user->b2bCustomer),
        ]);
    }

    public function setPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            ...$this->botProtectionRules(),
        ]);

        $tokenRecord = B2bPasswordSetupToken::findValid($validated['token']);

        if (! $tokenRecord) {
            return $this->error('Link za postavljanje lozinke je nevažeći ili je istekao.', 422);
        }

        $user = $tokenRecord->user;

        if (! $user->is_b2b_customer) {
            return $this->error('Nevažeći B2B račun.', 422);
        }

        $user->update(['password' => Hash::make($validated['password'])]);
        $user->tokens()->where('name', 'b2b-api')->delete();
        B2bPasswordSetupToken::query()->where('user_id', $user->id)->delete();

        return $this->success([
            'message' => 'Lozinka je uspješno postavljena. Možete se prijaviti.',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            ...$this->botProtectionRules(),
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->where('is_b2b_customer', true)
            ->whereHas('b2bCustomer', fn ($query) => $query->where('is_active', true))
            ->first();

        if ($user) {
            $plainToken = B2bPasswordResetToken::createForUser(
                $user,
                (int) config('b2b.password_reset_hours', 24),
            );
            $resetUrl = rtrim((string) config('bnc.frontend_url'), '/').'/b2b/reset-lozinke?token='.$plainToken;

            Mail::to($user->email)->queue(new B2bPasswordResetMail($user, $resetUrl));
        }

        return $this->success([
            'message' => 'Ako postoji B2B račun sa ovim emailom, poslali smo upute za resetovanje lozinke.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            ...$this->botProtectionRules(),
        ]);

        $tokenRecord = B2bPasswordResetToken::findValid($validated['token']);

        if (! $tokenRecord) {
            return $this->error('Link za resetovanje lozinke je nevažeći ili je istekao.', 422);
        }

        $user = $tokenRecord->user;

        if (! $user->is_b2b_customer) {
            return $this->error('Nevažeći B2B račun.', 422);
        }

        $user->update(['password' => Hash::make($validated['password'])]);
        $user->tokens()->where('name', 'b2b-api')->delete();
        B2bPasswordResetToken::query()->where('user_id', $user->id)->delete();

        return $this->success([
            'message' => 'Lozinka je uspješno promijenjena. Možete se prijaviti.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->logoutUserSession($request);

        return $this->success(['message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success($this->formatCustomer($this->b2bCustomer($request)));
    }
}
