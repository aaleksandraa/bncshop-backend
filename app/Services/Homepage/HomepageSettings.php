<?php

namespace App\Services\Homepage;

use App\Models\SystemSetting;
use App\Services\Catalog\ProductReadCache;
use Illuminate\Support\Facades\Cache;

class HomepageSettings
{
    public function __construct(
        private readonly ProductReadCache $productReadCache,
    ) {}
    public const WEEKLY_OFFER_LAYOUTS = [
        'spotlight_card' => 'Kartica u hero sekciji (1 proizvod)',
        'grid_2' => 'Mreža — 2 proizvoda u redu',
        'grid_3' => 'Mreža — 3 proizvoda u redu',
        'grid_4' => 'Mreža — 4 proizvoda u redu',
        'grid_6' => 'Mreža — do 6 proizvoda (3×2)',
        'rows' => 'Lista — red po red',
        'carousel' => 'Karusel — horizontalni scroll',
        'tiles' => 'Pločice — veliki prikaz',
    ];

    /**
     * @return array<string, mixed>
     */
    public function weeklyOfferDefaults(): array
    {
        return [
            'enabled' => true,
            'title' => 'Ponuda sedmice',
            'subtitle' => null,
            'layout' => 'spotlight_card',
            'product_limit' => 1,
            'product_ids' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function weeklyOffer(): array
    {
        $stored = SystemSetting::query()
            ->where('key', 'homepage_weekly_offer')
            ->value('value');

        if (! is_array($stored)) {
            return $this->weeklyOfferDefaults();
        }

        return array_merge($this->weeklyOfferDefaults(), $stored);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveWeeklyOffer(array $data): void
    {
        $previousProductIds = array_values(array_map(
            intval(...),
            (array) ($this->weeklyOffer()['product_ids'] ?? []),
        ));

        $layout = (string) ($data['layout'] ?? 'spotlight_card');
        $limit = max(1, min(6, (int) ($data['product_limit'] ?? 1)));

        if ($layout === 'spotlight_card') {
            $limit = 1;
        }

        $productIds = array_values(array_unique(array_map(
            intval(...),
            array_filter((array) ($data['product_ids'] ?? [])),
        )));

        $productIds = array_slice($productIds, 0, $limit);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'homepage_weekly_offer'],
            [
                'group' => 'homepage',
                'value' => [
                    'enabled' => (bool) ($data['enabled'] ?? true),
                    'title' => (string) ($data['title'] ?? 'Ponuda sedmice'),
                    'subtitle' => filled($data['subtitle'] ?? null) ? (string) $data['subtitle'] : null,
                    'layout' => $layout,
                    'product_limit' => $limit,
                    'product_ids' => $productIds,
                ],
            ],
        );

        $this->flushWeeklyOfferCache($previousProductIds, $productIds);
    }

    /**
     * @param  array<int, int>  $previousProductIds
     * @param  array<int, int>  $productIds
     */
    public function flushWeeklyOfferCache(array $previousProductIds = [], array $productIds = []): void
    {
        $this->productReadCache->flushWeeklyOffer();

        if ($this->productReadCache->supportsTags()) {
            return;
        }

        foreach ([[], $previousProductIds, $productIds] as $ids) {
            Cache::forget('homepage:weekly-offer:'.md5(implode(',', $ids)));
        }
    }

    public function weeklyOfferInHero(): bool
    {
        $config = $this->weeklyOffer();

        return ($config['enabled'] ?? false)
            && ($config['layout'] ?? '') === 'spotlight_card';
    }
}
