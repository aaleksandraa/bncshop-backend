<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\AuthenticatesApiSession;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Requests\Api\V1\CustomerLoginRequest;
use App\Http\Requests\Api\V1\CustomerRegisterRequest;
use App\Http\Requests\Api\V1\UpdateCustomerProfileRequest;
use App\Models\Customer;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Marketing\BrevoService;
use App\Services\Marketing\BrevoSettings;
use App\Services\Marketing\MarketingContactSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    use AuthenticatesApiSession;
    use RespondsWithJson;

    public function __construct(
        private readonly LoyaltyService $loyaltyService,
        private readonly MarketingContactSyncService $marketingContactSyncService,
        private readonly BrevoService $brevoService,
        private readonly BrevoSettings $brevoSettings,
    ) {}

    public function register(CustomerRegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::createAccount([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'phone' => $validated['phone'] ?? null,
            'is_customer' => true,
            'is_b2b_customer' => false,
        ]);

        $customer = Customer::query()->create([
            'user_id' => $user->id,
            'phone' => $validated['phone'] ?? null,
        ]);

        $user->setRelation('customer', $customer);
        $claimed = $this->loyaltyService->claimPendingForCustomer($customer);
        $this->syncMarketingContact($customer);

        $this->loginUserSession($request, $user);

        return $this->success([
            'user' => $user,
            'loyalty_claim' => $claimed,
        ], status: 201);
    }

    public function login(CustomerLoginRequest $request): JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Neispravni podaci za prijavu.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_customer) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => ['Ovaj račun nije korisnički račun.'],
            ]);
        }

        $request->session()->regenerate();

        if ($user->customer) {
            $this->loyaltyService->claimPendingForCustomer($user->customer);
        }

        return $this->success([
            'user' => $user->load('customer'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->logoutUserSession($request);

        return $this->success(['message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success($request->user()?->load('customer'));
    }

    public function orders(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with(['items'])
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 20), 50));

        return $this->paginated($orders, $orders->items());
    }

    public function updateProfile(UpdateCustomerProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $user->update(collect($validated)->only(['name', 'email', 'phone'])->filter()->all());

        if ($user->customer) {
            $user->customer->update(collect($validated)->only(['phone', 'company_name', 'jib'])->filter()->all());
        }

        return $this->success($user->fresh('customer'));
    }

    public function pendingLoyaltyPoints(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->email) {
            return $this->error('E-mail nije postavljen na računu.');
        }

        return $this->success([
            'pending_points' => $this->loyaltyService->getPendingPointsForEmail(
                strtolower(trim($user->email)),
            ),
        ]);
    }

    private function syncMarketingContact(Customer $customer): void
    {
        try {
            $contact = $this->marketingContactSyncService->syncCustomer($customer);

            if ($contact === null) {
                return;
            }

            $settings = $this->brevoSettings->all();

            if (($settings['sync_registered'] ?? false) && $this->brevoService->isConfigured()) {
                $this->brevoService->syncContact($contact);
            }
        } catch (\Throwable) {
            // Marketing sync must not block registration.
        }
    }
}
