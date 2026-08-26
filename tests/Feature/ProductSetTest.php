<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductSetItem;
use App\Services\Catalog\ProductSetService;
use App\Services\Commerce\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductSetTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_requires_at_least_two_components(): void
    {
        $set = $this->createSetProduct();
        $component = $this->createComponentProduct('PC', 1000, 5);

        $this->expectException(ValidationException::class);

        app(ProductSetService::class)->syncSetItems($set, [
            ['component_product_id' => $component->id, 'quantity' => 1],
        ]);
    }

    public function test_set_rejects_nested_set_as_component(): void
    {
        $nestedSet = $this->createSetProduct();
        $componentA = $this->createComponentProduct('PC', 1000, 5);
        $componentB = $this->createComponentProduct('Monitor', 300, 5);

        app(ProductSetService::class)->syncSetItems($nestedSet, [
            ['component_product_id' => $componentA->id, 'quantity' => 1],
            ['component_product_id' => $componentB->id, 'quantity' => 1],
        ]);

        $set = $this->createSetProduct('Outer set');

        $this->expectException(ValidationException::class);

        app(ProductSetService::class)->syncSetItems($set, [
            ['component_product_id' => $componentA->id, 'quantity' => 1],
            ['component_product_id' => $nestedSet->id, 'quantity' => 1],
        ]);
    }

    public function test_set_price_and_stock_are_derived_from_components(): void
    {
        $set = $this->createSetProduct('Gaming set', 1499);
        $pc = $this->createComponentProduct('PC', 1199, 3);
        $monitor = $this->createComponentProduct('Monitor', 349, 4);

        app(ProductSetService::class)->syncSetItems($set, [
            ['component_product_id' => $pc->id, 'quantity' => 1],
            ['component_product_id' => $monitor->id, 'quantity' => 2],
        ]);

        $set->refresh();

        $this->assertSame(1897.0, (float) $set->regular_price);
        $this->assertSame(1499.0, (float) $set->display_price);
        $this->assertTrue($set->on_sale);
        $this->assertSame(2, $set->available_stock);
    }

    public function test_set_reserve_deducts_component_stock(): void
    {
        $set = $this->createSetProduct('Office set', 999);
        $pc = $this->createComponentProduct('PC', 800, 2);
        $monitor = $this->createComponentProduct('Monitor', 250, 5);

        app(ProductSetService::class)->syncSetItems($set, [
            ['component_product_id' => $pc->id, 'quantity' => 1],
            ['component_product_id' => $monitor->id, 'quantity' => 1],
        ]);

        $stockService = app(StockService::class);
        $stockService->reserve($set->fresh(['setItems.componentProduct']), 1);

        $pc->refresh();
        $monitor->refresh();
        $set->refresh();

        $this->assertSame(1, $pc->reserved_stock);
        $this->assertSame(1, $monitor->reserved_stock);
        $this->assertSame(1, $set->available_stock);

        $stockService->deduct($set->fresh(['setItems.componentProduct']), 1);

        $pc->refresh();
        $monitor->refresh();
        $set->refresh();

        $this->assertSame(1, $pc->api_stock);
        $this->assertSame(4, $monitor->api_stock);
        $this->assertSame(1, $set->available_stock);
    }

    public function test_product_detail_api_includes_set_items(): void
    {
        $set = $this->createSetProduct('Bundle', 1200);
        $pc = $this->createComponentProduct('PC', 900, 2);
        $monitor = $this->createComponentProduct('Monitor', 400, 2);

        app(ProductSetService::class)->syncSetItems($set, [
            ['component_product_id' => $pc->id, 'quantity' => 1],
            ['component_product_id' => $monitor->id, 'quantity' => 1],
        ]);

        $response = $this->getJson('/api/v1/products/'.$set->slug);

        $response
            ->assertOk()
            ->assertJsonPath('data.is_set', true)
            ->assertJsonCount(2, 'data.set_items')
            ->assertJsonPath('data.set_items.0.name', 'PC')
            ->assertJsonPath('data.set_items.1.name', 'Monitor');
    }

    public function test_manual_product_without_new_flag_is_refurbished(): void
    {
        $trait = new class
        {
            use \App\Filament\Resources\ProductResource\Pages\Concerns\ManagesProductSet {
                applyManualProductConditionFlags as public;
            }
        };

        $polovan = $trait->applyManualProductConditionFlags([
            'import_source' => 'manual',
            'is_set' => true,
            'is_new' => false,
        ]);

        $novo = $trait->applyManualProductConditionFlags([
            'import_source' => 'manual',
            'is_new' => true,
        ]);

        $imported = $trait->applyManualProductConditionFlags([
            'import_source' => 'a1',
            'is_new' => false,
            'is_refurbished' => false,
        ]);

        $this->assertTrue($polovan['is_refurbished']);
        $this->assertFalse($novo['is_refurbished']);
        $this->assertFalse($imported['is_refurbished']);
    }

    private function createSetProduct(string $name = 'Test set', float $price = 1000): Product
    {
        return Product::factory()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString().'-'.fake()->unique()->numberBetween(1000, 9999),
            'is_set' => true,
            'is_public' => true,
            'status' => 'active',
            'import_source' => 'manual',
            'price_locked' => true,
            'manual_price' => $price,
            'display_price' => $price,
            'regular_price' => $price,
            'api_stock' => 0,
            'available_stock' => 0,
        ]);
    }

    private function createComponentProduct(string $name, float $price, int $stock): Product
    {
        return Product::factory()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString().'-'.fake()->unique()->numberBetween(1000, 9999),
            'is_set' => false,
            'is_public' => true,
            'status' => 'active',
            'import_source' => 'manual',
            'display_price' => $price,
            'regular_price' => $price,
            'api_stock' => $stock,
            'available_stock' => $stock,
            'reserved_stock' => 0,
            'stock_status' => 'in_stock',
        ]);
    }
}
