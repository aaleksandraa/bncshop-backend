<?php

namespace App\Services\Eline;

use App\Models\Product;
use App\Services\Catalog\ProductReadCache;
use App\Services\Pricing\ProductSalePriceService;
use Illuminate\Database\Eloquent\Builder;

class ElinePriceRestoreService
{
    public function __construct(
        private readonly ProductReadCache $productReadCache,
        private readonly ElineShelfPrice $shelfPrice,
        private readonly ProductSalePriceService $salePrice,
    ) {}

    /**
     * Vrati police cijene eLine artikala na MPC iz cjenovnika.
     * Ako šifre nema u feedu, ostaje sačuvani api_price.
     *
     * @param  array<string, array{mpc: float, candidates: list<float>}>  $priceChoices
     * @return array{
     *     scanned: int,
     *     updated: int,
     *     unchanged: int,
     *     skipped_locked: int,
     *     skipped_missing: int,
     *     updated_ids: array<int, int>,
     *     samples: array<int, array{eline_sifra: string, name: string, from: string, to: string}>
     * }
     */
    public function restore(
        bool $dryRun = false,
        bool $keepLocked = false,
        bool $refurbishedOnly = false,
        array $priceChoices = [],
        ?string $onlySifra = null,
    ): array {
        $stats = [
            'scanned' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped_locked' => 0,
            'skipped_missing' => 0,
            'updated_ids' => [],
            'samples' => [],
        ];

        $this->query($refurbishedOnly, $priceChoices, $onlySifra)
            ->orderBy('id')
            ->chunkById(200, function ($products) use (&$stats, $dryRun, $keepLocked, $priceChoices): void {
                foreach ($products as $product) {
                    $stats['scanned']++;
                    $plan = $this->plan($product, $keepLocked, $priceChoices);

                    if ($plan['action'] === 'skipped_locked') {
                        $stats['skipped_locked']++;

                        continue;
                    }

                    if ($plan['action'] === 'skipped_missing') {
                        $stats['skipped_missing']++;

                        continue;
                    }

                    if ($plan['action'] === 'unchanged') {
                        $stats['unchanged']++;

                        continue;
                    }

                    $stats['updated']++;

                    if (count($stats['samples']) < 40) {
                        $stats['samples'][] = [
                            'eline_sifra' => (string) ($product->eline_sifra ?: $product->sku),
                            'name' => (string) $product->name,
                            'from' => $plan['from'],
                            'to' => $plan['to'],
                        ];
                    }

                    if ($dryRun) {
                        continue;
                    }

                    $product->forceFill([
                        'price_locked' => false,
                        'manual_price' => null,
                        'calculated_price' => null,
                        'api_price' => $plan['target'],
                        'api_final_price' => $plan['target'],
                        'regular_price' => $plan['target'],
                        'display_price' => $plan['display'],
                        'on_sale' => $plan['display'] < $plan['target'],
                    ])->save();
                    $stats['updated_ids'][] = (int) $product->id;
                }
            });

        if (! $dryRun && $stats['updated'] > 0) {
            $this->productReadCache->flushAll();
        }

        return $stats;
    }

    /**
     * @param  array<string, array{mpc: float, candidates: list<float>}>  $priceChoices
     */
    private function query(bool $refurbishedOnly, array $priceChoices, ?string $onlySifra = null): Builder
    {
        $builder = Product::query()
            ->where(function (Builder $query): void {
                $query->where('is_set', false)->orWhereNull('is_set');
            });

        if ($onlySifra !== null && $onlySifra !== '') {
            return $builder->where(function (Builder $match) use ($onlySifra): void {
                $this->constrainToSifra($match, $onlySifra);
            });
        }

        $externalIds = $this->externalIds($priceChoices);
        $sifre = $this->feedSifre($priceChoices);

        return $builder
            ->where(function (Builder $query) use ($externalIds, $sifre): void {
                $query->where('import_source', 'eline');

                if ($externalIds !== []) {
                    $query->orWhereIn('external_product_id', $externalIds);
                }

                if ($sifre === []) {
                    return;
                }

                $placeholders = implode(',', array_fill(0, count($sifre), '?'));
                $query->orWhere(function (Builder $manual) use ($sifre, $placeholders): void {
                    $manual->where(function (Builder $source): void {
                        $source->whereNull('import_source')
                            ->orWhere('import_source', '')
                            ->orWhere('import_source', 'manual');
                    })->where(function (Builder $code) use ($sifre, $placeholders): void {
                        $code->whereRaw("TRIM(COALESCE(sku, '')) IN ({$placeholders})", $sifre)
                            ->orWhereRaw("TRIM(COALESCE(eline_sifra, '')) IN ({$placeholders})", $sifre);
                    });
                });
            })
            ->when($refurbishedOnly, function (Builder $query): void {
                $query->where('is_refurbished', true);
            });
    }

