<?php

namespace Tests\Unit\Ananas;

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\Ananas\AnanasPackageWeightResolver;
use App\Services\Ananas\AnanasPackageWeightResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnanasPackageWeightResolverTest extends TestCase
{
    use RefreshDatabase;

    private AnanasPackageWeightResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(AnanasPackageWeightResolver::class);
    }

    public function test_parses_kg_with_dot_decimal(): void
    {
        $result = $this->resolveWithRawValue('Težina', '1.5 kg');

        $this->assertTrue($result->isOk());
        $this->assertSame(1.5, $result->resolvedWeightKg);
    }

    public function test_parses_kg_with_comma_decimal(): void
    {
        $result = $this->resolveWithRawValue('Težina', '1,5 kg');

        $this->assertTrue($result->isOk());
        $this->assertSame(1.5, $result->resolvedWeightKg);
    }

    public function test_parses_grams_to_kg(): void
    {
        $result = $this->resolveWithRawValue('Težina', '184 g');

        $this->assertTrue($result->isOk());
        $this->assertSame(0.184, $result->resolvedWeightKg);
    }

    public function test_parses_grams_without_space(): void
    {
        $result = $this->resolveWithRawValue('Težina', '184g');

        $this->assertTrue($result->isOk());
        $this->assertSame(0.184, $result->resolvedWeightKg);
    }

    public function test_parses_uppercase_kg(): void
    {
        $result = $this->resolveWithRawValue('Težina', '0.75 KG');

        $this->assertTrue($result->isOk());
        $this->assertSame(0.75, $result->resolvedWeightKg);
    }

    public function test_trims_whitespace_and_nbsp(): void
    {
        $result = $this->resolveWithRawValue('Težina', " \u{00A0}2 kg ");

        $this->assertTrue($result->isOk());
        $this->assertSame(2.0, $result->resolvedWeightKg);
    }

    public function test_rejects_zero(): void
    {
        $result = $this->resolveWithRawValue('Težina', '0 kg');

        $this->assertSame(AnanasPackageWeightResult::STATUS_ZERO_OR_NEGATIVE, $result->parseStatus);
    }

    public function test_rejects_negative(): void
    {
        $result = $this->resolveWithRawValue('Težina', '-1 kg');

        $this->assertSame(AnanasPackageWeightResult::STATUS_ZERO_OR_NEGATIVE, $result->parseStatus);
    }

    public function test_rejects_non_numeric_text(): void
    {
        $result = $this->resolveWithRawValue('Težina', 'teško');

        $this->assertSame(AnanasPackageWeightResult::STATUS_UNPARSEABLE, $result->parseStatus);
    }

    public function test_bare_decimal_on_approved_chain_assumes_kg(): void
    {
        $result = $this->resolveWithRawValue('Bruto težina', '8.5');

        $this->assertTrue($result->isOk());
        $this->assertSame(8.5, $result->resolvedWeightKg);
    }

    public function test_bare_integer_on_approved_chain_interprets_large_values_as_grams(): void
    {
        $result = $this->resolveWithRawValue('Težina', '184');

        $this->assertTrue($result->isOk());
        $this->assertSame(0.184, $result->resolvedWeightKg);
    }

    public function test_unitless_number_on_untrusted_attribute_is_ambiguous(): void
    {
        $result = $this->resolveWithRawValue('Masa proizvoda', '184');

        $this->assertSame(AnanasPackageWeightResult::STATUS_UNITLESS_AMBIGUOUS, $result->parseStatus);
    }

    public function test_missing_attribute_returns_missing(): void
    {
        $product = Product::factory()->create();

        $result = $this->resolver->resolve($product);

        $this->assertSame(AnanasPackageWeightResult::STATUS_MISSING, $result->parseStatus);
    }

    public function test_uses_first_chain_attribute_with_value(): void
    {
        $product = Product::factory()->create();
        $this->attachRawValue($product, 'Neto težina', '9 kg');
        $this->attachRawValue($product, 'Težina', '1 kg');

        $result = $this->resolver->resolve($product);

        $this->assertTrue($result->isOk());
        $this->assertSame('Težina', $result->sourceAttributeName);
        $this->assertSame(1.0, $result->resolvedWeightKg);
    }

    public function test_prefers_higher_priority_chain_name(): void
    {
        $product = Product::factory()->create();
        $this->attachRawValue($product, 'Bruto težina pakovanja', '2.5 kg');
        $this->attachRawValue($product, 'Težina', '1 kg');

        $result = $this->resolver->resolve($product);

        $this->assertSame('Bruto težina pakovanja', $result->sourceAttributeName);
        $this->assertSame(2.5, $result->resolvedWeightKg);
    }

    public function test_parses_numeric_raw_with_display_unit_grams(): void
    {
        $product = Product::factory()->create();
        $definition = AttributeDefinition::query()->create([
            'external_attribute_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Težina',
            'display_name' => 'Težina',
            'display_unit' => 'g',
            'internal_type' => 'number',
            'is_public' => true,
        ]);

        ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $definition->id,
            'attribute_name_snapshot' => 'Težina',
            'raw_value' => '184',
            'normalized_value' => '184',
            'normalized_type' => 'number',
        ]);

        $result = $this->resolver->resolve($product->fresh(['attributeValues.attributeDefinition']));

        $this->assertTrue($result->isOk());
        $this->assertSame(0.184, $result->resolvedWeightKg);
    }

    public function test_parses_embedded_weight_from_long_raw_value(): void
    {
        $result = $this->resolveWithRawValue('Težina', 'Težina proizvoda: 2,5 kg');

        $this->assertTrue($result->isOk());
        $this->assertSame(2.5, $result->resolvedWeightKg);
    }

    public function test_fuzzy_weight_definition_name_is_discovered(): void
    {
        $product = Product::factory()->create();
        $definition = AttributeDefinition::query()->create([
            'external_attribute_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Neto težina proizvoda',
            'display_name' => 'Neto težina proizvoda',
            'internal_type' => 'text',
            'is_public' => true,
        ]);

        ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $definition->id,
            'attribute_name_snapshot' => 'Neto težina proizvoda',
            'raw_value' => '750 g',
            'normalized_value' => '750 g',
            'normalized_type' => 'text',
        ]);

        $result = $this->resolver->resolve($product->fresh(['attributeValues.attributeDefinition']));

        $this->assertTrue($result->isOk());
        $this->assertSame(0.75, $result->resolvedWeightKg);
    }

    private function resolveWithRawValue(string $attributeName, string $rawValue): AnanasPackageWeightResult
    {
        $product = Product::factory()->create();
        $this->attachRawValue($product, $attributeName, $rawValue);

        return $this->resolver->resolve($product->fresh(['attributeValues.attributeDefinition']));
    }

    private function attachRawValue(Product $product, string $attributeName, string $rawValue): void
    {
        $definition = AttributeDefinition::query()->create([
            'external_attribute_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => $attributeName,
            'display_name' => $attributeName,
            'internal_type' => 'text',
            'is_public' => true,
        ]);

        ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $definition->id,
            'attribute_name_snapshot' => $attributeName,
            'raw_value' => $rawValue,
            'normalized_value' => $rawValue,
            'normalized_type' => 'text',
        ]);
    }
}
