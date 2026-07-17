<?php

namespace App\Services\B2b;

use App\Mail\B2b\B2bAccessApprovedMail;
use App\Models\B2bAccessRequest;
use App\Models\B2bCustomer;
use App\Models\B2bPasswordSetupToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class B2bCustomerProvisioner
{
    public function approveAccessRequest(B2bAccessRequest $request, ?User $reviewer = null): B2bCustomer
    {
        if (! $request->isPending()) {
            throw new \RuntimeException('Zahtjev je već obrađen.');
        }

        if (User::query()->where('email', $request->email)->exists()) {
            throw new \RuntimeException('Korisnik sa ovim emailom već postoji.');
        }

        return DB::transaction(function () use ($request, $reviewer): B2bCustomer {
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

            $this->sendPasswordSetupEmail($user);

            return $customer;
        });
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
    public function createCustomer(array $data, ?User $creator = null): B2bCustomer
    {
        if (User::query()->where('email', $data['email'])->exists()) {
            throw new \RuntimeException('Korisnik sa ovim emailom već postoji.');
        }

        return DB::transaction(function () use ($data, $creator): B2bCustomer {
            $user = User::createAccount([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make(Str::random(32)),
                'phone' => $data['phone'],
                'email_verified_at' => now(),
                'is_customer' => false,
                'is_b2b_customer' => true,
            ]);

            $customer = B2bCustomer::query()->create([
                'user_id' => $user->id,
                'company_name' => $data['company_name'],
                'company_address' => $data['company_address'],
                'jib' => $data['jib'],
                'pdv_number' => $data['pdv_number'] ?? null,
                'phone' => $data['phone'],
                'discount_percent' => $data['discount_percent'] ?? null,
                'is_active' => true,
                'created_by' => $creator?->id,
            ]);

            $this->sendPasswordSetupEmail($user);

            return $customer;
        });
    }

    public function sendPasswordSetupEmail(User $user): string
    {
        $plainToken = B2bPasswordSetupToken::createForUser($user);
        $setupUrl = rtrim((string) config('bnc.frontend_url'), '/').'/b2b/postavi-lozinku?token='.$plainToken;

        Mail::to($user->email)->queue(new B2bAccessApprovedMail($user, $setupUrl));

        return $plainToken;
    }
}