    private function constrainToSifra(Builder $query, string $sifra): void
    {
        $query->where('external_product_id', ElineSupport::externalProductId($sifra));

        foreach (ElineSupport::sifraKeys($sifra) as $key) {
            $query->orWhereRaw("TRIM(COALESCE(eline_sifra, '')) = ?", [$key])
                ->orWhereRaw("TRIM(COALESCE(sku, '')) = ?", [$key]);
        }
    }

    /**
     * @param  array<string, array{mpc: float, candidates: list<float>}>  $priceChoices
     * @return list<string>
     */
    private function externalIds(array $priceChoices): array
    {
        $ids = [];

        foreach ($priceChoices as $key => $choice) {
            if (is_string($key) && str_starts_with($key, 'external:')) {
                $ids[] = substr($key, strlen('external:'));
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, array{mpc: float, candidates: list<float>}>  $priceChoices
     * @return list<string>
     */
    private function feedSifre(array $priceChoices): array
    {
        $sifre = [];

        foreach ($priceChoices as $key => $choice) {
            if (is_string($key) && str_starts_with($key, 'external:')) {
                continue;
            }

            $sifre[] = (string) $key;
        }

        return array_values(array_unique($sifre));
    }

    /**
     * @param  array<string, array{mpc: float, candidates: list<float>}>  $priceChoices
     * @return array{action: string, from: string, to: string, target: float}
     */
    private function plan(Product $product, bool $keepLocked, array $priceChoices): array
    {
        $empty = ['from' => '', 'to' => '', 'target' => 0.0, 'display' => 0.0];

        if ($keepLocked && $product->price_locked && $product->manual_price !== null) {
            return ['action' => 'skipped_locked'] + $empty;
        }

        $choice = $this->choiceForProduct($product, $priceChoices);
        $rows = is_array($choice['rows'] ?? null) ? $choice['rows'] : [];
        $current = (float) ($product->regular_price ?: $product->display_price);
        $target = round($this->shelfPrice->resolve($rows, $current), 2);

        if ($choice === null || $target <= 0) {
            return ['action' => 'skipped_missing'] + $empty;
        }

        $sale = $this->salePrice->resolveSalePrice($product);
        $display = $sale !== null && $sale > 0 && $sale < $target
            ? round($sale, 2)
            : $target;
        $from = $this->priceLabel($product);
        $to = $this->amountLabel($display, $target);

        $sameApi = round((float) ($product->api_price ?? 0), 2) === $target;
        $sameRegular = round((float) $product->regular_price, 2) === $target;
        $sameDisplay = round((float) $product->display_price, 2) === $display;
        $unlocked = ! $product->price_locked && $product->manual_price === null;

        if ($sameApi && $sameRegular && $sameDisplay && $unlocked) {
            return ['action' => 'unchanged', 'from' => $from, 'to' => $to, 'target' => $target, 'display' => $display];
        }

        return ['action' => 'update', 'from' => $from, 'to' => $to, 'target' => $target, 'display' => $display];
    }

    /**
     * @param  array<string, array{mpc: float, candidates: list<float>}>  $priceChoices
     * @return array{mpc: float, candidates: list<float>}|null
     */
    private function choiceForProduct(Product $product, array $priceChoices): ?array
    {
        $externalKey = 'external:'.(string) $product->external_product_id;

        if (isset($priceChoices[$externalKey])) {
            return $priceChoices[$externalKey];
        }

        foreach ([(string) $product->eline_sifra, (string) $product->sku] as $raw) {
            foreach (ElineSupport::sifraKeys($raw) as $key) {
                if (isset($priceChoices[$key])) {
                    return $priceChoices[$key];
                }
            }
        }

        return null;
    }

    private function amountLabel(float $display, float $regular): string
    {
        $displayText = number_format($display, 2, '.', '');
        $regularText = number_format($regular, 2, '.', '');

        return $displayText === $regularText ? $displayText : "{$displayText} (mpc {$regularText})";
    }

    private function priceLabel(Product $product): string
    {
        $regular = number_format((float) $product->regular_price, 2, '.', '');
        $display = number_format((float) $product->display_price, 2, '.', '');

        return $regular === $display ? $display : "{$display} (redovna {$regular})";
    }
}
