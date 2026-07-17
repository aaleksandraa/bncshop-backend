<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesBotProtection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    use ValidatesBotProtection;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $checkoutSettings = app(\App\Services\Commerce\CheckoutSettings::class);
        $paymentMethods = $checkoutSettings->get('payment_methods', ['pay_on_delivery', 'bank_transfer']);
        $shippingMethods = $checkoutSettings->get('shipping_methods', ['delivery', 'pickup']);

        if (! is_array($paymentMethods) || $paymentMethods === []) {
            $paymentMethods = ['pay_on_delivery', 'bank_transfer'];
        }

        if (! is_array($shippingMethods) || $shippingMethods === []) {
            $shippingMethods = ['delivery', 'pickup'];
        }

        return array_merge($this->botProtectionRules(), [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255', 'required_with:jib,pdv_number'],
            'jib' => ['nullable', 'string', 'max:20', 'required_with:company_name'],
            'pdv_number' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'accepted_terms' => ['required', 'accepted'],
            'create_account' => ['nullable', 'boolean'],
            'password' => ['nullable', 'required_if:create_account,true', 'string', 'min:8', 'confirmed'],
            'shipping_method' => ['required', 'string', Rule::in($shippingMethods)],
            'payment_method' => ['nullable', 'string', Rule::in($paymentMethods)],
        ]);
    }
}
