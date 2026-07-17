<?php

namespace App\Http\Controllers\Api\V1\B2b;

use App\Http\Controllers\Api\V1\B2b\Concerns\FormatsB2bResponses;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class B2bProfileController extends Controller
{
    use FormatsB2bResponses;
    use RespondsWithJson;

    public function show(Request $request): JsonResponse
    {
        return $this->success($this->formatCustomer($this->b2bCustomer($request)));
    }

    public function update(Request $request): JsonResponse
    {
        $customer = $this->b2bCustomer($request);
        $user = $customer->user;

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'string', 'max:50'],
            'company_address' => ['sometimes', 'string', 'max:1000'],
            'pdv_number' => ['nullable', 'string', 'max:50'],
        ]);

        $user->update(collect($validated)->only(['name', 'email', 'phone'])->filter()->all());

        $customerFields = collect($validated)->only([
            'phone',
            'company_address',
        ])->filter()->all();

        if (array_key_exists('pdv_number', $validated)) {
            $customerFields['pdv_number'] = $validated['pdv_number'];
        }

        $customer->update($customerFields);

        $customer->load('user');

        return $this->success($this->formatCustomer($customer));
    }
}
