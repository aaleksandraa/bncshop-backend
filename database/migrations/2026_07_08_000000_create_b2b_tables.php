<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_b2b_customer')->default(false)->after('is_customer');
        });

        Schema::create('b2b_settings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('default_customer_discount_percent', 5, 2)->default(0);
            $table->string('admin_notification_email')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('b2b_categories')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('b2b_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_category_id')->constrained('b2b_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('regular_price', 12, 2);
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->boolean('exclude_customer_discount')->default(false);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->string('sku')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('b2b_product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_product_id')->constrained('b2b_products')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('b2b_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('discount_type'); // percent | fixed_price
            $table->decimal('value', 12, 2);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('badge_text')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_campaign_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_campaign_id')->constrained('b2b_campaigns')->cascadeOnDelete();
            $table->foreignId('b2b_product_id')->constrained('b2b_products')->cascadeOnDelete();
            $table->unique(['b2b_campaign_id', 'b2b_product_id']);
        });

        Schema::create('b2b_access_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('email');
            $table->string('company_name');
            $table->text('company_address');
            $table->string('jib');
            $table->string('pdv_number')->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('company_name');
            $table->text('company_address');
            $table->string('jib');
            $table->string('pdv_number')->nullable();
            $table->string('phone');
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('b2b_password_setup_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('token');
        });

        Schema::create('b2b_carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_customer_id')->unique()->constrained('b2b_customers')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('b2b_cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_cart_id')->constrained('b2b_carts')->cascadeOnDelete();
            $table->foreignId('b2b_product_id')->constrained('b2b_products')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['b2b_cart_id', 'b2b_product_id']);
        });

        Schema::create('b2b_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('b2b_customer_id')->constrained('b2b_customers');
            $table->string('status')->default('nova');
            $table->string('payment_method')->default('invoice');
            $table->string('company_name');
            $table->text('company_address');
            $table->string('jib');
            $table->string('pdv_number')->nullable();
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone');
            $table->text('shipping_address');
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        Schema::create('b2b_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_order_id')->constrained('b2b_orders')->cascadeOnDelete();
            $table->foreignId('b2b_product_id')->nullable()->constrained('b2b_products')->nullOnDelete();
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_regular_price', 12, 2);
            $table->decimal('unit_final_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->decimal('customer_discount_percent', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('b2b_order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_order_id')->constrained('b2b_orders')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_order_status_histories');
        Schema::dropIfExists('b2b_order_items');
        Schema::dropIfExists('b2b_orders');
        Schema::dropIfExists('b2b_cart_items');
        Schema::dropIfExists('b2b_carts');
        Schema::dropIfExists('b2b_password_setup_tokens');
        Schema::dropIfExists('b2b_customers');
        Schema::dropIfExists('b2b_access_requests');
        Schema::dropIfExists('b2b_campaign_product');
        Schema::dropIfExists('b2b_campaigns');
        Schema::dropIfExists('b2b_product_images');
        Schema::dropIfExists('b2b_products');
        Schema::dropIfExists('b2b_categories');
        Schema::dropIfExists('b2b_settings');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_b2b_customer');
        });
    }
};
