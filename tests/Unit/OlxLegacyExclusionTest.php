<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\Olx\OlxExportScope;
use Tests\TestCase;

class OlxLegacyExclusionTest extends TestCase
{
    public function test_unmanaged_product_with_listing_is_legacy_protected(): void
    {
        $product = new Product([
            'olx_listing_id' => '75522399',
            'olx_managed' => false,
        ]);

        $scope = app(OlxExportScope::class);

        $this->assertTrue($scope->isLegacyProtected($product));
    }

    public function test_managed_product_without_listing_is_not_legacy_protected(): void
    {
        $product = new Product([
            'olx_listing_id' => null,
            'olx_managed' => true,
        ]);

        $scope = app(OlxExportScope::class);

        $this->assertFalse($scope->isLegacyProtected($product));
    }
}
