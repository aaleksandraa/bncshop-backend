<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        $authorId = User::query()->orderBy('id')->value('id');

        $productIds = Product::query()
            ->public()
            ->active()
            ->where('is_refurbished', true)
            ->orderByDesc('updated_at')
            ->limit(6)
            ->pluck('id')
            ->all();

        if ($productIds === []) {
            $productIds = Product::query()
                ->public()
                ->active()
                ->orderByDesc('updated_at')
                ->limit(6)
                ->pluck('id')
                ->all();
        }

        $contentBlocks = [
            [
                'type' => 'rich_text',
                'data' => [
                    'body' => <<<'HTML'
<h2>Šta je refurbished uređaj?</h2>
<p><strong>Refurbished</strong> (obnovljeni) uređaj je proizvod koji je vraćen, testiran, očišćen i ponovo stavljen u prodaju. To ne znači da je loš — naprotiv, prolazi provjere kvalitete prije nego što stigne do kupca.</p>
<h3>Prednosti refurbished laptopa</h3>
<ul>
<li>Niža cijena u odnosu na potpuno nov model</li>
<li>Testiran hardver i provjeren rad</li>
<li>Opcija kupnje jačeg modela za isti budžet</li>
<li>Ekološki održivija kupovina</li>
</ul>
<h3>Na šta obratiti pažnju?</h3>
<p>Prije kupovine provjerite garanciju, stanje baterije, RAM i disk, te da li prodavac jasno navodi stanje uređaja. U BNC Shopu refurbished proizvodi su jasno označeni i spremni za kupovinu online.</p>
HTML,
                ],
            ],
        ];

        if ($productIds !== []) {
            $contentBlocks[] = [
                'type' => 'products_showcase',
                'data' => [
                    'title' => 'Preporučeni refurbished laptopi',
                    'layout' => 'carousel',
                    'product_ids' => $productIds,
                ],
            ];
        }

        BlogPost::query()->updateOrCreate(
            ['slug' => 'sta-znaci-refurbished-laptop'],
            [
                'title' => 'Šta znači refurbished laptop?',
                'excerpt' => 'Refurbished laptop je obnovljeni uređaj koji je testiran i ponovo spreman za kupovinu — uz nižu cijenu i jasno stanje.',
                'featured_image_url' => null,
                'featured_image_path' => null,
                'intro' => '<p>Ako razmišljate o kupovini laptopa, vjerovatno ste naišli na oznaku <strong>refurbished</strong>. U ovom članku objašnjavamo šta to znači, kome odgovara i kako odabrati dobar model.</p>',
                'content_blocks' => $contentBlocks,
                'status' => 'published',
                'published_at' => now()->subDay(),
                'author_id' => $authorId,
                'meta_title' => 'Šta znači refurbished laptop? | BNC Blog',
                'meta_description' => 'Saznajte šta znači refurbished laptop, koje su prednosti i na šta obratiti pažnju pri kupovini obnovljenog uređaja.',
                'og_image_url' => null,
            ],
        );
    }
}
