<?php

namespace App\Services\Eline;

use App\Models\ApiImportJob;
use App\Models\ApiSource;
use App\Models\ElineCategoryMapping;
use App\Models\ElineProductOverride;
use App\Models\Product;
use App\Services\Pricing\PriceCalculator;
use App\Services\Sync\FieldLockService;
use App\Services\Sync\ImportJobChangeLogger;
use App\Services\Sync\ProductImportChangeTracker;
use App\Services\Sync\ProductUpsertResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ElineProductImporter
{
    public function __construct(
        private readonly FieldLockService $fieldLockService,
        private readonly PriceCalculator $priceCalculator,
    ) {}

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  Collection<string, ElineCategoryMapping>  $mappingsByCategory
     * @return array{created: int, updated: int, skipped: int, errors: array<int, string>}
     */
    public function importMany(
        Collection $items,
        Collection $mappingsByCategory,
        ApiSource $source,
        ?array $allFeedSifre = null,
        ?ApiImportJob $job = null,
        ?ImportJobChangeLogger $changeLogger = null,
    ): array {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($items as $item) {
            try {
                $result = $this->importOne($item, $mappingsByCategory, $source);

                if ($result === null) {
                    $stats['skipped']++;

                    continue;
                }

                match ($result->action) {
                    'inserted' => $stats['created']++,
                    'updated' => $stats['updated']++,
                    default => $stats['skipped']++,
                };

                if ($job !== null && $changeLogger !== null) {
                    $changeLogger->log($result, $job);
                }
            } catch (\Throwable $e) {
                $stats['errors'][] = sprintf(
                    'sifra %s: %s',
                    (string) ($item['sifra'] ?? '?'),
                    $e->getMessage(),
                );

                if ($job !== null && $changeLogger !== null) {
                    $changeLogger->logError(
                        ElineSupport::externalProductId((string) ($item['sifra'] ?? 'unknown')),
                        $e->getMessage(),
                        $job,
                    );
                }
            }
        }

        if ($changeLogger !== null) {
            $changeLogger->flush();
        }

        if ($allFeedSifre !== null) {
            $this->markMissingProducts($allFeedSifre);
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Collection<string, ElineCategoryMapping>  $mappingsByCategory
     */
    public function importOne(
        array $item,
        Collection $mappingsByCategory,
        ApiSource $source,
    ): ?ProductUpsertResult {
        $sifra = (string) $item['sifra'];
        $elineCategory = trim((string) ($item['eline_category'] ?? ''));

        if ($elineCategory === '') {
            return null;
        }

        /** @var ElineCategoryMapping|null $mapping */
        $mapping = $mappingsByCategory->get($elineCategory);

        if ($mapping === null || ! $mapping->is_enabled || $mapping->category_id === null) {
            return null;
        }

        $override = ElineProductOverride::query()->where('eline_sifra', $sifra)->first();

        if ($override !== null && ! $override->is_enabled) {
            return null;
        }

        $condition = $override?->product_condition
            ?? $mapping->product_condition
            ?? ElineCategoryMapping::CONDITION_REFURBISHED;

        $categoryId = $override?->category_id ?? $mapping->category_id;
        $externalId = ElineSupport::externalProductId($sifra);

        $product = Product::query()->firstOrNew(['external_product_id' => $externalId]);
        $wasExisting = $product->exists;
        $snapshot = $wasExisting ? ProductImportChangeTracker::snapshot($product) : [];

        if (! $wasExisting) {
            $product->first_imported_at = now();
        }

        $mpc = $item['mpc'];
        $stanje = (int) ($item['stanje'] ?? 0);
        $isArticleActive = ElineSupport::isActive($item['aktivan'] ?? null);
        $isPriceActive = $item['price_aktivan'] === null || ElineSupport::isActive($item['price_aktivan']);
        $description = (string) ($item['opis'] ?? '');
        $shortDescription = Str::limit($description, 255, '');

        $product->fill([
            'import_source' => 'eline',
            'eline_sifra' => $sifra,
            'eline_feed_hash' => ElineSupport::feedHash($item),
            'sku' => $sifra,
            'api_source_id' => $source->id,
            'name' => (string) ($item['naziv'] ?? $sifra),
            'slug' => $this->resolveSlug($product, (string) ($item['naziv'] ?? $sifra)),
            'category_id' => $categoryId,
            'is_refurbished' => $condition === ElineCategoryMapping::CONDITION_REFURBISHED,
            'is_new' => $condition === ElineCategoryMapping::CONDITION_NEW,
            'is_gaming' => false,
            'is_public' => $mapping->is_enabled && $isArticleActive && $isPriceActive,
            'status' => $isArticleActive && $isPriceActive ? 'active' : 'draft',
            'margin_percentage' => $mapping->margin_percentage,
            'api_price' => $mpc,
            'api_final_price' => $mpc,
            'regular_price' => $mpc,
            'api_stock' => $stanje,
            'available_stock' => $stanje,
            'reserved_stock' => 0,
            'stock_status' => $stanje > 0 ? 'store_available' : 'out_of_stock',
            'sync_status' => 'synced',
            'marked_missing_at' => null,
        ]);

        $this->applyLockedField($product, 'description', $description);
        $this->applyLockedField($product, 'short_description', $shortDescription);

        $product->save();
        $this->priceCalculator->recalculateAndPersist($product->fresh());

        $freshProduct = $product->fresh();
        $changedFields = $wasExisting
            ? ProductImportChangeTracker::diff($snapshot, $freshProduct)
            : [];

        return new ProductUpsertResult(
            action: $wasExisting ? 'updated' : 'inserted',
            product: $freshProduct,
            changedFields: $changedFields,
        );
    }

    private function applyLockedField(Product $product, string $field, mixed $newValue): void
    {
        if (! $product->exists) {
            $product->{$field} = $newValue;

            return;
        }

        $currentValue = $product->{$field};

        if ($this->fieldLockService->shouldApply($product, $field, $newValue, $currentValue)) {
            $product->{$field} = $newValue;
        }
    }

    private function resolveSlug(Product $product, string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'eline-'.($product->eline_sifra ?? Str::random(6));
        }

        $candidate = $base;
        $suffix = 2;

        while (
            Product::query()
                ->where('slug', $candidate)
                ->when($product->exists, fn ($query) => $query->where('id', '!=', $product->id))
                ->exists()
        ) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @param  array<int, string>  $importedSifre
     */
    private function markMissingProducts(array $importedSifre): void
    {
        Product::query()
            ->fromEline()
            ->whereNotIn('eline_sifra', $importedSifre)
            ->where('sync_status', '!=', 'missing_from_api')
            ->update([
                'sync_status' => 'missing_from_api',
                'is_public' => false,
                'marked_missing_at' => now(),
            ]);
    }
}
