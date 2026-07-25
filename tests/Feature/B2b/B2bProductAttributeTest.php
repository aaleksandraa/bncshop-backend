<?php

namespace Tests\Feature\B2b;

use App\Models\B2bAttributeDefinition;
use App\Models\B2bAttributeOption;
use App\Models\B2bCategory;
use App\Models\B2bProduct;
use App\Models\B2bProductAttributeValue;
use App\Services\B2b\B2bProductAttributeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\B2b\Concerns\CreatesB2bCustomers;
use Tests\TestCase;

class B2bProductAttributeTest extends TestCase
{
    use CreatesB2bCustomers;
    use RefreshDatabase;

    public function test_product_detail_includes_attributes(): void
    {
        [$category, $product] = $this->seedMonitorProduct();

        [$user] = $this->createB2bUser('attrs-detail@test.test');
        $this->loginB2bUser($user);

        $this->getJsonStateful("/api/v1/b2b/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.attributes.0.slug', 'rezolucija')
            ->assertJsonPath('data.attributes.0.values.0', '1920x1080');
    }

    public function test_products_can_be_filtered_by_attributes(): void
    {
        [$category, $productA] = $this->seedMonitorProduct('monitor-a', '1920x1080');
        $productB = $this->createMonitorProduct($category, 'monitor-b', '2560x1440');

        [$user] = $this->createB2bUser('attrs-filter@test.test');
        $this->loginB2bUser($user);

        $this->getJsonStateful('/api/v1/b2b/products?category=monitori&attrs[rezolucija][]=1920x1080')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $productA->slug);

        $this->getJsonStateful('/api/v1/b2b/products?category=monitori&attrs[rezolucija][]=2560x1440')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $productB->slug);
    }

    public function test_category_filters_endpoint_returns_distinct_values_from_active_products(): void
    {
        [$category] = $this->seedMonitorProduct('monitor-a', '1920x1080');
        $this->createMonitorProduct($category, 'monitor-b', '2560x1440');

        [$user] = $this->createB2bUser('attrs-filters@test.test');
        $this->loginB2bUser($user);

        $this->getJsonStateful('/api/v1/b2b/categories/monitori/filters')
            ->assertOk()
            ->assertJsonPath('data.category.slug', 'monitori')
            ->assertJsonFragment(['slug' => 'rezolucija', 'values' => ['1920x1080', '2560x1440']]);
    }

    public function test_attribute_sync_allows_empty_values_and_custom_options(): void
    {
        $category = B2bCategory::query()->create([
            'name' => 'Laptopi',
            'slug' => 'laptopi',
            'is_active' => true,
        ]);

        $brand = B2bAttributeDefinition::query()->create([
            'name' => 'Brend',
            'slug' => 'brend',
            'input_type' => B2bAttributeDefinition::INPUT_SELECT,
            'is_filterable' => true,
            'is_active' => true,
        ]);

        $model = B2bAttributeDefinition::query()->create([
            'name' => 'Model',
            'slug' => 'model',
            'input_type' => B2bAttributeDefinition::INPUT_TEXT,
            'is_filterable' => false,
            'is_active' => true,
        ]);

        $ports = B2bAttributeDefinition::query()->create([
            'name' => 'Priključci',
            'slug' => 'prikljucci',
            'input_type' => B2bAttributeDefinition::INPUT_MULTISELECT,
            'is_filterable' => true,
            'is_active' => true,
        ]);

        $category->attributeDefinitions()->attach([
            $brand->id => ['sort_order' => 1],
            $model->id => ['sort_order' => 2],
            $ports->id => ['sort_order' => 3],
        ]);

        $product = B2bProduct::query()->create([
            'b2b_category_id' => $category->id,
            'name' => 'Test laptop',
            'slug' => 'test-laptop',
            'regular_price' => 1000,
            'stock_quantity' => 1,
            'is_active' => true,
        ]);

        /** @var B2bProductAttributeService $service */
        $service = app(B2bProductAttributeService::class);

        $service->syncFromForm($product, [
            'attr_brend' => 'Lenovo',
            'attr_model' => 'ThinkPad X1',
            'attr_prikljucci' => ['HDMI', 'USB-C'],
        ]);

        $this->assertDatabaseHas('b2b_product_attribute_values', [
            'b2b_product_id' => $product->id,
            'b2b_attribute_definition_id' => $brand->id,
            'value' => 'Lenovo',
        ]);

        $this->assertDatabaseHas('b2b_attribute_options', [
            'b2b_attribute_definition_id' => $brand->id,
            'value' => 'Lenovo',
        ]);

        $this->assertDatabaseCount('b2b_product_attribute_values', 4);

        $service->syncFromForm($product, []);

        $this->assertDatabaseCount('b2b_product_attribute_values', 0);
    }

    /**
     * @return array{0: B2bCategory, 1: B2bProduct}
     */
    private function seedMonitorProduct(string $slug = 'monitor-a', string $resolution = '1920x1080'): array
    {
        $category = B2bCategory::query()->create([
            'name' => 'Monitori',
            'slug' => 'monitori',
            'is_active' => true,
        ]);

        $resolutionDefinition = B2bAttributeDefinition::query()->create([
            'name' => 'Rezolucija',
            'slug' => 'rezolucija',
            'input_type' => B2bAttributeDefinition::INPUT_SELECT,
            'is_filterable' => true,
            'is_active' => true,
        ]);

        B2bAttributeOption::query()->create([
            'b2b_attribute_definition_id' => $resolutionDefinition->id,
            'value' => '1920x1080',
        ]);

        B2bAttributeOption::query()->create([
            'b2b_attribute_definition_id' => $resolutionDefinition->id,
            'value' => '2560x1440',
        ]);

        $category->attributeDefinitions()->attach($resolutionDefinition->id, ['sort_order' => 1]);

        $product = $this->createMonitorProduct($category, $slug, $resolution);

        return [$category, $product];
    }

    private function createMonitorProduct(B2bCategory $category, string $slug, string $resolution): B2bProduct
    {
        $product = B2bProduct::query()->create([
            'b2b_category_id' => $category->id,
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'regular_price' => 500,
            'stock_quantity' => 3,
            'is_active' => true,
        ]);

        $definitionId = $category->attributeDefinitions()->value('b2b_attribute_definitions.id');

        B2bProductAttributeValue::query()->create([
            'b2b_product_id' => $product->id,
            'b2b_attribute_definition_id' => $definitionId,
            'value' => $resolution,
        ]);

        return $product;
    }
}
