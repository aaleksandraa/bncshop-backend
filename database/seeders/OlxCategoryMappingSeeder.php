<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\OlxCategory;
use App\Models\OlxCategoryMapping;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OlxCategoryMappingSeeder extends Seeder
{
    /**
     * OLX kategorije iz plana (rev. 3) — statički cache ako discovery još nije pokrenut.
     *
     * @var array<int, array{name: string, path: string}>
     */
    private const OLX_CATEGORIES = [
        163 => ['name' => 'Monitori', 'path' => 'Kompjuteri > Kompjuterska oprema > Monitori'],
        38 => ['name' => 'Desktop Racunari', 'path' => 'Kompjuteri > Desktop Racunari'],
        39 => ['name' => 'Laptopi', 'path' => 'Kompjuteri > Laptopi'],
        775 => ['name' => 'Klima uredaji', 'path' => 'Moj dom > Grijanje i hladenje > Klima uredaji'],
        2529 => ['name' => 'Elektricni romobili', 'path' => 'Elektricni romobili'],
        166 => ['name' => 'Printer, skener i kopir aparati', 'path' => 'Kompjuteri > Kompjuterska oprema > Printer, skener i kopir aparati'],
        816 => ['name' => 'Video nadzor', 'path' => 'Moj dom > Sigurnosni uredaji > Video nadzor'],
        170 => ['name' => 'Tastature', 'path' => 'Kompjuteri > Kompjuterska oprema > Tastature'],
        162 => ['name' => 'Misevi', 'path' => 'Kompjuteri > Kompjuterska oprema > Misevi'],
        1499 => ['name' => 'PC slusalice', 'path' => 'Kompjuteri > Kompjuterska oprema > PC slusalice'],
        248 => ['name' => 'Projektori, prezenteri i platna', 'path' => 'Kompjuteri > Kompjuterska oprema > Projektori, prezenteri i platna'],
        1748 => ['name' => 'Televizori TV', 'path' => 'Tehnika > TV, oprema i dijelovi > Televizori TV'],
        776 => ['name' => 'Ventilatori', 'path' => 'Moj dom > Grijanje i hladenje > Ventilatori'],
        2076 => ['name' => 'Smartwatch (pametni satovi)', 'path' => 'Mobilni uredaji > Smartwatch (pametni satovi)'],
        2464 => ['name' => 'Oprema za grijanje i ventilaciju', 'path' => 'Moj dom > Grijanje i hladenje > Oprema za grijanje i ventilaciju'],
    ];

    /**
     * Mapiranje BNC → OLX. Svaki red može kreirati više redova u olx_category_mappings
     * (npr. laptopi + netbook → ista OLX kategorija).
     *
     * @var array<int, array<string, mixed>>
     */
    private const MAPPINGS = [
        [
            'label' => 'Monitori',
            'olx_category_id' => 163,
            'slug_paths' => ['it-oprema/periferija/monitori', 'it-oprema/monitori', 'monitori'],
            'names' => ['Monitori'],
            'include_descendants' => true,
        ],
        [
            'label' => 'Desktop računari',
            'olx_category_id' => 38,
            'slug_paths' => ['it-oprema/racunari', 'racunari'],
            'names' => ['Računari', 'Racunari'],
            'include_descendants' => true,
        ],
        [
            'label' => 'Laptopi',
            'olx_category_id' => 39,
            'slug_paths' => ['it-oprema/laptopi/laptopi', 'it-oprema/laptopi/netbook', 'it-oprema/laptopi', 'laptopi'],
            'slug_prefixes' => ['it-oprema/laptopi/'],
            'names' => ['Laptopi', 'Netbook'],
            'include_descendants' => false,
        ],
        [
            'label' => 'Klime',
            'olx_category_id' => 775,
            'slug_paths' => ['klima-grijanje/klime', 'klime', 'klima'],
            'names' => ['Klime', 'Klima'],
            'include_descendants' => true,
        ],
        [
            'label' => 'Električni trotineti',
            'olx_category_id' => 2529,
            'slug_paths' => ['auto-mobilnost/trotineti-elektricni'],
            'names' => ['Električni trotineti', 'Elektricni trotineti'],
            'include_descendants' => false,
        ],
        [
            'label' => 'Printeri',
            'olx_category_id' => 166,
            'slug_paths' => ['print-kancelarija/printeri', 'printeri'],
            'names' => ['Printeri'],
            'include_descendants' => true,
        ],
        [
            'label' => 'Video nadzor',
            'olx_category_id' => 816,
            'slug_paths' => ['sigurnost-smart-home/video-nadzor', 'video-nadzor'],
            'names' => ['Video Nadzor', 'Video nadzor'],
            'include_descendants' => true,
        ],
        [
            'label' => 'Tastature',
            'olx_category_id' => 170,
            'slug_paths' => ['it-oprema/periferija/tastature', 'tastature'],
            'names' => ['Tastature'],
            'include_descendants' => true,
        ],
        [
            'label' => 'Miševi',
            'olx_category_id' => 162,
            'slug_paths' => ['it-oprema/periferija/misevi', 'misevi'],
            'names' => ['Miševi', 'Misevi'],
            'include_descendants' => true,
        ],
        [
            'label' => 'PC slušalice',
            'olx_category_id' => 1499,
            'slug_paths' => ['tv-audio-video/audio/slusalice', 'it-oprema/periferija/slusalice', 'slusalice'],
            'names' => ['Slušalice'],
            'include_descendants' => false,
        ],
        [
            'label' => 'Projektori',
            'olx_category_id' => 248,
            'slug_paths' => ['tv-audio-video/projektori/projektori', 'tv-audio-video/projektori', 'projektori'],
            'names' => ['Projektori'],
            'include_descendants' => true,
        ],
        [
            'label' => 'Televizori',
            'olx_category_id' => 1748,
            'slug_paths' => [
                'tv-audio-video/tv/televizori',
                'tv-audio-video/tv/tv-lcd',
                'tv-audio-video/tv/tv-plazma',
                'televizori',
            ],
            'names' => ['Televizori'],
            'include_descendants' => false,
        ],
        [
            'label' => 'Ventilatori',
            'olx_category_id' => 776,
            'slug_paths' => ['klima-grijanje/ventilatori', 'ventilatori'],
            'names' => ['Ventilatori'],
            'include_descendants' => true,
        ],
        [
            'label' => 'Pametni satovi',
            'olx_category_id' => 2076,
            'slug_paths' => ['telefonija/pametni-satovi', 'pametni-satovi'],
            'names' => ['Pametni Satovi', 'Pametni satovi'],
            'include_descendants' => true,
        ],
        [
            'label' => 'Prečišćivači zraka',
            'olx_category_id' => 2464,
            'slug_paths' => ['klima-grijanje/preciscivaci-zraka', 'preciscivaci-zraka'],
            'names' => ['Prečišćivači zraka', 'Preciscivaci zraka'],
            'include_descendants' => false,
        ],
    ];

    public function run(): void
    {
        $this->seedOlxCategories();

        $created = 0;
        $skipped = 0;
        $missing = [];

        foreach (self::MAPPINGS as $definition) {
            $olxCategoryId = (int) $definition['olx_category_id'];
            $olxPath = self::OLX_CATEGORIES[$olxCategoryId]['path'] ?? '';
            $categories = $this->resolveBncCategories($definition);

            if ($categories->isEmpty()) {
                $missing[] = (string) ($definition['label'] ?? $olxCategoryId);

                continue;
            }

            foreach ($categories as $category) {
                OlxCategoryMapping::query()->updateOrCreate(
                    ['category_id' => $category->id],
                    [
                        'olx_category_id' => $olxCategoryId,
                        'olx_category_path' => $olxPath,
                        'is_enabled' => true,
                        'include_descendants' => (bool) ($definition['include_descendants'] ?? true),
                    ],
                );
                $created++;
            }
        }

        $this->command?->info("OLX mapiranja: {$created} kategorija upisano/ ažurirano.");

        if ($missing !== []) {
            $this->command?->warn('Nisu pronađene BNC kategorije za: '.implode(', ', $missing));
        }
    }

    private function seedOlxCategories(): void
    {
        foreach (self::OLX_CATEGORIES as $id => $meta) {
            OlxCategory::query()->updateOrCreate(
                ['id' => $id],
                [
                    'name' => $meta['name'],
                    'path' => $meta['path'],
                    'fetched_at' => now(),
                ],
            );
        }

        $this->command?->info('OLX kategorije (cache): '.count(self::OLX_CATEGORIES).' upisano.');
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return \Illuminate\Support\Collection<int, Category>
     */
    private function resolveBncCategories(array $definition): \Illuminate\Support\Collection
    {
        $found = collect();
        $slugPaths = array_map('strtolower', $definition['slug_paths'] ?? []);
        $slugPrefixes = array_map('strtolower', $definition['slug_prefixes'] ?? []);
        $names = array_map([$this, 'normalizeText'], $definition['names'] ?? []);

        $categories = Category::query()
            ->where('status', 'active')
            ->get(['id', 'name', 'display_name', 'full_slug', 'depth']);

        foreach ($categories as $category) {
            $slug = strtolower((string) $category->full_slug);

            if (in_array($slug, $slugPaths, true)) {
                $found->put($category->id, $category);

                continue;
            }

            foreach ($slugPrefixes as $prefix) {
                if (str_starts_with($slug, $prefix)) {
                    $found->put($category->id, $category);

                    continue 2;
                }
            }

            $categoryName = $this->normalizeText($category->display_name ?: $category->name);

            foreach ($names as $name) {
                if ($categoryName === $name || str_contains($categoryName, $name)) {
                    $found->put($category->id, $category);

                    continue 2;
                }
            }
        }

        return $found->values();
    }

    private function normalizeText(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replace(['/', '-', '_'], ' ')
            ->squish()
            ->toString();
    }
}
