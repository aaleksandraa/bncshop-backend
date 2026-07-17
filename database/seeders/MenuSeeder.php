<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    private const HEADER_CHILD_LIMIT = 24;

    private const HEADER_MAX_DEPTH = 3;

    /** @var array<int, string> */
    private const KLIMA_CHILD_ORDER = [
        'klime',
        'grijanje-tijela',
        'grijanja-tijela',
        'grijanje',
    ];

    /**
     * @var array<int, array{names: array<int, string>, slug_ends: array<int, string>, slug_paths: array<int, string>, label?: string|null}>
     */
    private const HEADER_CATEGORY_PRIORITY = [
        ['names' => ['Računari', 'Racunari'], 'slug_ends' => ['racunari'], 'slug_paths' => ['it-oprema/racunari', 'racunari'], 'label' => null],
        ['names' => ['Laptopi'], 'slug_ends' => ['laptopi'], 'slug_paths' => ['it-oprema/laptopi', 'laptopi'], 'label' => null],
        ['names' => ['Monitori'], 'slug_ends' => ['monitori'], 'slug_paths' => ['it-oprema/periferija/monitori', 'it-oprema/monitori', 'monitori'], 'label' => null],
        ['names' => ['Print', 'Printeri', 'Print i kancelarija'], 'slug_ends' => ['print-kancelarija', 'printeri', 'print'], 'slug_paths' => ['print-kancelarija', 'print-kancelarija/printeri', 'printeri'], 'label' => 'Print'],
        ['names' => ['IT tehnika', 'IT oprema'], 'slug_ends' => ['it-oprema'], 'slug_paths' => ['it-oprema'], 'label' => 'IT tehnika'],
        ['names' => ['Klime', 'Klima'], 'slug_ends' => ['klime', 'klima'], 'slug_paths' => ['klime', 'klima'], 'label' => null],
        ['names' => ['Televizori', 'TV'], 'slug_ends' => ['televizori', 'tv'], 'slug_paths' => ['televizori', 'tv/televizori', 'tv'], 'label' => null],
    ];

    public function run(): void
    {
        $pages = [
            [
                'title' => 'O nama',
                'slug' => 'o-nama',
                'body' => null,
                'meta_title' => 'O nama',
                'meta_description' => 'BNC d.o.o. Sarajevo — polovna i brandirana informatička oprema, servis, garancija i kupovina online 24/7.',
                'status' => 'active',
            ],
            [
                'title' => 'Servis',
                'slug' => 'servis',
                'body' => '<p>Informacije o servisnim uslugama, garancijskom servisu i podršci nakon kupovine. Sadržaj ove stranice možete prilagoditi u admin panelu.</p>',
                'status' => 'active',
            ],
            [
                'title' => 'Kontakt',
                'slug' => 'kontakt',
                'body' => null,
                'meta_title' => 'Kontakt',
                'meta_description' => 'Kontaktirajte BNC d.o.o. Sarajevo — Merhemića trg 2. Telefon, email, mapa i Google recenzije.',
                'status' => 'active',
            ],
            [
                'title' => 'Dostava',
                'slug' => 'dostava',
                'body' => '<p>Informacije o dostavi širom Bosne i Hercegovine.</p>',
                'status' => 'active',
            ],
            [
                'title' => 'Povrat',
                'slug' => 'povrat',
                'body' => '<p>Uslovi povrata proizvoda u skladu sa zakonom.</p>',
                'status' => 'active',
            ],
            [
                'title' => 'Garancija',
                'slug' => 'garancija',
                'body' => '<p>Garancija na sve proizvode — novo i rabljeno.</p>',
                'status' => 'active',
            ],
            [
                'title' => 'Impresum',
                'slug' => 'impresum',
                'body' => '<p>Pravne informacije o vlasništvu i odgovornosti.</p>',
                'status' => 'active',
            ],
            [
                'title' => 'Privatnost',
                'slug' => 'privatnost',
                'body' => '<p>Ova politika privatnosti objašnjava kako BNC Shop prikuplja, koristi i štiti vaše lične podatke u skladu sa GDPR propisima Evropske unije i važećim zakonima Bosne i Hercegovine.</p><h2>Koje podatke prikupljamo</h2><p>Pri narudžbi prikupljamo kontakt podatke (ime, telefon, e-mail, adresa) koje nam dobrovoljno ostavljate. Za registrovane korisnike čuvamo podatke o nalogu i narudžbama.</p><h2>Kolačići</h2><p>Koristimo neophodne kolačiće za rad shopa (korpa, prijava, pamćenje vaših postavki). Analitičke i marketing kolačiće (Google Analytics, Meta Pixel) koristimo samo uz vašu saglasnost.</p><p>Postavke kolačića možete pregledati i promijeniti u bilo kom trenutku u sekciji <strong>Postavke kolačića</strong> ispod na ovoj stranici, ili putem linka u footeru webshopa.</p><h2>Vaša prava</h2><p>Imate pravo na pristup, ispravku, brisanje i ograničenje obrade podataka, kao i pravo da povučete saglasnost za neobavezne kolačiće bez uticaja na zakonitost obrade prije povlačenja.</p><h2>Kontakt</h2><p>Za pitanja o privatnosti kontaktirajte nas putem kontakt podataka navedenih u footeru.</p>',
                'status' => 'active',
            ],
            [
                'title' => 'Uslovi kupovine',
                'slug' => 'uslovi',
                'body' => '<p>Opći uslovi kupovine u BNC Shop webshopu.</p>',
                'status' => 'active',
            ],
        ];

        foreach ($pages as $page) {
            CmsPage::query()->updateOrCreate(['slug' => $page['slug']], $page);
        }

        $headerMenu = Menu::query()->updateOrCreate(
            ['slug' => 'header'],
            [
                'name' => 'Glavni meni',
                'description' => 'Navigacija u headeru webshopa',
                'is_active' => true,
            ],
        );

        $footerMenu = Menu::query()->updateOrCreate(
            ['slug' => 'footer'],
            [
                'name' => 'Footer meni',
                'description' => 'Linkovi u podnožju stranice',
                'is_active' => true,
            ],
        );

        $this->refreshHeaderMenu($headerMenu);
        $this->ensureFooterMenu($footerMenu);

        $this->command?->info('Menus and CMS pages seeded.');
    }

    private function ensureFooterMenu(Menu $menu): void
    {
        if ($menu->items()->count() === 0) {
            $this->seedFooterMenu($menu);

            return;
        }

        $this->ensureFooterInstallmentLinks($menu);
    }

    private function ensureFooterInstallmentLinks(Menu $menu): void
    {
        $groups = [
            'Kupovina' => ['sort' => 3, 'url' => '/kupovina-na-rate'],
            'Podrška' => ['sort' => 4, 'url' => '/kupovina-na-rate'],
        ];

        foreach ($groups as $label => $config) {
            if ($label === 'Podrška') {
                continue;
            }

            $parent = $menu->items()
                ->whereNull('parent_id')
                ->where('label', $label)
                ->first();

            if ($parent === null) {
                continue;
            }

            $exists = $menu->items()
                ->where('parent_id', $parent->id)
                ->where('url', $config['url'])
                ->exists();

            if ($exists) {
                continue;
            }

            MenuItem::query()->create([
                'menu_id' => $menu->id,
                'parent_id' => $parent->id,
                'type' => MenuItem::TYPE_CUSTOM_LINK,
                'label' => 'Kupovina na rate',
                'url' => $config['url'],
                'sort_order' => $config['sort'],
                'is_active' => true,
            ]);
        }
    }

    private function refreshHeaderMenu(Menu $menu): void
    {
        $menu->items()->delete();

        $sortOrder = 0;

        $links = [
            ['label' => 'Akcija', 'url' => '/akcija'],
            ['label' => 'Kategorije', 'url' => '/kategorije'],
            ['label' => 'Brendovi', 'url' => '/brendovi'],
            ['label' => 'Novo', 'url' => '/novo'],
            ['label' => 'Refurbished', 'url' => '/refurbished'],
        ];

        foreach ($links as $link) {
            MenuItem::query()->create([
                'menu_id' => $menu->id,
                'type' => MenuItem::TYPE_CUSTOM_LINK,
                'label' => $link['label'],
                'url' => $link['url'],
                'sort_order' => $sortOrder++,
                'is_active' => true,
            ]);
        }

        $usedCategoryIds = [];

        foreach (self::HEADER_CATEGORY_PRIORITY as $priority) {
            $category = $this->findPriorityCategory($priority);

            if ($category === null || in_array($category->id, $usedCategoryIds, true)) {
                continue;
            }

            $parentItem = MenuItem::query()->create([
                'menu_id' => $menu->id,
                'type' => MenuItem::TYPE_CATEGORY,
                'category_id' => $category->id,
                'label' => $priority['label'] ?? null,
                'sort_order' => $sortOrder++,
                'is_active' => true,
            ]);

            $usedCategoryIds[] = $category->id;
            $this->seedHeaderCategoryChildren($menu, $parentItem, $category->id, 2);
        }

        MenuItem::query()->create([
            'menu_id' => $menu->id,
            'type' => MenuItem::TYPE_CUSTOM_LINK,
            'label' => 'Ostalo',
            'url' => '/kategorije',
            'sort_order' => $sortOrder++,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array{names: array<int, string>, slug_ends: array<int, string>, slug_paths: array<int, string>, label?: string|null}  $priority
     */
    private function findPriorityCategory(array $priority): ?Category
    {
        foreach ($priority['slug_paths'] as $slugPath) {
            $category = Category::query()
                ->active()
                ->whereRaw('LOWER(full_slug) = ?', [mb_strtolower($slugPath)])
                ->orderBy('depth')
                ->orderBy('path')
                ->first();

            if ($category !== null) {
                return $category;
            }
        }

        foreach ($priority['names'] as $name) {
            $category = Category::query()
                ->active()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->orderBy('depth')
                ->orderBy('path')
                ->first();

            if ($category !== null) {
                return $category;
            }
        }

        foreach ($priority['slug_ends'] as $slugEnd) {
            $needle = mb_strtolower($slugEnd);

            $category = Category::query()
                ->active()
                ->where(function ($query) use ($needle): void {
                    $query->whereRaw('LOWER(full_slug) = ?', [$needle])
                        ->orWhereRaw('LOWER(full_slug) LIKE ?', ['%/'.$needle]);
                })
                ->orderBy('depth')
                ->orderBy('path')
                ->first();

            if ($category !== null) {
                return $category;
            }
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Category>  $children
     * @return \Illuminate\Support\Collection<int, Category>
     */
    private function sortCategoryChildren($children, Category $parent)
    {
        $slug = mb_strtolower($parent->full_slug);
        $name = mb_strtolower($parent->name);

        if (
            ! str_contains($slug, 'klime')
            && ! str_contains($slug, 'klima')
            && ! str_contains($name, 'klima')
            && ! str_contains($name, 'grijanje')
        ) {
            return $children->sortBy('name')->values();
        }

        return $children
            ->sort(function (Category $a, Category $b): int {
                $aRank = $this->childCategoryRank($a);
                $bRank = $this->childCategoryRank($b);

                if ($aRank !== $bRank) {
                    return $aRank <=> $bRank;
                }

                return strcasecmp($a->name, $b->name);
            })
            ->values();
    }

    private function childCategoryRank(Category $category): int
    {
        $slug = mb_strtolower($category->full_slug);
        $name = mb_strtolower($category->name);

        foreach (self::KLIMA_CHILD_ORDER as $index => $needle) {
            if (
                str_ends_with($slug, '/'.$needle)
                || $slug === $needle
                || str_contains($slug, '/'.$needle.'/')
                || str_contains($name, $needle)
            ) {
                return $index;
            }
        }

        return PHP_INT_MAX;
    }

    private function seedHeaderCategoryChildren(
        Menu $menu,
        MenuItem $parentItem,
        int $categoryId,
        int $depth,
    ): void {
        if ($depth > self::HEADER_MAX_DEPTH) {
            return;
        }

        $children = Category::query()
            ->active()
            ->where('parent_id', $categoryId)
            ->limit(self::HEADER_CHILD_LIMIT)
            ->get();

        $parentCategory = Category::query()->find($categoryId);

        if ($parentCategory !== null) {
            $children = $this->sortCategoryChildren($children, $parentCategory);
        } else {
            $children = $children->sortBy('name')->values();
        }

        foreach ($children as $childIndex => $child) {
            $childItem = MenuItem::query()->create([
                'menu_id' => $menu->id,
                'parent_id' => $parentItem->id,
                'type' => MenuItem::TYPE_CATEGORY,
                'category_id' => $child->id,
                'sort_order' => $childIndex,
                'is_active' => true,
            ]);

            $this->seedHeaderCategoryChildren($menu, $childItem, $child->id, $depth + 1);
        }
    }

    private function seedFooterMenu(Menu $menu): void
    {
        $groups = [
            [
                ['type' => MenuItem::TYPE_CUSTOM_LINK, 'label' => 'Početna', 'url' => '/'],
                ['type' => MenuItem::TYPE_CUSTOM_LINK, 'label' => 'Pretraga', 'url' => '/pretraga'],
                ['type' => MenuItem::TYPE_CUSTOM_LINK, 'label' => 'Korpa', 'url' => '/korpa'],
                ['type' => MenuItem::TYPE_CUSTOM_LINK, 'label' => 'Kupovina na rate', 'url' => '/kupovina-na-rate'],
                ['type' => MenuItem::TYPE_CUSTOM_LINK, 'label' => 'Checkout', 'url' => '/checkout'],
            ],
            [
                ['type' => MenuItem::TYPE_CUSTOM_LINK, 'label' => 'Prijava', 'url' => '/nalog/prijava'],
                ['type' => MenuItem::TYPE_CUSTOM_LINK, 'label' => 'Registracija', 'url' => '/nalog/registracija'],
                ['type' => MenuItem::TYPE_CUSTOM_LINK, 'label' => 'Moje narudžbe', 'url' => '/nalog/narudzbe'],
            ],
            [
                ['type' => MenuItem::TYPE_PAGE, 'slug' => 'kontakt'],
                ['type' => MenuItem::TYPE_PAGE, 'slug' => 'dostava'],
                ['type' => MenuItem::TYPE_PAGE, 'slug' => 'povrat'],
                ['type' => MenuItem::TYPE_PAGE, 'slug' => 'garancija'],
            ],
        ];

        $order = 0;
        foreach ($groups as $group) {
            $parent = MenuItem::query()->create([
                'menu_id' => $menu->id,
                'type' => MenuItem::TYPE_CUSTOM_LINK,
                'label' => match ($order) {
                    0 => 'Kupovina',
                    1 => 'Nalog',
                    default => 'Podrška',
                },
                'url' => '#',
                'sort_order' => $order++,
                'is_active' => true,
            ]);

            foreach ($group as $childIndex => $child) {
                $data = [
                    'menu_id' => $menu->id,
                    'parent_id' => $parent->id,
                    'type' => $child['type'],
                    'label' => $child['label'] ?? null,
                    'url' => $child['url'] ?? null,
                    'sort_order' => $childIndex,
                    'is_active' => true,
                ];

                if ($child['type'] === MenuItem::TYPE_PAGE) {
                    $page = CmsPage::query()->where('slug', $child['slug'])->first();
                    $data['cms_page_id'] = $page?->id;
                }

                MenuItem::query()->create($data);
            }
        }
    }
}
