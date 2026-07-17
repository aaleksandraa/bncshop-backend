<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoyaltyCard;
use App\Models\LoyaltyReward;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Loyalty\LoyaltyCardService;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoyaltyCardInStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\EmailTemplatesSeeder::class);

        SystemSetting::query()->create([
            'key' => 'loyalty',
            'value' => [
                'enabled' => true,
                'points_per_km' => 1,
            ],
            'group' => 'loyalty',
        ]);
    }

    private function createCustomer(): Customer
    {
        $user = User::createAccount([
            'name' => 'Kartica Test',
            'email' => 'card@test.test',
            'password' => Hash::make('password'),
            'is_customer' => true,
        ]);

        return Customer::query()->create(['user_id' => $user->id]);
    }

    public function test_issue_card_creates_unique_active_card(): void
    {
        Mail::fake();

        $staff = User::createAccount([
            'name' => 'Admin',
            'email' => 'admin@test.test',
            'password' => Hash::make('password'),
            'is_customer' => false,
        ]);

        $customer = $this->createCustomer();
        $card = app(LoyaltyCardService::class)->issueCard($customer, $staff);

        $this->assertSame('active', $card->status);
        $this->assertMatchesRegularExpression('/^BNC-\d{8}$/', $card->card_number);
        $this->assertSame($customer->id, $customer->fresh()->activeLoyaltyCard()?->customer_id);
    }

    public function test_lookup_by_card_number_finds_customer(): void
    {
        Mail::fake();

        $staff = User::createAccount([
            'name' => 'Admin',
            'email' => 'admin2@test.test',
            'password' => Hash::make('password'),
            'is_customer' => false,
        ]);

        $customer = $this->createCustomer();
        $card = app(LoyaltyCardService::class)->issueCard($customer, $staff);

        $found = app(LoyaltyCardService::class)->lookupByCardNumber($card->card_number);

        $this->assertNotNull($found);
        $this->assertSame($customer->id, $found->id);
    }

    public function test_replace_card_marks_old_as_replaced(): void
    {
        Mail::fake();

        $staff = User::createAccount([
            'name' => 'Admin',
            'email' => 'admin3@test.test',
            'password' => Hash::make('password'),
            'is_customer' => false,
        ]);

        $customer = $this->createCustomer();
        $service = app(LoyaltyCardService::class);
        $oldCard = $service->issueCard($customer, $staff);
        $newCard = $service->replaceCard($oldCard, $staff);

        $this->assertSame('replaced', $oldCard->fresh()->status);
        $this->assertSame('active', $newCard->status);
        $this->assertSame($newCard->id, $customer->fresh()->activeLoyaltyCard()?->id);
    }

    public function test_in_store_sale_awards_points_with_receipt(): void
    {
        $customer = $this->createCustomer();

        $transaction = app(LoyaltyService::class)->awardForInStoreSale($customer, 75.50, [
            'receipt_number' => 'RAC-1001',
            'staff_user_id' => 1,
        ]);

        $this->assertSame(75, $transaction->points);
        $this->assertSame(75, $customer->fresh()->loyalty_points_balance);
        $this->assertSame('earn_in_store', $transaction->type);
    }

    public function test_in_store_redeem_deducts_points(): void
    {
        Mail::fake();

        $customer = $this->createCustomer();
        $customer->update(['loyalty_points_balance' => 200]);

        $reward = LoyaltyReward::query()->create([
            'name' => '10% popust',
            'type' => 'percentage',
            'points_required' => 100,
            'reward_value' => 10,
            'is_active' => true,
        ]);

        app(LoyaltyService::class)->redeemInStore($customer, $reward, [
            'receipt_number' => 'RAC-2002',
            'staff_user_id' => 1,
        ]);

        $this->assertSame(100, $customer->fresh()->loyalty_points_balance);
    }

    public function test_duplicate_receipt_number_is_rejected(): void
    {
        $customer = $this->createCustomer();
        $service = app(LoyaltyService::class);

        $service->awardForInStoreSale($customer, 50, ['receipt_number' => 'RAC-DUP-1']);

        $this->expectException(\RuntimeException::class);
        $service->awardForInStoreSale($customer, 30, ['receipt_number' => 'RAC-DUP-1']);
    }
}
