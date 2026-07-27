<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSellerProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('seller.edit_eline_products') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sale_price' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'primary_image_id' => ['sometimes', 'nullable', 'integer', 'exists:product_images,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('sale_price') || $this->input('sale_price') === null) {
                return;
            }

            $product = Product::query()
                ->fromEline()
                ->find($this->route('id'));

            if ($product === null) {
                return;
            }

            $salePrice = (float) $this->input('sale_price');
            $regularPrice = (float) $product->regular_price;

            if ($salePrice >= $regularPrice) {
                $validator->errors()->add(
                    'sale_price',
                    'Akcijska cijena mora biti manja od redovne cijene.',
                );
            }
        });
    }
}
