<?php

namespace App\Services\B2b;

use App\Models\B2bAccessRequest;
use App\Models\B2bCustomer;
use App\Models\B2bPasswordSetupToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class B2bCustomerProvisioner
{
    public function __construct(
        private readonly B2bAccessMailer $accessMailer,
    ) {}

    public function approveAccessRequest(B2bAccessRequest $request, ?User $reviewer = null): B2bCustomer
    {
        if (! $request->isPending()) {
            throw new \RuntimeException('Zahtjev je već obrađen.');
        }

        if (User::query()->where('email', $request->email)->exists()) {
            throw new \RuntimeException('Korisnik sa ovim emailom već postoji.');
        }

        $customer = DB::transaction(function () use ($request, $reviewer): B2bCustomer {
            $user = User::createAccount([
                'name' => $request->fullName(),
                'email' => $request->email,
                'password' => Hash::make(Str::random(32)),
                'phone' => $request->phone,
                'email_verified_at' => now(),
                'is_customer' => false,
                'is_b2b_customer' => true,
            ]);

            $customer = B2bCustomer::query()->create([
                'user_id' => $user->id,
                'company_name' => $request->company_name,
                'company_address' => $request->company_address,
                'jib' => $request->jib,
                'pdv_number' => $request->pdv_number,
                'phone' => $request->phone,
                'discount_percent' => null,
                'is_active' => true,
                'created_by' => $reviewer?->id,
            ]);

            $request->update([
                'status' => 'approved',
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now(),
            ]);

            return $customer->load('user');
        });

        $this->sendPasswordSetupEmail($customer->user);

        return $customer;
    }

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     phone: string,
     *     company_name: string,
     *     company_address: string,
     *     jib: string,
     *     pdv_number?: string|null,
     *     discount_percent?: float|null
     * }  $data
     */
    public function createCustomer(array $data, ?User $creator = null, bool $sendPasswordEmail = true): B2bCustomer
    {
        if (User::query()->where('email', $data['email'])->exists()) {
            throw new \RuntimeException('Korisnik sa ovim emailom već postoji.');
        }

        $customer = DB::transaction(function () use ($data, $creator): B2bCustomer {
            $user = User::createAccount([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make(Str::random(32)),
                'phone' => $data['phone'],
                'email_verified_at' => now(),
                'is_customer' => false,
                'is_b2b_customer' => true,
            ]);

            return B2bCustomer::query()->create([
                'user_id' => $user->id,
                'company_name' => $data['company_name'],
                'company_address' => $data['company_address'],
                'jib' => $data['jib'],
                'pdv_number' => $data['pdv_number'] ?? null,
                'phone' => $data['phone'],
                'discount_percent' => $data['discount_percent'] ?? null,
                'is_active' => true,
                'created_by' => $creator?->id,
            ])->load('user');
        });

        if ($sendPasswordEmail) {
            $this->sendPasswordSetupEmail($customer->user);
        }

        return $customer;
    }

    public function sendPasswordSetupEmail(User $user, bool $force = false): ?string
    {
        if (! $force) {
            $recentTokenExists = B2bPasswordSetupToken::query()
                ->where('user_id', $user->id)
                ->where('expires_at', '>', now())
                ->where('created_at', '>=', now()->subMinutes(5))
                ->exists();

            if ($recentTokenExists) {
                Log::info('B2B password setup email skipped: recent valid token already issued', [
                    'user_id' => $user->id,
                ]);

                return null;
            }
        }

        $plainToken = B2bPasswordSetupToken::createForUser($user);
        $setupUrl = rtrim((string) config('bnc.frontend_url'), '/').'/b2b/postavi-lozinku?token='.$plainToken;

        $this->accessMailer->sendAccessApproved($user, $setupUrl);

        return $plainToken;
    }
}
