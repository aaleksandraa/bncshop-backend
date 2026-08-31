<?php

namespace Tests\Unit\Ananas;

use App\Models\Product;
use App\Services\Ananas\AnanasEligibilityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AnanasEligibilityPolicyTest extends TestCase
{
    use RefreshDatabase;

    private AnanasEligibilityPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = app(AnanasEligibilityPolicy::class);
    }

    public function test_refurbished_product_is_not_eligible(): void
    {
        $product = Product::factory()->create([
            'is_refurbished' => true,
            'is_set' => false,
            'import_source' => 'a1',
        ]);

        $result = $this->policy->evaluate($product);

        $this->assertFalse($result->eligible);
        $this->assertSame(AnanasEligibilityPolicy::REFURBISHED_OR_USED, $result->reasonCode);
    }

    public function test_set_product_is_not_eligible(): void
    {
        $product = Product::factory()->create([
            'is_refurbished' => false,
            'is_set' => true,
            'import_source' => 'manual',
        ]);

        $result = $this->policy->evaluate($product);

        $this->assertFalse($result->eligible);
        $this->assertSame(AnanasEligibilityPolicy::SET_PRODUCT, $result->reasonCode);
    }

    public function test_refurbished_is_checked_before_set(): void
    {
        $product = Product::factory()->create([
            'is_refurbished' => true,
            'is_set' => true,
        ]);

        $result = $this->policy->evaluate($product);

        $this->assertSame(AnanasEligibilityPolicy::REFURBISHED_OR_USED, $result->reasonCode);
    }

    public function test_eline_new_product_is_eligible_when_not_refurbished(): void
    {
        $product = Product::factory()->create([
            'is_refurbished' => false,
            'is_set' => false,
            'import_source' => 'eline',
            'is_new' => true,
        ]);

        $result = $this->policy->evaluate($product);

        $this->assertTrue($result->eligible);
        $this->assertNull($result->reasonCode);
    }

    public function test_standard_a1_product_is_eligible(): void
    {
        $product = Product::factory()->create([
            'is_refurbished' => false,
            'is_set' => false,
            'import_source' => 'a1',
        ]);

        $this->assertTrue($this->policy->evaluate($product)->eligible);
    }

    public function test_assert_can_export_throws_for_refurbished_product(): void
    {
        $product = Product::factory()->create([
            'is_refurbished' => true,
            'is_set' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(AnanasEligibilityPolicy::REFURBISHED_OR_USED);

        $this->policy->assertCanExport($product);
    }

    public function test_assert_can_export_allows_eligible_product(): void
    {
        $product = Product::factory()->create([
            'is_refurbished' => false,
            'is_set' => false,
        ]);

        $this->policy->assertCanExport($product);

        $this->assertTrue(true);
    }
}
