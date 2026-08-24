<?php

namespace Tests\Unit;

use App\Support\ResourceSlug;
use PHPUnit\Framework\TestCase;

class ResourceSlugTest extends TestCase
{
    public function test_rejects_nullish_and_empty_values(): void
    {
        $this->assertFalse(ResourceSlug::isUsable(null));
        $this->assertFalse(ResourceSlug::isUsable(''));
        $this->assertFalse(ResourceSlug::isUsable('   '));
        $this->assertFalse(ResourceSlug::isUsable('null'));
        $this->assertFalse(ResourceSlug::isUsable('undefined'));
        $this->assertFalse(ResourceSlug::isUsable('NaN'));
        $this->assertFalse(ResourceSlug::isUsable('../etc/passwd'));
    }

    public function test_accepts_real_slugs_including_nested_categories(): void
    {
        $this->assertTrue(ResourceSlug::isUsable('kontakt'));
        $this->assertTrue(ResourceSlug::isUsable('it-oprema/racunari'));
        $this->assertTrue(ResourceSlug::isUsable('back-to-school'));
    }
}
