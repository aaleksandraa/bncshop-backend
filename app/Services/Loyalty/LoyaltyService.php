<?php

namespace App\Services\Loyalty;

use App\Mail\LoyaltyNotificationMail;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\LoyaltyPendingEarning;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class LoyaltyService
{
    public function __construct(
        private readonly LoyaltySettings $settings,
        private readonly LoyaltyPointsCalculator $calculator,
    ) {}

    public function isProgramActive(): bool
    {
        return $this->settings->isProgramActive();
    }

    public function getBalance(Customer $customer): int
    {
        return (int) $customer->loyalty_points_balance;
    }

    /**
     * @return Collection<int, LoyaltyReward>
     */
    public function getAvailableRewards(Customer $customer): Collection
    {
        $balance = $this->getBalance($customer);

        return LoyaltyReward::query()
            ->with('product')
            ->where('is_active', true)
            ->where('points_required', '<=', $balance)
            ->orderBy('sort_order')
            ->orderBy('points_required')
            ->get()
            ->filter(fn (LoyaltyReward $reward): bool => $reward->isCurrentlyActive()
                && $this->customerCanUseReward($customer, $reward))
            ->values();
    }

    /**
     * @return array{valid: bool, message: ?string}
     */
    public function validateRedemption(Customer $customer, LoyaltyReward $reward): array
    {
        if (! $this->isProgramActive()) {
            return ['valid' => false, 'message' => 'Program lojalnosti trenutno nije aktivan.'];
        }

        if (! $reward->isCurrentlyActive()) {
            return ['valid' => false, 'message' => 'Nagrada nije dostupna.'];
        }

        if ($this->getBalance($customer) < $reward->points_required) {
            return ['valid' => false, 'message' => 'Nemate dovoljno bodova za ovu nagradu.'];
        }

        if (! $this->customerCanUseReward($customer, $reward)) {
            return ['valid' => false, 'message' => 'Dostigli ste limit korištenja ove nagrade.'];
        }

        if ($reward->type === 'free_product') {
            $product = $reward->product;
            if ($product === null || ! $product->is_public || $product->status !== 'active') {
                return ['valid' => false, 'message' => 'Proizvod nagrade nije dostupan.'];
            }

            if ((int) $product->available_stock < 1) {
                return ['valid' => false, 'message' => 'Proizvod nagrade nema na stanju.'];
            }
        }

        return ['valid' => true, 'message' => null];
    }

    public function calculateDiscountForCart(Cart $cart, LoyaltyReward $reward, float $subtotal): float
    {
        return match ($reward->type) {
            'percentage' => round($subtotal * ((float) $reward->reward_value / 100), 2),
            'fixed' => min($subtotal, round((float) $reward->reward_value, 2)),
            'free_product' => 0,
            default => 0,
        };
    }

    public function awardForOrder(Order $order): void
    {
        if (! $this->isProgramActive()) {
            return;
        }

        if (LoyaltyTransaction::query()->where('order_id', $order->id)->where('type', 'earn')->exists()) {
            return;
        }

        $points = $this->calculator->earnPointsForOrder($order);

        if ($points <= 0) {
            return;
        }

        if ($order->customer_id) {
            $customer = Customer::query()->find($order->customer_id);
            if ($customer) {
                $this->creditPoints($customer, $points, 'earn', "Bodovi za narudžbu {$order->order_number}", $order);
                $order->update(['points_earned' => $points]);
                $this->checkAndNotifyUnlockedRewards($customer);
                $this->sendPointsEarnedEmail($customer, $order, $points);

                return;
            }
        }

        if ($order->email && ($this->settings->get('guest_registration_prompt', true))) {
            LoyaltyPendingEarning::query()->updateOrCreate(
                ['order_id' => $order->id],
                [
                    'email' => strtolower(trim($order->email)),
                    'points' => $points,
                    'status' => 'pending',
                ],
            );

            $order->update(['points_earned' => $points]);
            $this->sendGuestRegisterPromptEmail($order, $points);
        }
    }

    public function clawbackForOrder(Order $order): void
    {
        if ((int) $order->points_earned <= 0) {
            return;
        }

        if (LoyaltyTransaction::query()->where('order_id', $order->id)->where('type', 'clawback')->exists()) {
            return;
        }

        $earn = LoyaltyTransaction::query()
            ->where('order_id', $order->id)
            ->where('type', 'earn')
            ->first();

        if ($earn) {
            $customer = Customer::query()->find($earn->customer_id);
            if ($customer) {
                $points = min((int) $order->points_earned, $this->getBalance($customer));
                if ($points > 0) {
                    $this->debitPoints($customer, $points, 'clawback', "Povrat bodova za narudžbu {$order->order_number}", $order);
                }
            }
        }

        LoyaltyPendingEarning::query()
            ->where('order_id', $order->id)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);
    }

    /**
     * @return array{claimed_points: int, claimed_orders: int}
     */
    public function claimPendingForCustomer(Customer $customer): array
    {
        $email = strtolower(trim((string) $customer->user?->email));

        if ($email === '') {
            return ['claimed_points' => 0, 'claimed_orders' => 0];
        }

        $pending = LoyaltyPendingEarning::query()
            ->where('email', $email)
            ->where('status', 'pending')
            ->get();

        $claimedPoints = 0;
        $claimedOrders = 0;

        foreach ($pending as $entry) {
            DB::transaction(function () use ($customer, $entry, &$claimedPoints, &$claimedOrders): void {
                $this->creditPoints(
                    $customer,
                    (int) $entry->points,
                    'claim_pending',
                    "Preuzeti bodovi za narudžbu #{$entry->order_id}",
                    $entry->order,
                );

                $entry->update(['status' => 'claimed']);
                $claimedPoints += (int) $entry->points;
                $claimedOrders++;
            });
        }

        if ($claimedPoints > 0) {
            $this->checkAndNotifyUnlockedRewards($customer);
        }

        return ['claimed_points' => $claimedPoints, 'claimed_orders' => $claimedOrders];
    }

    /**
     * @return array{redemption: LoyaltyRedemption, discount_amount: float}
     */
    public function redeemForCheckout(Customer $customer, LoyaltyReward $reward, Order $order, float $discountAmount): LoyaltyRedemption
    {
        $validation = $this->validateRedemption($customer, $reward);
        if (! $validation['valid']) {
            throw new RuntimeException($validation['message'] ?? 'Nagrada nije validna.');
        }

        return DB::transaction(function () use ($customer, $reward, $order, $discountAmount): LoyaltyRedemption {
            $this->debitPoints(
                $customer,
                (int) $reward->points_required,
                'redeem',
                "Iskorištena nagrada: {$reward->name}",
                $order,
                $reward,
            );

            $redemption = LoyaltyRedemption::query()->create([
                'customer_id' => $customer->id,
                'loyalty_reward_id' => $reward->id,
                'points_spent' => (int) $reward->points_required,
                'status' => 'applied',
                'generated_code' => strtoupper(Str::random(10)),
                'order_id' => $order->id,
            ]);

            $reward->increment('times_redeemed');

            $order->update([
                'points_redeemed' => (int) $reward->points_required,
                'loyalty_discount_amount' => $discountAmount,
                'loyalty_reward_id' => $reward->id,
                'loyalty_redemption_id' => $redemption->id,
            ]);

            return $redemption;
        });
    }

    public function adjustPoints(Customer $customer, int $points, string $description): LoyaltyTransaction
    {
        if ($points === 0) {
            throw new RuntimeException('Broj bodova mora biti različit od nule.');
        }

        return $points > 0
            ? $this->creditPoints($customer, $points, 'adjust', $description)
            : $this->debitPoints($customer, abs($points), 'adjust', $description);
    }

    public function checkAndNotifyUnlockedRewards(Customer $customer): void
    {
        $balance = $this->getBalance($customer);
        $rewards = LoyaltyReward::query()
            ->where('is_active', true)
            ->where('points_required', '<=', $balance)
            ->get()
            ->filter(fn (LoyaltyReward $reward): bool => $reward->isCurrentlyActive());

        foreach ($rewards as $reward) {
            $alreadyNotified = LoyaltyTransaction::query()
                ->where('customer_id', $customer->id)
                ->where('loyalty_reward_id', $reward->id)
                ->where('type', 'reward_unlocked')
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            LoyaltyTransaction::query()->create([
                'customer_id' => $customer->id,
                'type' => 'reward_unlocked',
                'points' => 0,
                'balance_after' => $balance,
                'loyalty_reward_id' => $reward->id,
                'description' => "Nagrada dostupna: {$reward->name}",
                'created_at' => now(),
            ]);

            $this->sendRewardUnlockedEmail($customer, $reward, $balance);
        }
    }

    /**
     * @return array<int, LoyaltyTransaction>
     */
    public function getTransactionHistory(Customer $customer, int $limit = 50): array
    {
        return LoyaltyTransaction::query()
            ->where('customer_id', $customer->id)
            ->whereIn('type', ['earn', 'redeem', 'expire', 'clawback', 'adjust', 'claim_pending', 'earn_in_store', 'redeem_in_store'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * @return array<int, LoyaltyTransaction>
     */
    public function getInStoreTransactionHistory(Customer $customer, int $limit = 20): array
    {
        return LoyaltyTransaction::query()
            ->where('customer_id', $customer->id)
            ->whereIn('type', ['earn_in_store', 'redeem_in_store', 'adjust'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function awardForInStoreSale(Customer $customer, float $amountKm, array $meta): LoyaltyTransaction
    {
        if (! $this->isProgramActive()) {
            throw new RuntimeException('Program lojalnosti trenutno nije aktivan.');
        }

        $receiptNumber = trim((string) ($meta['receipt_number'] ?? ''));
        if ($receiptNumber === '') {
            throw new RuntimeException('Broj računa je obavezan.');
        }

        if ($this->receiptAlreadyUsed($receiptNumber)) {
            throw new RuntimeException('Račun s ovim brojem je već evidentiran danas.');
        }

        if ($amountKm <= 0) {
            throw new RuntimeException('Iznos kupovine mora biti veći od nule.');
        }

        $rate = (float) $this->settings->get('points_per_km', 1);
        $points = (int) floor($amountKm * $rate);

        if ($points <= 0) {
            throw new RuntimeException('Kupovina ne generiše bodove prema pravilima programa.');
        }

        $transaction = $this->creditPoints(
            $customer,
            $points,
            'earn_in_store',
            "Bodovi za kupovinu u radnji (račun {$receiptNumber})",
            null,
            null,
            array_merge($meta, ['amount_km' => $amountKm, 'channel' => 'in_store']),
        );

        $this->checkAndNotifyUnlockedRewards($customer);

        return $transaction;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function redeemInStore(Customer $customer, LoyaltyReward $reward, array $meta): LoyaltyRedemption
    {
        $receiptNumber = trim((string) ($meta['receipt_number'] ?? ''));
        if ($receiptNumber === '') {
            throw new RuntimeException('Broj računa je obavezan.');
        }

        $validation = $this->validateRedemption($customer, $reward);
        if (! $validation['valid']) {
            throw new RuntimeException($validation['message'] ?? 'Nagrada nije validna.');
        }

        return DB::transaction(function () use ($customer, $reward, $meta, $receiptNumber): LoyaltyRedemption {
            $this->debitPoints(
                $customer,
                (int) $reward->points_required,
                'redeem_in_store',
                "Nagrada u radnji: {$reward->name} (račun {$receiptNumber})",
                null,
                $reward,
                array_merge($meta, ['channel' => 'in_store']),
            );

            $redemption = LoyaltyRedemption::query()->create([
                'customer_id' => $customer->id,
                'loyalty_reward_id' => $reward->id,
                'points_spent' => (int) $reward->points_required,
                'status' => 'applied',
                'generated_code' => strtoupper(Str::random(10)),
                'order_id' => null,
            ]);

            $reward->increment('times_redeemed');

            return $redemption;
        });
    }

    private function receiptAlreadyUsed(string $receiptNumber): bool
    {
        return LoyaltyTransaction::query()
            ->whereDate('created_at', today())
            ->whereIn('type', ['earn_in_store', 'redeem_in_store'])
            ->where('meta->receipt_number', $receiptNumber)
            ->exists();
    }

    public function getPendingPointsForEmail(string $email): int
    {
        return (int) LoyaltyPendingEarning::query()
            ->where('email', strtolower(trim($email)))
            ->where('status', 'pending')
            ->sum('points');
    }

    /**
     * @return array{expired_transactions: int, expired_points: int}
     */
    public function expirePoints(): array
    {
        $mode = (string) $this->settings->get('expiry_mode', 'never');

        if ($mode === 'never') {
            return ['expired_transactions' => 0, 'expired_points' => 0];
        }

        $expiredTransactions = 0;
        $expiredPoints = 0;

        if ($mode === 'program_end') {
            $endsAt = $this->settings->get('ends_at');
            if (! $endsAt || now()->lte($endsAt)) {
                return ['expired_transactions' => 0, 'expired_points' => 0];
            }

            Customer::query()
                ->where('loyalty_points_balance', '>', 0)
                ->chunkById(100, function ($customers) use (&$expiredTransactions, &$expiredPoints): void {
                    foreach ($customers as $customer) {
                        $points = (int) $customer->loyalty_points_balance;
                        if ($points <= 0) {
                            continue;
                        }

                        $this->debitPoints($customer, $points, 'expire', 'Istek bodova na kraju programa');
                        $expiredTransactions++;
                        $expiredPoints += $points;
                    }
                });

            return ['expired_transactions' => $expiredTransactions, 'expired_points' => $expiredPoints];
        }

        if ($mode === 'months_after_earn') {
            $months = max(1, (int) $this->settings->get('expiry_months', 12));
            $cutoff = now()->subMonths($months);

            LoyaltyTransaction::query()
                ->where('type', 'earn')
                ->where('created_at', '<=', $cutoff)
                ->where('points', '>', 0)
                ->orderBy('id')
                ->chunkById(200, function ($transactions) use (&$expiredTransactions, &$expiredPoints): void {
                    foreach ($transactions as $earn) {
                        $alreadyExpired = LoyaltyTransaction::query()
                            ->where('customer_id', $earn->customer_id)
                            ->where('type', 'expire')
                            ->where('order_id', $earn->order_id)
                            ->exists();

                        if ($alreadyExpired) {
                            continue;
                        }

                        $customer = Customer::query()->find($earn->customer_id);
                        if (! $customer) {
                            continue;
                        }

                        $points = min((int) $earn->points, $this->getBalance($customer));
                        if ($points <= 0) {
                            continue;
                        }

                        $this->debitPoints(
                            $customer,
                            $points,
                            'expire',
                            "Istek bodova za narudžbu #{$earn->order_id}",
                            $earn->order,
                        );

                        $expiredTransactions++;
                        $expiredPoints += $points;
                    }
                });
        }

        return ['expired_transactions' => $expiredTransactions, 'expired_points' => $expiredPoints];
    }

    private function customerCanUseReward(Customer $customer, LoyaltyReward $reward): bool
    {
        if ($reward->max_uses_per_customer !== null) {
            $used = LoyaltyRedemption::query()
                ->where('customer_id', $customer->id)
                ->where('loyalty_reward_id', $reward->id)
                ->whereIn('status', ['applied', 'available'])
                ->count();

            if ($used >= $reward->max_uses_per_customer) {
                return false;
            }
        }

        return true;
    }

    private function creditPoints(
        Customer $customer,
        int $points,
        string $type,
        string $description,
        ?Order $order = null,
        ?LoyaltyReward $reward = null,
        ?array $meta = null,
    ): LoyaltyTransaction {
        return DB::transaction(function () use ($customer, $points, $type, $description, $order, $reward, $meta): LoyaltyTransaction {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $newBalance = (int) $customer->loyalty_points_balance + $points;
            $customer->update(['loyalty_points_balance' => $newBalance]);

            return LoyaltyTransaction::query()->create([
                'customer_id' => $customer->id,
                'type' => $type,
                'points' => $points,
                'balance_after' => $newBalance,
                'order_id' => $order?->id,
                'loyalty_reward_id' => $reward?->id,
                'description' => $description,
                'meta' => $meta,
                'created_at' => now(),
            ]);
        });
    }

    private function debitPoints(
        Customer $customer,
        int $points,
        string $type,
        string $description,
        ?Order $order = null,
        ?LoyaltyReward $reward = null,
        ?array $meta = null,
    ): LoyaltyTransaction {
        return DB::transaction(function () use ($customer, $points, $type, $description, $order, $reward, $meta): LoyaltyTransaction {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $newBalance = max(0, (int) $customer->loyalty_points_balance - $points);
            $customer->update(['loyalty_points_balance' => $newBalance]);

            return LoyaltyTransaction::query()->create([
                'customer_id' => $customer->id,
                'type' => $type,
                'points' => -$points,
                'balance_after' => $newBalance,
                'order_id' => $order?->id,
                'loyalty_reward_id' => $reward?->id,
                'description' => $description,
                'meta' => $meta,
                'created_at' => now(),
            ]);
        });
    }

    private function sendPointsEarnedEmail(Customer $customer, Order $order, int $points): void
    {
        $email = $customer->user?->email;
        if (! $email) {
            return;
        }

        Mail::to($email)->queue(new LoyaltyNotificationMail(
            'loyalty_points_earned',
            $this->emailVariables($customer, [
                'first_name' => $order->first_name,
                'points_earned' => (string) $points,
                'order_number' => $order->order_number,
            ]),
            'Osvojili ste '.$points.' BNC bodova',
        ));
    }

    private function sendGuestRegisterPromptEmail(Order $order, int $points): void
    {
        if (! $order->email) {
            return;
        }

        Mail::to($order->email)->queue(new LoyaltyNotificationMail(
            'loyalty_guest_register_prompt',
            [
                'first_name' => $order->first_name,
                'points_earned' => (string) $points,
                'order_number' => $order->order_number,
                'register_url' => rtrim((string) config('bnc.frontend_url'), '/').'/nalog/registracija',
                'store_name' => (string) config('mail.from.name', 'BNC Shop'),
            ],
            'Registrujte se i preuzmite '.$points.' BNC bodova',
        ));
    }

    private function sendRewardUnlockedEmail(Customer $customer, LoyaltyReward $reward, int $balance): void
    {
        $email = $customer->user?->email;
        if (! $email) {
            return;
        }

        Mail::to($email)->queue(new LoyaltyNotificationMail(
            'loyalty_reward_unlocked',
            $this->emailVariables($customer, [
                'reward_name' => $reward->name,
                'reward_description' => $reward->description ?? $this->rewardDescription($reward),
                'points_required' => (string) $reward->points_required,
            ], $balance),
            'Dostupna vam je nagrada: '.$reward->name,
        ));
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function emailVariables(Customer $customer, array $extra = [], ?int $balance = null): array
    {
        return array_merge([
            'first_name' => $customer->user?->name ?? 'Kupac',
            'points_balance' => (string) ($balance ?? $this->getBalance($customer)),
            'account_url' => rtrim((string) config('bnc.frontend_url'), '/').'/nalog/bodovi',
            'register_url' => rtrim((string) config('bnc.frontend_url'), '/').'/nalog/registracija',
            'store_name' => (string) config('mail.from.name', 'BNC Shop'),
        ], $extra);
    }

    private function rewardDescription(LoyaltyReward $reward): string
    {
        return match ($reward->type) {
            'percentage' => (float) $reward->reward_value.'% popusta',
            'fixed' => number_format((float) $reward->reward_value, 2, ',', '.').' KM popusta',
            'free_product' => 'Besplatan proizvod: '.($reward->product?->name ?? ''),
            default => $reward->name,
        };
    }
}
