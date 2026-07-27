<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadSellerProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEditSellerElineProducts() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'max:5120'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
