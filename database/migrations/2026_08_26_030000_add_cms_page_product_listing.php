<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->boolean('has_product_listing')->default(false)->after('status');
        });

        Schema::create('cms_page_product', function (Blueprint $table): void {
            $table->foreignId('cms_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['cms_page_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_page_product');

        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->dropColumn('has_product_listing');
        });
    }
};
