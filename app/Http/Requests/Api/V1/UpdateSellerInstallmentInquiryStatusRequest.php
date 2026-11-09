<?php

namespace App\Http\Requests\Api\V1;

use App\Models\InstallmentInquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSellerInstallmentInquiryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_keys(InstallmentInquiry::statusOptions()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status je obavezan.',
            'status.in' => 'Odabrani status nije dozvoljen.',
        ];
    }
}
