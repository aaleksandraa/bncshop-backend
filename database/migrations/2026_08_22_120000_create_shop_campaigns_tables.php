<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('badge_path');
            $table->string('badge_alt')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('targeting_mode', 20)->default('products');
            $table->boolean('include_subcategories')->default(true);
            $table->boolean('has_landing_page')->default(true);
            $table->string('page_title')->nullable();
            $table->text('page_description')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
        });

        Schema::create('shop_campaign_category', function (Blueprint $table) {
            $table->foreignId('shop_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['shop_campaign_id', 'category_id']);
        });

        Schema::create('shop_campaign_product', function (Blueprint $table) {
            $table->foreignId('shop_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['shop_campaign_id', 'product_id']);
        });

        Schema::create('shop_campaign_excluded_product', function (Blueprint $table) {
            $table->foreignId('shop_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['shop_campaign_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_campaign_excluded_product');
        Schema::dropIfExists('shop_campaign_product');
        Schema::dropIfExists('shop_campaign_category');
        Schema::dropIfExists('shop_campaigns');
    }
};
