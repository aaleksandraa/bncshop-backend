<?php

namespace Tests\Unit;

use App\Models\ElineCategory;
use App\Models\ElineCategoryMapping;
use App\Services\Eline\ElineApiClient;
use App\Services\Eline\ElineCategoryDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElineCategoryDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_creates_categories_and_draft_mappings(): void
    {
        $client = $this->createMock(ElineApiClient::class);
        $client->method('fetchArtikli')->willReturn([
            [
                'sifra' => '1',
                'naziv' => 'Item 1',
                'grupakategorija' => 'Refurbished racunari',
            ],
            [
                'sifra' => '2',
                'naziv' => 'Item 2',
                'grupakategorija' => 'Novi racunari',
            ],
            [
                'sifra' => '3',
                'naziv' => 'Item 3',
                'grupakategorija' => 'Refurbished racunari',
            ],
        ]);

        $service = new ElineCategoryDiscoveryService($client);
        $stats = $service->discover();

        $this->assertSame(2, $stats['categories']);
        $this->assertSame(2, $stats['mappings_created']);

        $refurbished = ElineCategory::query()->where('name', 'Refurbished racunari')->first();
        $this->assertSame(2, $refurbished?->product_count);

        $mapping = ElineCategoryMapping::query()->where('eline_category_id', $refurbished?->id)->first();
        $this->assertFalse($mapping?->is_enabled);
        $this->assertSame(ElineCategoryMapping::CONDITION_REFURBISHED, $mapping?->product_condition);
    }
}
