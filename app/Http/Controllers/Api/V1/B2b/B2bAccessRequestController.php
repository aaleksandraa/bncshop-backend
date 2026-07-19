<?php

namespace App\Http\Controllers\Api\V1\B2b;

use App\Http\Controllers\Api\V1\B2b\Concerns\FormatsB2bResponses;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Concerns\ValidatesBotProtection;
use App\Mail\B2b\B2bAccessRequestNotification;
use App\Models\B2bAccessRequest;
use App\Models\B2bCustomer;
use App\Models\B2bSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class B2bAccessRequestController extends Controller
{
    use FormatsB2bResponses;
    use RespondsWithJson;
    use ValidatesBotProtection;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'company_address' => ['required', 'string', 'max:1000'],
            'jib' => ['required', 'string', 'max:50'],
            'pdv_number' => ['nullable', 'string', 'max:50'],
            ...$this->botProtectionRules(),
        ]);

        if (User::query()->where('email', $validated['email'])->exists()) {
            return $this->error('Korisnik sa ovim emailom već postoji.', 422);
        }

        if (B2bCustomer::query()->where('jib', $validated['jib'])->exists()) {
            return $this->error('Firma sa ovim JIB-om je već registrovana.', 422);
        }

        $existingPending = B2bAccessRequest::query()
            ->where('status', 'pending')
            ->where(function ($query) use ($validated): void {
                $query->where('email', $validated['email'])
                    ->orWhere('jib', $validated['jib']);
            })
            ->exists();

        if ($existingPending) {
            return $this->error('Već postoji aktivan zahtjev za pristup sa ovim emailom ili JIB-om.', 422);
        }

        $accessRequest = B2bAccessRequest::query()->create($validated + ['status' => 'pending']);

        Mail::to(B2bSetting::adminNotificationEmail())
            ->send(new B2bAccessRequestNotification($accessRequest));

        return $this->success([
            'message' => 'Zahtjev je uspješno poslan. Kontaktiraćemo vas nakon odobrenja.',
        ], [], 201);
    }
}
