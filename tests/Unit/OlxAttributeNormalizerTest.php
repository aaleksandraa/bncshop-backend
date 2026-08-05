<?php

namespace Tests\Unit;

use App\Services\Olx\OlxAttributeNormalizer;
use App\Models\OlxCategoryAttribute;
use Tests\TestCase;

class OlxAttributeNormalizerTest extends TestCase
{
    public function test_snaps_numeric_inch_to_closest_option(): void
    {
        $meta = new OlxCategoryAttribute([
            'input_type' => 'select',
            'options_json' => ['24', '27', '32', '34'],
        ]);

        $normalizer = new OlxAttributeNormalizer;

        $this->assertSame('27', $normalizer->snapToSelectOption('27.0', $meta));
    }

    public function test_is_valid_option_matches_select_values(): void
    {
        $meta = new OlxCategoryAttribute([
            'input_type' => 'select',
            'options_json' => ['Intel', 'AMD'],
        ]);

        $normalizer = new OlxAttributeNormalizer;

        $this->assertTrue($normalizer->isValidOption('Intel', $meta));
        $this->assertFalse($normalizer->isValidOption('Intel Core i5', $meta));
    }
}
