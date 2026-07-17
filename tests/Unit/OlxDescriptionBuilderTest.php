<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\Olx\OlxDescriptionBuilder;
use App\Services\Olx\OlxSyncSettings;
use Tests\TestCase;

class OlxDescriptionBuilderTest extends TestCase
{
    public function test_builds_html_description_with_paragraphs_bold_and_footer(): void
    {
        $builder = app(OlxDescriptionBuilder::class);

        $product = Product::make([
            'short_description' => 'Kratak opis proizvoda.',
            'description' => "Prvi paragraf opisa.\n\nDrugi paragraf.\n\n**Osnovne karakteristike:**\nRAM: 16GB",
            'category_id' => 1,
        ]);
        $product->setRelation('attributeValues', collect());

        $settings = $this->createMock(OlxSyncSettings::class);
        $settings->method('descriptionFooter')->willReturn('<p><strong>Kontakt:</strong> <a href="https://bncshop.ba">BNC Shop</a></p>');

        $description = $builder->buildDescription($product, $settings);

        $this->assertStringContainsString('<p>Kratak opis proizvoda.</p>', $description);
        $this->assertStringContainsString('<p>Prvi paragraf opisa.', $description);
        $this->assertStringContainsString('Drugi paragraf.', $description);
        $this->assertStringContainsString('<strong>Osnovne karakteristike:</strong>', $description);
        $this->assertStringContainsString('<strong>Kontakt:</strong>', $description);
        $this->assertStringContainsString('href="https://bncshop.ba"', $description);
        $this->assertStringNotContainsString('**', $description);
    }

    public function test_preserves_html_product_description(): void
    {
        $builder = app(OlxDescriptionBuilder::class);

        $product = Product::make([
            'description' => '<p>Prvi <strong>HTML</strong> paragraf.</p><p>Drugi paragraf.</p>',
            'category_id' => 1,
        ]);
        $product->setRelation('attributeValues', collect());

        $settings = $this->createMock(OlxSyncSettings::class);
        $settings->method('descriptionFooter')->willReturn('');

        $description = $builder->buildDescription($product, $settings);

        $this->assertStringContainsString('<strong>HTML</strong>', $description);
        $this->assertStringContainsString('Drugi paragraf.', $description);
    }
}
