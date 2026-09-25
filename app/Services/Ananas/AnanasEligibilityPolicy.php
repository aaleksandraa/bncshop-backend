<?php

namespace App\Services\Ananas;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Pricing\PriceCalculator;
use RuntimeException;

class AnanasEligibilityPolicy
{
    public const REFURBISHED_OR_USED = 'REFURBISHED_OR_USED';

    public const SET_PRODUCT = 'SET_PRODUCT';

    public const CATEGORY_UNMAPPED = 'CATEGORY_UNMAPPED';

    public const ALREADY_EXPORTED = 'ALREADY_EXPORTED';

    public const MISSING_EAN = 'MISSING_EAN';

    public const INVALID_EAN = 'INVALID_EAN';

    public const DUPLICATE_EAN = 'DUPLICATE_EAN';

    public const MISSING_IMAGE = 'MISSING_IMAGE';

    public const MISSING_WEIGHT = 'MISSING_WEIGHT';

    public const VAT_UNRESOLVED = 'VAT_UNRESOLVED';

    public const INVALID_PRICE = 'INVALID_PRICE';

    /**
     * Docs list 0/10/20. Stage QA2 for this BiH merchant accepted only 17 on PUT bulk.
     * The number is a tax-rate tag — it does not add VAT on top of BNC basePrice.
     * vat=0 is remapped to 17 in resolvedVatRate() so import of ~2000 does not repeat the PUT rejection.
     *
     * @var list<int>
     */
    public const ALLOWED_VAT_RATES = [0, 10, 17, 20];

    public const MERCHANT_VAT_RATE = 17;

    public function __construct(
        private readonly AnanasExportScope $exportScope,
        private readonly AnanasPackageWeightResolver $packageWeightResolver,
        private readonly PriceCalculator $priceCalculator,
        private readonly AnanasSyncSettings $settings,
    ) {}

    public function evaluateHardExclusions(Product $product): AnanasEligibilityResult
    {
        if ((bool) $product->is_refurbished) {
            return AnanasEligibilityResult::notEligible(self::REFURBISHED_OR_USED);
        }

        if ((bool) $product->is_set) {
            return AnanasEligibilityResult::notEligible(self::SET_PRODUCT);
        }

        return AnanasEligibilityResult::eligible();
    }

    public function evaluate(Product $product): AnanasEligibilityResult
    {
        $hard = $this->evaluateHardExclusions($product);

        if (! $hard->eligible) {
            return $hard;
        }

        if (! $product->is_public || $product->status !== 'active') {
            return AnanasEligibilityResult::notEligible(self::CATEGORY_UNMAPPED);
        }

        if ($this->exportScope->resolveCategoryMapping($product) === null) {
            return AnanasEligibilityResult::notEligible(self::CATEGORY_UNMAPPED);
        }

        return $this->evaluateProductData($product);
    }

    public function evaluateProductData(Product $product): AnanasEligibilityResult
    {
        $hard = $this->evaluateHardExclusions($product);

        if (! $hard->eligible) {
            return $hard;
        }

        $ean = $this->normalizeEan($product->barcode);

        if ($ean === null) {
            return AnanasEligibilityResult::notEligible(self::MISSING_EAN);
        }

        if (! $this->isValidEan($ean)) {
            return AnanasEligibilityResult::notEligible(self::INVALID_EAN);
        }

        if ($this->hasDuplicateEan($product, $ean)) {
            return AnanasEligibilityResult::notEligible(self::DUPLICATE_EAN);
        }

        if (! $this->hasActiveCoverImage($product)) {
            return AnanasEligibilityResult::notEligible(self::MISSING_IMAGE);
        }

        $weight = $this->packageWeightResolver->resolve($product);

        if (! $weight->isOk()) {
            return AnanasEligibilityResult::notEligible(self::MISSING_WEIGHT);
        }

        $regularPrice = $this->priceCalculator->calculate($product)->regularPrice;

        if ($regularPrice <= 0) {
            return AnanasEligibilityResult::notEligible(self::INVALID_PRICE);
        }

        if ($this->resolvedVatRate() === null) {
            return AnanasEligibilityResult::notEligible(self::VAT_UNRESOLVED);
        }

        return AnanasEligibilityResult::eligible();
    }

    public function assertCanExport(Product $product): void
    {
        $result = $this->evaluate($product);

        if (! $result->eligible) {
            throw new RuntimeException(sprintf(
                'Product %d is not eligible for Ananas export: %s',
                $product->id,
                $result->reasonCode ?? 'NOT_ELIGIBLE',
            ));
        }
    }

    public function resolvedVatRate(): ?int
    {
        $fromConfig = config('bnc.ananas_vat_rate');

        $rate = null;

        if ($fromConfig !== null && $fromConfig !== '') {
            $rate = $this->normalizeVatRate($fromConfig);
        } else {
            $rate = $this->normalizeVatRate($this->settings->all()['vat_rate'] ?? null);
        }

        if ($rate === 0) {
            return self::MERCHANT_VAT_RATE;
        }

        return $rate;
    }

    public function normalizeVatRate(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $rate = (int) $value;

        return in_array($rate, self::ALLOWED_VAT_RATES, true) ? $rate : null;
    }

    private function normalizeEan(?string $barcode): ?string
    {
        if ($barcode === null) {
            return null;
        }

        $trimmed = trim($barcode);

        return $trimmed === '' ? null : $trimmed;
    }

    private function isValidEan(string $ean): bool
    {
        return (bool) preg_match('/^\d{8}$/', $ean) || (bool) preg_match('/^\d{13}$/', $ean);
    }

    private function hasDuplicateEan(Product $product, string $ean): bool
    {
        return Product::query()
            ->where('is_public', true)
            ->where('status', 'active')
            ->where('barcode', $ean)
            ->whereKeyNot($product->id)
            ->exists();
    }

    private function hasActiveCoverImage(Product $product): bool
    {
        $images = $product->relationLoaded('images')
            ? $product->images
            : $product->images()->where('status', 'active')->orderByDesc('is_primary')->orderBy('sort_order')->get();

        foreach ($images as $image) {
            if ($image->status !== 'active') {
                continue;
            }

            if ($image instanceof ProductImage && filled($image->resolvedUrl())) {
                return true;
            }
        }

        return false;
    }
}
