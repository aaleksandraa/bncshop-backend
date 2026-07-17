<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2b_products', function (Blueprint $table): void {
            $table->index(['is_active', 'b2b_category_id', 'sort_order'], 'b2b_products_catalog_idx');
            $table->index(['is_active', 'sort_order', 'name'], 'b2b_products_active_sort_idx');
        });

        Schema::table('b2b_product_images', function (Blueprint $table): void {
            $table->index(['b2b_product_id', 'is_primary'], 'b2b_product_images_primary_idx');
        });

        Schema::table('b2b_orders', function (Blueprint $table): void {
            $table->index(['b2b_customer_id', 'created_at'], 'b2b_orders_customer_created_idx');
        });

        Schema::table('b2b_categories', function (Blueprint $table): void {
            $table->index(['is_active', 'sort_order'], 'b2b_categories_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::table('b2b_products', function (Blueprint $table): void {
            $table->dropIndex('b2b_products_catalog_idx');
            $table->dropIndex('b2b_products_active_sort_idx');
        });

        Schema::table('b2b_product_images', function (Blueprint $table): void {
            $table->dropIndex('b2b_product_images_primary_idx');
        });

        Schema::table('b2b_orders', function (Blueprint $table): void {
            $table->dropIndex('b2b_orders_customer_created_idx');
        });

        Schema::table('b2b_categories', function (Blueprint $table): void {
            $table->dropIndex('b2b_categories_active_sort_idx');
        });
    }
};
