<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Product;
use App\Services\Pricing\ProductSalePriceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSellerProductRequest extends FormRequest
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
            'description' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sale_price' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'sale_validity' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    ProductSalePriceService::VALIDITY_NO_END,
                    ProductSalePriceService::VALIDITY_UNTIL_DATE,
                    ProductSalePriceService::VALIDITY_UNTIL_STOCK,
                ]),
            ],
            'sale_ends_at' => ['sometimes', 'nullable', 'date'],
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
            $regularPrice = app(ProductSalePriceService::class)
                ->resolveEffectiveRegularPrice($product);

            if ($salePrice >= $regularPrice) {
                $validator->errors()->add(
                    'sale_price',
                    'Akcijska cijena mora biti manja od redovne cijene.',
                );
            }

            if ($this->input('sale_validity') === ProductSalePriceService::VALIDITY_UNTIL_DATE
                && ! $this->filled('sale_ends_at')) {
                $validator->errors()->add(
                    'sale_ends_at',
                    'Unesite datum isteka akcije.',
                );
            }
        });
    }
}
