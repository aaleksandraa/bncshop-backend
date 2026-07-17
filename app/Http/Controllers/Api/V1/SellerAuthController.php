<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthenticatesApiSession;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SellerLoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SellerAuthController extends Controller
{
    use AuthenticatesApiSession;
    use RespondsWithJson;

    public function login(SellerLoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Neispravni podaci za prijavu.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->is_customer) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => ['Ovaj račun nije prodavački račun.'],
            ]);
        }

        if (! $user->can('view_orders') && ! $user->can('manage_orders')) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => ['Nemate dozvolu za prodavački panel.'],
            ]);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return $this->success([
            'user' => $this->formatUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->logoutUserSession($request);

        return $this->success(['message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->success($this->formatUser($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => $user->getRoleNames()->values()->all(),
        ];
    }
}
