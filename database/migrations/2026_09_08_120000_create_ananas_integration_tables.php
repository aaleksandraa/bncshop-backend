<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ananas_product_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ananas_category_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('ananas_product_type');
            $table->string('ananas_category')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('include_descendants')->default(true);
            $table->timestamps();

            $table->unique('category_id');
            $table->index(['is_enabled', 'category_id']);
        });

        Schema::create('ananas_product_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->boolean('export_enabled')->default(false);
            $table->string('ean')->nullable();
            $table->string('sku')->nullable();
            $table->string('external_id')->nullable();
            $table->string('ananas_product_id')->nullable();
            $table->string('merchant_inventory_id')->nullable();
            $table->string('ananas_code')->nullable();
            $table->string('group_id')->nullable();
            $table->string('remote_status')->nullable();
            $table->string('local_status')->default('DISABLED');
            $table->boolean('ean_exists_on_ananas')->nullable();
            $table->uuid('last_progress_id')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->string('stock_hash', 64)->nullable();
            $table->string('price_hash', 64)->nullable();
            $table->timestamp('last_submitted_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('remote_modified_at')->nullable();
            $table->timestamps();

            $table->index('ean');
            $table->index('merchant_inventory_id');
            $table->index('local_status');
            $table->index('export_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ananas_product_mappings');
        Schema::dropIfExists('ananas_category_mappings');
        Schema::dropIfExists('ananas_product_types');
    }
};
