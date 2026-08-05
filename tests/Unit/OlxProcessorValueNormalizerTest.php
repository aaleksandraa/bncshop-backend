<?php

namespace Tests\Unit;

use App\Services\Olx\OlxProcessorValueNormalizer;
use Tests\TestCase;

class OlxProcessorValueNormalizerTest extends TestCase
{
    public function test_maps_full_processor_strings_to_brands(): void
    {
        $normalizer = new OlxProcessorValueNormalizer;

        $this->assertSame('Intel', $normalizer->normalize('Intel Core i5-12400'));
        $this->assertSame('AMD', $normalizer->normalize('AMD Ryzen 5 5600X'));
        $this->assertSame('Apple', $normalizer->normalize('Apple M2 Pro'));
        $this->assertSame('Intel', $normalizer->normalize('Core i7-13700K'));
    }
}
