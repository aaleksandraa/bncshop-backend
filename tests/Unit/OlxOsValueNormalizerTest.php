<?php

namespace Tests\Unit;

use App\Services\Olx\OlxOsValueNormalizer;
use Tests\TestCase;

class OlxOsValueNormalizerTest extends TestCase
{
    public function test_maps_imported_os_values_to_olx_options(): void
    {
        $normalizer = new OlxOsValueNormalizer;

        $this->assertSame('Nema', $normalizer->normalize('FreeDOS'));
        $this->assertSame('Nema', $normalizer->normalize('Bez OS-a'));
        $this->assertSame('Win 11', $normalizer->normalize('Windows 11 Home'));
        $this->assertSame('Win 10', $normalizer->normalize('Microsoft Windows 10 Pro'));
        $this->assertSame('Mac OS', $normalizer->normalize('Apple Mac OS'));
        $this->assertSame('Linux', $normalizer->normalize('Ubuntu Linux'));
    }
}
