<?php

namespace Tests\Unit;

use App\Services\Sync\SupplierNameNormalizer;
use Tests\TestCase;

class SupplierNameNormalizerTest extends TestCase
{
    public function test_comtrade_aliases_are_normalized(): void
    {
        $normalizer = new SupplierNameNormalizer;

        $this->assertSame([
            'display_name' => 'Comtrade',
            'code' => 'comtrade',
        ], $normalizer->normalize('comtrade'));
    }

    public function test_arbis_aliases_are_normalized(): void
    {
        $normalizer = new SupplierNameNormalizer;

        $this->assertSame([
            'display_name' => 'Arbis',
            'code' => 'arbis',
        ], $normalizer->normalize('arbis'));
    }
}
