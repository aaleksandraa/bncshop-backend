<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_gratis_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('type', 16);
            $table->foreignId('gift_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('gift_quantity_per_parent')->default(1);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('until_stock')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
            $table->index('gift_product_id');
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->boolean('is_gratis_gift')->default(false)->after('is_loyalty_reward');
            $table->foreignId('parent_cart_item_id')
                ->nullable()
                ->after('is_gratis_gift')
                ->constrained('cart_items')
                ->cascadeOnDelete();
            $table->foreignId('product_gratis_offer_id')
                ->nullable()
                ->after('parent_cart_item_id')
                ->constrained('product_gratis_offers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_gratis_offer_id');
            $table->dropConstrainedForeignId('parent_cart_item_id');
            $table->dropColumn('is_gratis_gift');
        });

        Schema::dropIfExists('product_gratis_offers');
    }
};
