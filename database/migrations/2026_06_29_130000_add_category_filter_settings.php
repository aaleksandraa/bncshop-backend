<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->boolean('filter_price_enabled')->default(true)->after('icon_url');
            $table->boolean('filter_brand_enabled')->default(true)->after('filter_price_enabled');
            $table->boolean('filter_in_stock_enabled')->default(true)->after('filter_brand_enabled');
            $table->boolean('filter_on_sale_enabled')->default(true)->after('filter_in_stock_enabled');
            $table->boolean('filter_is_new_enabled')->default(true)->after('filter_on_sale_enabled');
            $table->boolean('filter_is_refurbished_enabled')->default(true)->after('filter_is_new_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn([
                'filter_price_enabled',
                'filter_brand_enabled',
                'filter_in_stock_enabled',
                'filter_on_sale_enabled',
                'filter_is_new_enabled',
                'filter_is_refurbished_enabled',
            ]);
        });
    }
};
