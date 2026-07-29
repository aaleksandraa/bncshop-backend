<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSellerCatalogProductRequest extends FormRequest
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
            'primary_image_id' => ['required', 'integer', 'exists:product_images,id'],
        ];
    }
}
