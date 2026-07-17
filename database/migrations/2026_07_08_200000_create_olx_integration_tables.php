<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olx_categories', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->unsignedInteger('parent_id')->nullable();
            $table->string('path')->nullable();
            $table->boolean('brand_required')->default(false);
            $table->boolean('show_condition')->default(true);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });

        Schema::create('olx_category_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('olx_category_id');
            $table->unsignedInteger('olx_attribute_id');
            $table->string('name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('input_type')->nullable();
            $table->boolean('required')->default(false);
            $table->json('options_json')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['olx_category_id', 'olx_attribute_id']);
            $table->foreign('olx_category_id')->references('id')->on('olx_categories')->cascadeOnDelete();
        });

        Schema::create('olx_category_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->unsignedInteger('olx_category_id');
            $table->string('olx_category_path')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('include_descendants')->default(true);
            $table->timestamps();

            $table->unique('category_id');
            $table->foreign('olx_category_id')->references('id')->on('olx_categories')->cascadeOnDelete();
        });

        Schema::create('olx_attribute_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('olx_category_id');
            $table->unsignedInteger('olx_attribute_id');
            $table->foreignId('attribute_definition_id')->nullable()->constrained('attribute_definitions')->nullOnDelete();
            $table->json('bnc_attribute_aliases')->nullable();
            $table->string('parser_pattern')->nullable();
            $table->string('default_value')->nullable();
            $table->json('value_mappings')->nullable();
            $table->boolean('is_required_for_publish')->default(false);
            $table->timestamps();

            $table->unique(['olx_category_id', 'olx_attribute_id']);
            $table->foreign('olx_category_id')->references('id')->on('olx_categories')->cascadeOnDelete();
        });

        Schema::create('olx_listing_registry', function (Blueprint $table) {
            $table->unsignedBigInteger('olx_listing_id')->primary();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('sku_number')->nullable();
            $table->string('title')->nullable();
            $table->unsignedInteger('category_id')->nullable();
            $table->string('state')->nullable();
            $table->string('status')->nullable();
            $table->string('sync_mode')->default('legacy');
            $table->string('match_method')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();

            $table->index('product_id');
            $table->index('sku_number');
            $table->index('sync_mode');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('olx_export_hash', 64)->nullable()->after('olx_last_error');
            $table->string('olx_listing_status', 32)->nullable()->after('olx_export_hash');
            $table->boolean('olx_export_enabled')->nullable()->after('olx_listing_status');
            $table->boolean('olx_managed')->default(false)->after('olx_export_enabled');
            $table->json('olx_image_map')->nullable()->after('olx_managed');

            $table->index('olx_export_hash');
            $table->index('olx_listing_status');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['olx_export_hash']);
            $table->dropIndex(['olx_listing_status']);
            $table->dropColumn([
                'olx_export_hash',
                'olx_listing_status',
                'olx_export_enabled',
                'olx_managed',
                'olx_image_map',
            ]);
        });

        Schema::dropIfExists('olx_listing_registry');
        Schema::dropIfExists('olx_attribute_mappings');
        Schema::dropIfExists('olx_category_mappings');
        Schema::dropIfExists('olx_category_attributes');
        Schema::dropIfExists('olx_categories');
    }
};
