<?php

namespace Tests\Unit;

use App\Models\ElineCategoryMapping;
use App\Services\Eline\ElineApiClient;
use App\Services\Eline\ElineSupport;
use Tests\TestCase;

class ElineApiClientTest extends TestCase
{
    public function test_plain_text_description_preserves_line_breaks_from_feed(): void
    {
        $normalized = ElineSupport::plainTextDescription(
            "Dijagonala (inch): 24\r\nVrsta: IPS \r\nBoja: Crna \r\nDisplayPort: 0\r\nGarancija (mjeseci): 12",
            null,
        );

        $this->assertSame(
            "Dijagonala (inch): 24\nVrsta: IPS\nBoja: Crna\nDisplayPort: 0\nGarancija (mjeseci): 12",
            $normalized,
        );
        $this->assertStringNotContainsString("\r", $normalized);
    }

    public function test_plain_text_description_converts_html_blocks_to_line_breaks(): void
    {
        $normalized = ElineSupport::plainTextDescription(
            null,
            '<p>Dijagonala (inch): 24</p><p>Vrsta: LED</p>',
        );

        $this->assertSame("Dijagonala (inch): 24\nVrsta: LED", $normalized);
    }

    public function test_build_price_map_indexes_by_sifra(): void
    {
        $client = new ElineApiClient;

        $map = $client->buildPriceMap([
            ['sifra' => '10', 'mpc' => 100],
            ['sifra' => '20', 'mpc' => 200],
        ]);

        $this->assertSame(100, $map['10']['mpc']);
        $this->assertSame(200, $map['20']['mpc']);
    }

    public function test_merge_product_data_combines_article_and_price(): void
    {
        $client = new ElineApiClient;

        $items = $client->mergeProductData(
            [[
                'sifra' => '10',
                'naziv' => 'Test laptop',
                'grupakategorija' => 'Refurbished racunari',
                'aktivan' => 255,
                'opis' => '',
                'htmlOpis' => '<p>Opis</p>',
            ]],
            ['10' => ['sifra' => '10', 'mpc' => 165.55, 'stanje' => 3, 'aktivan' => 255]],
        );

        $this->assertCount(1, $items);
        $this->assertSame('10', $items[0]['sifra']);
        $this->assertSame(165.55, $items[0]['mpc']);
        $this->assertSame(3, $items[0]['stanje']);
        $this->assertSame('Refurbished racunari', $items[0]['eline_category']);
        $this->assertSame('Opis', $items[0]['opis']);
    }

    public function test_support_infers_condition_from_category_name(): void
    {
        $this->assertSame(
            ElineCategoryMapping::CONDITION_REFURBISHED,
            ElineSupport::inferCondition('Refurbished racunari'),
        );

        $this->assertSame(
            ElineCategoryMapping::CONDITION_NEW,
            ElineSupport::inferCondition('Novi racunari'),
        );

        $this->assertNull(ElineSupport::inferCondition('Periferija'));
    }

    public function test_external_product_id_is_deterministic(): void
    {
        $first = ElineSupport::externalProductId('42');
        $second = ElineSupport::externalProductId('42');

        $this->assertSame($first, $second);
        $this->assertNotSame(ElineSupport::externalProductId('43'), $first);
    }
}
