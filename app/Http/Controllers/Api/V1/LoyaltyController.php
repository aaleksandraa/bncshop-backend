<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Models\Customer;
use App\Models\LoyaltyReward;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Loyalty\LoyaltySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly LoyaltySettings $loyaltySettings,
        private readonly LoyaltyService $loyaltyService,
    ) {}

    public function settings(): JsonResponse
    {
        return $this->success($this->loyaltySettings->publicPayload());
    }

    public function account(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        if (! $customer) {
            return $this->error('Korisnički profil nije pronađen.', status: 404);
        }

        $rewards = $this->loyaltyService->getAvailableRewards($customer);
        $allRewards = LoyaltyReward::query()
            ->with('product')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('points_required')
            ->get()
            ->filter(fn (LoyaltyReward $reward): bool => $reward->isCurrentlyActive());

        return $this->success([
            'balance' => $this->loyaltyService->getBalance($customer),
            'program' => $this->loyaltySettings->publicPayload(),
            'available_rewards' => $rewards->values(),
            'rewards' => $allRewards->values(),
            'transactions' => $this->loyaltyService->getTransactionHistory($customer),
            'loyalty_card' => $customer->activeLoyaltyCard()?->only(['id', 'card_number', 'status', 'issued_at']),
        ]);
    }

    private function resolveCustomer(Request $request): ?Customer
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $customer = Customer::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['phone' => $user->phone],
        );

        $customer->load(['loyaltyCards' => fn ($q) => $q->where('status', 'active')]);

        return $customer;
    }
}
