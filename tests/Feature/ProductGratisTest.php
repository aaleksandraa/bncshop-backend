<?php

namespace Tests\Feature;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGratisOffer;
use App\Models\ShippingRule;
use App\Models\SystemSetting;
use App\Services\Catalog\ProductGratisService;
use App\Services\Commerce\CartService;
use App\Services\Commerce\CheckoutService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductGratisTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sync_text_and_product_offers_on_same_product(): void
    {
        $parent = $this->createProduct('Laptop', 1200, 5);
        $gift = $this->createProduct('Miš', 25, 10);

        app(ProductGratisService::class)->syncOffers($parent, [
            [
                'type' => ProductGratisOffer::TYPE_TEXT,
                'title' => 'Besplatna dostava',
                'description' => 'Promo tekst',
                'is_active' => true,
            ],
            [
                'type' => ProductGratisOffer::TYPE_PRODUCT,
                'gift_product_id' => $gift->id,
                'gift_quantity_per_parent' => 1,
                'title' => 'Miš gratis',
                'is_active' => true,
            ],
        ]);

        $this->assertDatabaseCount('product_gratis_offers', 2);
        $this->assertSame(2, $parent->fresh()->gratisOffers()->count());
    }

    public function test_api_returns_gratis_offers_on_detail_and_listing(): void
    {
        $parent = $this->createProduct('Laptop API', 1200, 5);
        $gift = $this->createProduct('Torba', 40, 8);

        app(ProductGratisService::class)->syncOffers($parent, [
            [
                'type' => ProductGratisOffer::TYPE_PRODUCT,
                'gift_product_id' => $gift->id,
                'gift_quantity_per_parent' => 1,
                'title' => 'Torba gratis',
                'is_active' => true,
            ],
        ]);

        $this->getJson('/api/v1/products/'.$parent->slug)
            ->assertOk()
            ->assertJsonPath('data.gratis_offers.0.title', 'Torba gratis')
            ->assertJsonPath('data.gratis_offers.0.type', ProductGratisOffer::TYPE_PRODUCT);

        $this->getJson('/api/v1/products?per_page=24')
            ->assertOk()
            ->assertJsonFragment([
                'slug' => $parent->slug,
            ]);
    }

    public function test_product_listing_survives_missing_gratis_offers_table(): void
    {
        $product = $this->createProduct('Listing without gratis table', 199, 3);

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_gratis_offer_id');
        });
        Schema::dropIfExists('product_gratis_offers');

        $this->getJson('/api/v1/products?per_page=8')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $product->slug)
            ->assertJsonPath('data.0.gratis_offers', []);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.gratis_offers', []);
    }

    public function test_adding_parent_to_cart_creates_zero_price_gift_line(): void
    {
        [$parent, $gift] = $this->seedProductGiftPair();
        $sessionId = (string) Str::uuid();

        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $parent->id,
            'quantity' => 1,
        ], [
            'X-Cart-Session' => $sessionId,
        ]);

        $response->assertCreated();

        $cart = app(CartService::class)->getOrCreate($sessionId);
        $giftLine = $cart->items()->where('is_gratis_gift', true)->first();

        $this->assertNotNull($giftLine);
        $this->assertSame($gift->id, $giftLine->product_id);
        $this->assertSame(1, $giftLine->quantity);
        $this->assertSame(0.0, (float) $giftLine->unit_price);
    }

    public function test_updating_parent_quantity_scales_gift_quantity(): void
    {
        [$parent, $gift] = $this->seedProductGiftPair(giftQtyPerParent: 2);
        $cartService = app(CartService::class);
        $cart = $cartService->getOrCreate((string) Str::uuid());
        $parentItem = $cartService->addItem($cart, $parent, 1);

        $cartService->updateItem($parentItem, 2);

        $giftLine = $cart->fresh(CartService::CART_RELATIONS)
            ->items
            ->firstWhere('is_gratis_gift', true);

        $this->assertNotNull($giftLine);
        $this->assertSame(4, $giftLine->quantity);
    }

    public function test_removing_parent_removes_gift_line(): void
    {
        [$parent] = $this->seedProductGiftPair();
        $cartService = app(CartService::class);
        $cart = $cartService->getOrCreate((string) Str::uuid());
        $parentItem = $cartService->addItem($cart, $parent, 1);

        $cartService->removeItem($parentItem);

        $this->assertSame(0, $cart->fresh()->items()->count());
    }

    public function test_until_stock_offer_is_hidden_when_gift_is_out_of_stock(): void
    {
        $parent = $this->createProduct('Monitor', 500, 5);
        $gift = $this->createProduct('Kablo', 10, 0);

        app(ProductGratisService::class)->syncOffers($parent, [
            [
                'type' => ProductGratisOffer::TYPE_PRODUCT,
                'gift_product_id' => $gift->id,
                'gift_quantity_per_parent' => 1,
                'until_stock' => true,
                'is_active' => true,
            ],
        ]);

        $offers = app(ProductGratisService::class)->activeOffersFor($parent->fresh());
        $this->assertCount(0, $offers);

        $cartService = app(CartService::class);
        $cart = $cartService->getOrCreate((string) Str::uuid());
        $cartService->addItem($cart, $parent, 1);

        $this->assertFalse($cart->fresh()->items()->where('is_gratis_gift', true)->exists());
    }

    public function test_adding_parent_fails_when_required_gift_is_out_of_stock(): void
    {
        $parent = $this->createProduct('Tipkovnica', 120, 5);
        $gift = $this->createProduct('Podloga', 15, 0);

        app(ProductGratisService::class)->syncOffers($parent, [
            [
                'type' => ProductGratisOffer::TYPE_PRODUCT,
                'gift_product_id' => $gift->id,
                'gift_quantity_per_parent' => 1,
                'until_stock' => false,
                'is_active' => true,
            ],
        ]);

        $this->expectException(ValidationException::class);

        app(CartService::class)->addItem(
            app(CartService::class)->getOrCreate((string) Str::uuid()),
            $parent,
            1,
        );
    }

    public function test_expired_offer_is_not_returned_by_api(): void
    {
        $parent = $this->createProduct('Tablet', 300, 3);

        ProductGratisOffer::query()->create([
            'product_id' => $parent->id,
            'type' => ProductGratisOffer::TYPE_TEXT,
            'title' => 'Stara ponuda',
            'ends_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/products/'.$parent->slug)
            ->assertOk()
            ->assertJsonPath('data.gratis_offers', []);
    }

    public function test_text_only_offer_is_visible_but_not_added_to_cart(): void
    {
        $parent = $this->createProduct('Telefon', 800, 4);

        app(ProductGratisService::class)->syncOffers($parent, [
            [
                'type' => ProductGratisOffer::TYPE_TEXT,
                'title' => 'Poklon pakovanje',
                'is_active' => true,
            ],
        ]);

        $this->getJson('/api/v1/products/'.$parent->slug)
            ->assertOk()
            ->assertJsonPath('data.gratis_offers.0.type', ProductGratisOffer::TYPE_TEXT);

        $cartService = app(CartService::class);
        $cart = $cartService->getOrCreate((string) Str::uuid());
        $cartService->addItem($cart, $parent, 1);

        $this->assertSame(1, $cart->fresh()->items()->count());
        $this->assertFalse($cart->items()->where('is_gratis_gift', true)->exists());
    }

    public function test_checkout_order_item_contains_gratis_snapshot(): void
    {
        $this->seedCheckoutSettings();
        [$parent] = $this->seedProductGiftPair();
        $sessionId = (string) Str::uuid();

        app(CartService::class)->addItem(
            app(CartService::class)->getOrCreate($sessionId),
            $parent,
            1,
        );

        $response = $this->postJson('/api/v1/checkout', $this->checkoutPayload(), [
            'X-Cart-Session' => $sessionId,
        ]);

        $response->assertCreated();

        $gratisItem = OrderItem::query()->where('final_price', 0)
            ->whereJsonContains('discount_snapshot->gratis_gift', true)
            ->first();

        $this->assertNotNull($gratisItem);
        $this->assertSame($parent->id, $gratisItem->discount_snapshot['parent_product_id'] ?? null);
    }

    /**
     * @return array{0: Product, 1: Product}
     */
    private function seedProductGiftPair(int $giftQtyPerParent = 1): array
    {
        $parent = $this->createProduct('Glavni proizvod', 999, 5);
        $gift = $this->createProduct('Gift proizvod', 49, 20);

        app(ProductGratisService::class)->syncOffers($parent, [
            [
                'type' => ProductGratisOffer::TYPE_PRODUCT,
                'gift_product_id' => $gift->id,
                'gift_quantity_per_parent' => $giftQtyPerParent,
                'title' => 'Gift gratis',
                'is_active' => true,
            ],
        ]);

        return [$parent->fresh(), $gift->fresh()];
    }

    private function createProduct(string $name, float $price, int $stock): Product
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
            'stock_status' => $stock > 0 ? 'in_stock' : 'out_of_stock',
        ]);
    }

    private function seedCheckoutSettings(array $overrides = []): void
    {
        SystemSetting::query()->create([
            'key' => 'checkout',
            'group' => 'checkout',
            'value' => array_merge([
                'payment_methods' => ['pay_on_delivery', 'bank_transfer'],
                'shipping_methods' => ['delivery', 'pickup'],
                'guest_checkout_enabled' => true,
            ], $overrides),
        ]);

        ShippingRule::factory()->create([
            'type' => 'global',
            'fixed_fee' => 5,
            'free_threshold' => null,
            'is_active' => true,
            'priority' => 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutPayload(): array
    {
        return [
            'first_name' => 'Test',
            'last_name' => 'Kupac',
            'phone' => '061123456',
            'email' => 'test@example.com',
            'address' => 'Ulica 1',
            'city' => 'Sarajevo',
            'postal_code' => '71000',
            'shipping_method' => 'pickup',
            'payment_method' => 'pay_on_delivery',
            'accepted_terms' => true,
        ];
    }
}
