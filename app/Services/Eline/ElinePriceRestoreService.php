<?php

namespace App\Services\Eline;

use App\Models\Product;
use App\Services\Catalog\ProductReadCache;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\PriceResult;
use Illuminate\Database\Eloquent\Builder;

class ElinePriceRestoreService
{
    public function __construct(
        private readonly PriceCalculator $priceCalculator,
        private readonly ProductReadCache $productReadCache,
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
        bool $refurbishedOnly = true,
        array $priceChoices = [],
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

        $this->query($refurbishedOnly)
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
                        'api_price' => $plan['target'],
                        'api_final_price' => $plan['target'],
                    ])->save();

                    $this->priceCalculator->recalculateAndPersist($product->fresh());
                    $stats['updated_ids'][] = (int) $product->id;
                }
            });

        if (! $dryRun && $stats['updated'] > 0) {
            $this->productReadCache->flushAll();
        }

        return $stats;
    }

    private function query(bool $refurbishedOnly): Builder
    {
        return Product::query()
            ->fromEline()
            ->where('is_set', false)
            ->when($refurbishedOnly, function (Builder $query): void {
                $query->where('is_refurbished', true);
            });
    }

    /**
     * @param  array<string, array{mpc: float, candidates: list<float>}>  $priceChoices
     * @return array{action: string, from: string, to: string, target: float}
     */
    private function plan(Product $product, bool $keepLocked, array $priceChoices): array
    {
        $empty = ['from' => '', 'to' => '', 'target' => 0.0];

        if ($keepLocked && $product->price_locked && $product->manual_price !== null) {
            return ['action' => 'skipped_locked'] + $empty;
        }

        $choice = $this->choiceForProduct($product, $priceChoices);
        $target = round((float) ($choice['mpc'] ?? 0), 2);

        if ($choice === null || $target <= 0) {
            return ['action' => 'skipped_missing'] + $empty;
        }

        $preview = $product->replicate();
        $preview->id = $product->id;
        $preview->exists = true;
        $preview->price_locked = false;
        $preview->manual_price = null;
        $preview->api_price = $target;

        $result = $this->priceCalculator->calculate($preview);
        $from = $this->priceLabel($product);
        $to = $this->resultLabel($result);

        $sameApi = round((float) ($product->api_price ?? 0), 2) === round($target, 2);
        $sameRegular = round((float) $product->regular_price, 2) === round($result->regularPrice, 2);
        $sameDisplay = round((float) $product->display_price, 2) === round($result->displayPrice, 2);
        $sameSale = (bool) $product->on_sale === $result->onSale;
        $unlocked = ! $product->price_locked && $product->manual_price === null;

        if ($sameApi && $sameRegular && $sameDisplay && $sameSale && $unlocked) {
            return ['action' => 'unchanged', 'from' => $from, 'to' => $to, 'target' => $target];
        }

        return ['action' => 'update', 'from' => $from, 'to' => $to, 'target' => $target];
    }

    /**
     * @param  array<string, array{mpc: float, candidates: list<float>}>  $priceChoices
     * @return array{mpc: float, candidates: list<float>}|null
     */
    private function choiceForProduct(Product $product, array $priceChoices): ?array
    {
        foreach ([(string) $product->eline_sifra, (string) $product->sku] as $raw) {
            foreach (ElineSupport::sifraKeys($raw) as $key) {
                if (isset($priceChoices[$key])) {
                    return $priceChoices[$key];
                }
            }
        }

        return null;
    }

    private function priceLabel(Product $product): string
    {
        $regular = number_format((float) $product->regular_price, 2, '.', '');
        $display = number_format((float) $product->display_price, 2, '.', '');

        return $regular === $display ? $display : "{$display} (redovna {$regular})";
    }

    private function resultLabel(PriceResult $result): string
    {
        $regular = number_format($result->regularPrice, 2, '.', '');
        $display = number_format($result->displayPrice, 2, '.', '');

        return $regular === $display ? $display : "{$display} (redovna {$regular})";
    }
}
