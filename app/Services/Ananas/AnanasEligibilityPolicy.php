<?php

namespace App\Services\Ananas;

use App\Models\Product;
use App\Models\ProductAttributeValue;
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

    public const WEIGHT_UNPARSEABLE = 'WEIGHT_UNPARSEABLE';

    public const VAT_UNRESOLVED = 'VAT_UNRESOLVED';

    public const INVALID_PRICE = 'INVALID_PRICE';

    public const ZERO_STOCK = 'ZERO_STOCK';

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

    /** @var array<string, true>|null normalized EAN → duplicate in active public catalog */
    private ?array $duplicateNormalizedEans = null;

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

        $ean = $this->resolveEan($product);

        if ($ean === null) {
            $raw = trim((string) ($product->barcode ?? ''));

            return AnanasEligibilityResult::notEligible(
                $raw === '' ? self::MISSING_EAN : self::INVALID_EAN,
            );
        }

        if ($this->hasDuplicateEan($product, $ean)) {
            return AnanasEligibilityResult::notEligible(self::DUPLICATE_EAN);
        }

        if (! $this->hasActiveCoverImage($product)) {
            return AnanasEligibilityResult::notEligible(self::MISSING_IMAGE);
        }

        $weight = $this->packageWeightResolver->resolve($product);

        if (! $weight->isOk()) {
            $weightCode = match ($weight->parseStatus) {
                AnanasPackageWeightResult::STATUS_UNPARSEABLE,
                AnanasPackageWeightResult::STATUS_UNITLESS_AMBIGUOUS,
                AnanasPackageWeightResult::STATUS_ZERO_OR_NEGATIVE => self::WEIGHT_UNPARSEABLE,
                default => self::MISSING_WEIGHT,
            };

            return AnanasEligibilityResult::notEligible($weightCode);
        }

        $regularPrice = $this->priceCalculator->calculate($product)->regularPrice;

        if ($regularPrice <= 0) {
            return AnanasEligibilityResult::notEligible(self::INVALID_PRICE);
        }

        if ((int) $product->available_stock <= 0) {
            return AnanasEligibilityResult::notEligible(self::ZERO_STOCK);
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

    /**
     * EAN-8 / EAN-13 for Ananas. Accepts spaces/dashes, UPC-12 (padded), or EAN on a spec attribute.
     */
    public function resolveEan(Product $product): ?string
    {
        $fromBarcode = $this->normalizeEan($product->barcode);

        if ($fromBarcode !== null && $this->isValidEan($fromBarcode)) {
            return $fromBarcode;
        }

        $product->loadMissing(['attributeValues.attributeDefinition']);

        foreach ($product->attributeValues as $value) {
            if (! $value instanceof ProductAttributeValue) {
                continue;
            }

            if (! $this->isEanAttribute($value)) {
                continue;
            }

            $fromAttribute = $this->normalizeEan((string) $value->raw_value);

            if ($fromAttribute !== null && $this->isValidEan($fromAttribute)) {
                return $fromAttribute;
            }
        }

        return null;
    }

    public function normalizeEan(?string $barcode): ?string
    {
        if ($barcode === null) {
            return null;
        }

        $trimmed = trim(str_replace("\u{00A0}", ' ', $barcode));

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^[\d\s\-]+$/', $trimmed) === 1) {
            $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

            if (strlen($digits) === 12) {
                $digits = '0'.$digits;
            }

            return $digits === '' ? null : $digits;
        }

        return $trimmed;
    }

    private function isValidEan(string $ean): bool
    {
        return (bool) preg_match('/^\d{8}$/', $ean) || (bool) preg_match('/^\d{13}$/', $ean);
    }

    private function isEanAttribute(ProductAttributeValue $value): bool
    {
        $definition = $value->attributeDefinition;
        $label = trim((string) (
            $value->attribute_name_snapshot
            ?: $definition?->display_name
            ?: $definition?->name
            ?: ''
        ));

        return (bool) preg_match('/^(ean|ean-?13|ean\s*kod|barkod|barcode|gtin|gtin-?13)$/iu', $label);
    }

    /**
     * One catalog pass so eligibility reports do not run EXISTS per SKU.
     */
    public function warmDuplicateEanIndex(): void
    {
        $counts = [];

        Product::query()
            ->where('is_public', true)
            ->where('status', 'active')
            ->select(['id', 'barcode'])
            ->orderBy('id')
            ->chunkById(1000, function ($products) use (&$counts): void {
                foreach ($products as $product) {
                    if (! $product instanceof Product) {
                        continue;
                    }

                    $ean = $this->normalizeEan($product->barcode);

                    if ($ean === null || ! $this->isValidEan($ean)) {
                        continue;
                    }

                    $counts[$ean] = ($counts[$ean] ?? 0) + 1;
                }
            });

        $this->duplicateNormalizedEans = [];

        foreach ($counts as $ean => $count) {
            if ($count > 1) {
                $this->duplicateNormalizedEans[$ean] = true;
            }
        }
    }

    private function hasDuplicateEan(Product $product, string $ean): bool
    {
        if ($this->duplicateNormalizedEans !== null) {
            return isset($this->duplicateNormalizedEans[$ean]);
        }

        $candidates = array_values(array_unique(array_filter([
            $ean,
            strlen($ean) === 13 && str_starts_with($ean, '0') ? substr($ean, 1) : null,
        ])));

        return Product::query()
            ->where('is_public', true)
            ->where('status', 'active')
            ->whereKeyNot($product->id)
            ->whereIn('barcode', $candidates)
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
