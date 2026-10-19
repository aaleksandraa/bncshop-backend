<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSellerOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('note')) {
            return;
        }

        $note = trim((string) $this->input('note'));
        $this->merge([
            'note' => $note === '' ? null : $note,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cancelling = $this->input('status') === 'otkazano';

        return [
            'status' => ['required', 'string', 'in:poslano,otkazano,spremno_za_preuzimanje,isporučeno'],
            'note' => [
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
            'note.required' => 'Razlog otkazivanja je obavezan.',
            'note.min' => 'Razlog otkazivanja mora imati najmanje 3 znaka.',
            'note.max' => 'Razlog otkazivanja može imati najviše 1000 znakova.',
        ];
    }
}
