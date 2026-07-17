<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_customer')->default(false)->after('password');
            $table->string('phone')->nullable()->after('is_customer');
        });

        Schema::create('api_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('target_system_code');
            $table->string('base_url');
            $table->text('username')->nullable();
            $table->text('password')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->unsignedInteger('page_size')->default(500);
            $table->unsignedInteger('sync_interval_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->string('connection_status')->default('disconnected');
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_manufacturer_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('external_id')->nullable();
            $table->boolean('system')->default(true);
            $table->boolean('featured')->default(false);
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('logo_url')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_category_id')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('full_slug')->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('external_id')->nullable();
            $table->uuid('external_parent_id')->nullable();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('path')->nullable();
            $table->uuid('margin_id')->nullable();
            $table->string('margin_name')->nullable();
            $table->decimal('margin_percentage', 8, 2)->nullable();
            $table->unsignedInteger('olx_id')->nullable();
            $table->string('olx_name')->nullable();
            $table->boolean('system')->default(true);
            $table->boolean('pending_parent')->default(false);
            $table->string('status')->default('active');
            $table->string('image_url')->nullable();
            $table->string('icon_url')->nullable();
            $table->timestamps();
        });

        Schema::create('category_seo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_image_url')->nullable();
            $table->string('h1')->nullable();
            $table->text('intro_text')->nullable();
            $table->text('footer_text')->nullable();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_supplier_id')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_product_id')->unique();
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->text('name');
            $table->text('slug');
            $table->unique('slug');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->longText('description')->nullable();
            $table->text('short_description')->nullable();
            $table->boolean('is_gaming')->default(false);
            $table->boolean('is_public')->default(false);
            $table->boolean('is_new')->default(false);
            $table->string('status')->default('active');
            $table->decimal('margin_percentage', 8, 2)->nullable();
            $table->decimal('api_price', 12, 2)->nullable();
            $table->decimal('api_final_price', 12, 2)->nullable();
            $table->decimal('regular_price', 12, 2)->nullable();
            $table->decimal('display_price', 12, 2)->nullable();
            $table->decimal('api_rebate', 12, 2)->nullable();
            $table->timestamp('api_rebate_valid_until')->nullable();
            $table->unsignedTinyInteger('api_rebate_type')->nullable();
            $table->integer('api_stock')->default(0);
            $table->integer('reserved_stock')->default(0);
            $table->integer('available_stock')->default(0);
            $table->integer('manual_stock_override')->nullable();
            $table->string('stock_status')->default('in_stock');
            $table->boolean('allow_backorder')->default(false);
            $table->boolean('price_locked')->default(false);
            $table->decimal('manual_price', 12, 2)->nullable();
            $table->unsignedBigInteger('default_image_id')->nullable();
            $table->unsignedInteger('api_views_count')->default(0);
            $table->timestamp('first_imported_at')->nullable();
            $table->string('sync_status')->default('synced');
            $table->timestamp('marked_missing_at')->nullable();
            $table->string('olx_listing_id')->nullable();
            $table->timestamp('olx_synced_at')->nullable();
            $table->text('olx_last_error')->nullable();
            $table->timestamps();

            $table->index(['is_public', 'status']);
            $table->index('barcode');
        });

        Schema::create('product_sync_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('field_name');
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at');
            $table->unique(['product_id', 'field_name']);
        });

        Schema::create('sync_diff_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('field_name');
            $table->text('api_value')->nullable();
            $table->text('local_value')->nullable();
            $table->timestamp('logged_at');
        });

        Schema::create('attribute_definitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_attribute_id')->unique();
            $table->string('name');
            $table->string('external_id')->nullable();
            $table->unsignedInteger('olx_id')->nullable();
            $table->string('olx_name')->nullable();
            $table->unsignedTinyInteger('api_type')->nullable();
            $table->string('internal_type')->default('text');
            $table->boolean('is_public')->default(true);
            $table->boolean('is_filter')->default(false);
            $table->boolean('olx_required')->default(false);
            $table->json('options_json')->nullable();
            $table->json('parsed_options')->nullable();
            $table->timestamps();
        });

        Schema::create('attribute_category_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->uuid('external_category_id')->nullable();
            $table->string('category_name')->nullable();
            $table->boolean('is_filter_enabled')->default(true);
            $table->boolean('is_public_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['attribute_definition_id', 'category_id'], 'attr_cat_unique');
        });

        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_definition_id')->constrained()->cascadeOnDelete();
            $table->string('attribute_name_snapshot');
            $table->text('raw_value');
            $table->text('normalized_value')->nullable();
            $table->string('normalized_type')->default('text');
            $table->boolean('is_locked')->default(false);
            $table->unique(['product_id', 'attribute_definition_id'], 'prod_attr_unique');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->uuid('external_image_id')->nullable();
            $table->string('image_url');
            $table->string('source_url')->nullable();
            $table->string('public_url')->nullable();
            $table->string('content_type')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('product_supplier_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_sku')->nullable();
            $table->decimal('supplier_price', 12, 2)->nullable();
            $table->integer('supplier_stock')->default(0);
            $table->boolean('is_selected_price_source')->default(false);
            $table->unique(['product_id', 'supplier_id']);
        });

        Schema::create('product_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_price', 12, 2)->nullable();
            $table->decimal('new_price', 12, 2);
            $table->string('source');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('product_tags', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'tag_id']);
        });

        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('discount_type');
            $table->decimal('value', 12, 2);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('badge_text')->nullable();
            $table->boolean('combines_with_coupons')->default(false);
            $table->boolean('include_subcategories')->default(false);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tag_id')->nullable()->constrained()->nullOnDelete();
            $table->json('conditions_json')->nullable();
            $table->timestamps();
        });

        Schema::create('discount_excluded_products', function (Blueprint $table) {
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['discount_id', 'product_id']);
        });

        Schema::create('discount_excluded_brands', function (Blueprint $table) {
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manufacturer_id')->constrained()->cascadeOnDelete();
            $table->primary(['discount_id', 'manufacturer_id']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type');
            $table->decimal('value', 12, 2);
            $table->decimal('min_cart_amount', 12, 2)->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('applicable_to')->nullable();
            $table->boolean('single_use_per_customer')->default(false);
            $table->timestamps();
        });

        Schema::create('shipping_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('global');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('fixed_fee', 12, 2)->default(0);
            $table->decimal('free_threshold', 12, 2)->nullable();
            $table->boolean('pickup_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('jib')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('address');
            $table->string('city');
            $table->string('postal_code');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coupon_code')->nullable();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->json('discount_snapshot')->nullable();
            $table->boolean('price_confirmed')->default(true);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('tracking_token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('nova');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('address');
            $table->string('city');
            $table->string('postal_code');
            $table->string('company_name')->nullable();
            $table->string('jib')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('shipping_method');
            $table->json('shipping_rule_snapshot')->nullable();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method')->default('pay_on_delivery');
            $table->unsignedInteger('items_count')->default(0);
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('external_product_id')->nullable();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('category_path')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('final_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 12, 2);
            $table->string('supplier_sku')->nullable();
            $table->string('supplier_name')->nullable();
            $table->json('attributes_snapshot')->nullable();
            $table->json('discount_snapshot')->nullable();
            $table->foreignId('discount_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('used_at');
        });

        Schema::create('seo_overrides', function (Blueprint $table) {
            $table->id();
            $table->morphs('seoable');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_image_url')->nullable();
            $table->string('canonical')->nullable();
            $table->string('robots')->default('index,follow');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->timestamps();
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('session_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->index();
        });

        Schema::create('daily_sales_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('revenue', 14, 2)->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('items_sold')->default(0);
            $table->decimal('avg_order_value', 12, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('api_import_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_source_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->timestamp('sync_started_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('stats')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('api_import_job_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_import_job_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page');
            $table->unsignedInteger('records_count')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->json('errors')->nullable();
            $table->timestamps();
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('subject');
            $table->longText('body_html');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->string('group')->default('general');
            $table->timestamps();
        });

        Schema::create('report_cache', function (Blueprint $table) {
            $table->id();
            $table->string('report_key');
            $table->string('params_hash');
            $table->json('data');
            $table->timestamp('expires_at');
            $table->unique(['report_key', 'params_hash']);
        });
    }

    public function down(): void
    {
        $tables = [
            'report_cache', 'system_settings', 'email_templates', 'api_import_job_items',
            'api_import_jobs', 'daily_sales_snapshots', 'analytics_events', 'redirects',
            'seo_overrides', 'order_status_history', 'order_items', 'orders', 'cart_items',
            'carts', 'customer_addresses', 'customers', 'shipping_rules', 'coupon_usages',
            'coupons', 'discount_excluded_brands', 'discount_excluded_products', 'discounts',
            'product_tags', 'tags', 'product_price_history', 'product_supplier_offers',
            'product_images', 'product_attribute_values', 'attribute_category_mappings',
            'attribute_definitions', 'sync_diff_log', 'product_sync_locks', 'products',
            'suppliers', 'category_seo', 'categories', 'manufacturers', 'api_sources',
        ];
        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_customer', 'phone']);
        });
    }
};
