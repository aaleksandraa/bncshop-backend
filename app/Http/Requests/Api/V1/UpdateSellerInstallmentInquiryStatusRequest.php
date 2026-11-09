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

    protected function prepareForValidation(): void
    {
        if (! $this->exists('cancellation_reason')) {
            return;
        }

        $reason = trim((string) $this->input('cancellation_reason'));
        $this->merge([
            'cancellation_reason' => $reason === '' ? null : $reason,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cancelling = $this->input('status') === InstallmentInquiry::STATUS_CANCELLED;

        return [
            'status' => ['required', 'string', Rule::in(array_keys(InstallmentInquiry::statusOptions()))],
            'cancellation_reason' => [
                'nullable',
                'string',
                'max:1000',
                Rule::when($cancelling, ['required', 'min:3']),
            ],
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
            'cancellation_reason.required' => 'Razlog otkazivanja je obavezan.',
            'cancellation_reason.min' => 'Razlog otkazivanja mora imati najmanje 3 znaka.',
            'cancellation_reason.max' => 'Razlog otkazivanja može imati najviše 1000 znakova.',
        ];
    }
}
