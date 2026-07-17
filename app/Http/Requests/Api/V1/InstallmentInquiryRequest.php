<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesBotProtection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstallmentInquiryRequest extends FormRequest
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
        return array_merge($this->botProtectionRules(), [
            'first_name' => ['required', 'string', 'min:2', 'max:100'],
            'last_name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'min:8', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'product_slug' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'installment_type' => ['required', 'string', Rule::in(['mikrofin', 'shopping_card'])],
            'months' => ['required', 'integer', 'min:1', 'max:36'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'Ime je obavezno.',
            'last_name.required' => 'Prezime je obavezno.',
            'phone.required' => 'Telefon je obavezan.',
            'email.required' => 'Email je obavezan.',
            'email.email' => 'Unesite ispravnu email adresu.',
            'installment_type.required' => 'Odaberite plan otplate.',
            'months.required' => 'Odaberite broj rata.',
        ];
    }
}
