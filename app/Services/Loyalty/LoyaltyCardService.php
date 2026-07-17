<?php

namespace App\Services\Loyalty;

use App\Mail\LoyaltyNotificationMail;
use App\Models\Customer;
use App\Models\LoyaltyCard;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class LoyaltyCardService
{
    public function normalizeCardNumber(string $number): string
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', trim($number)) ?? '');

        if (preg_match('/^BNC-?(\d+)$/', $normalized, $matches)) {
            return 'BNC-'.str_pad($matches[1], 8, '0', STR_PAD_LEFT);
        }

        return $normalized;
    }

    public function generateCardNumber(): string
    {
        do {
            $number = 'BNC-'.str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
        } while (LoyaltyCard::query()->where('card_number', $number)->exists());

        return $number;
    }

    public function issueCard(Customer $customer, User $staff, ?string $notes = null): LoyaltyCard
    {
        $customer->loadMissing('user');

        if (! $customer->user?->email) {
            throw new RuntimeException('Kartica se može izdati samo registrovanom kupcu s e-mailom.');
        }

        return DB::transaction(function () use ($customer, $staff, $notes): LoyaltyCard {
            $this->deactivateActiveCards($customer, 'replaced');

            $card = LoyaltyCard::query()->create([
                'customer_id' => $customer->id,
                'card_number' => $this->generateCardNumber(),
                'status' => 'active',
                'issued_at' => now(),
                'issued_by' => $staff->id,
                'notes' => $notes,
            ]);

            $this->sendCardIssuedEmail($customer, $card);

            return $card;
        });
    }

    public function replaceCard(LoyaltyCard $card, User $staff, ?string $notes = null): LoyaltyCard
    {
        return DB::transaction(function () use ($card, $staff, $notes): LoyaltyCard {
            $customer = $card->customer()->lockForUpdate()->firstOrFail();

            $card->update([
                'status' => 'replaced',
                'blocked_at' => now(),
                'block_reason' => 'Zamjena kartice',
            ]);

            $newCard = LoyaltyCard::query()->create([
                'customer_id' => $customer->id,
                'card_number' => $this->generateCardNumber(),
                'status' => 'active',
                'issued_at' => now(),
                'issued_by' => $staff->id,
                'notes' => $notes,
            ]);

            $card->update(['replaced_by_card_id' => $newCard->id]);

            $this->sendCardIssuedEmail($customer, $newCard);

            return $newCard;
        });
    }

    public function blockCard(LoyaltyCard $card, string $reason, string $status = 'blocked'): LoyaltyCard
    {
        if (! in_array($status, ['blocked', 'lost'], true)) {
            throw new RuntimeException('Neispravan status blokade kartice.');
        }

        $card->update([
            'status' => $status,
            'blocked_at' => now(),
            'block_reason' => $reason,
        ]);

        return $card->fresh();
    }

    public function lookupByCardNumber(string $number): ?Customer
    {
        $normalized = $this->normalizeCardNumber($number);

        $card = LoyaltyCard::query()
            ->with('customer.user')
            ->where('card_number', $normalized)
            ->where('status', 'active')
            ->first();

        return $card?->customer;
    }

    public function findCustomerForInStore(?string $cardNumber = null, ?string $email = null, ?string $phone = null): ?Customer
    {
        if ($cardNumber) {
            $customer = $this->lookupByCardNumber($cardNumber);
            if ($customer) {
                return $customer->load('user');
            }
        }

        if ($email) {
            $customer = Customer::query()
                ->with('user')
                ->whereHas('user', fn ($query) => $query->where('email', strtolower(trim($email))))
                ->first();

            if ($customer) {
                return $customer;
            }
        }

        if ($phone) {
            $normalizedPhone = preg_replace('/\D+/', '', $phone) ?? '';

            if ($normalizedPhone !== '') {
                return Customer::query()
                    ->with('user')
                    ->where(function ($query) use ($phone, $normalizedPhone): void {
                        $query->where('phone', $phone)
                            ->orWhere('phone', 'like', '%'.$normalizedPhone);
                    })
                    ->orWhereHas('user', fn ($query) => $query->where('phone', $phone))
                    ->first();
            }
        }

        return null;
    }

    private function deactivateActiveCards(Customer $customer, string $status): void
    {
        LoyaltyCard::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->update([
                'status' => $status,
                'blocked_at' => now(),
                'block_reason' => 'Nova kartica izdata',
            ]);
    }

    private function sendCardIssuedEmail(Customer $customer, LoyaltyCard $card): void
    {
        $email = $customer->user?->email;
        if (! $email) {
            return;
        }

        Mail::to($email)->queue(new LoyaltyNotificationMail(
            'loyalty_card_issued',
            [
                'first_name' => $customer->user?->name ?? 'Kupac',
                'card_number' => $card->card_number,
                'account_url' => rtrim((string) config('bnc.frontend_url'), '/').'/nalog/bodovi',
                'store_name' => (string) config('mail.from.name', 'BNC Shop'),
            ],
            'Vaša BNC loyalty kartica je aktivna',
        ));
    }
}
