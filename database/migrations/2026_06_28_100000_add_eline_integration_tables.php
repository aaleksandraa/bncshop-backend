<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eline_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('product_count')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('eline_category_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eline_category_id')->constrained('eline_categories')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->string('product_condition')->default('refurbished');
            $table->decimal('margin_percentage', 8, 2)->nullable();
            $table->timestamps();

            $table->unique('eline_category_id');
        });

        Schema::create('eline_product_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('eline_sifra')->unique();
            $table->boolean('is_enabled')->default(true);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('product_condition')->nullable();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('import_source')->default('a1')->after('external_product_id');
            $table->string('eline_sifra')->nullable()->unique()->after('sku');
            $table->boolean('is_refurbished')->default(false)->after('is_new');
            $table->foreignId('api_source_id')->nullable()->after('category_id')->constrained('api_sources')->nullOnDelete();

            $table->index(['import_source', 'is_public', 'status']);
            $table->index('is_refurbished');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['api_source_id']);
            $table->dropIndex(['import_source', 'is_public', 'status']);
            $table->dropIndex(['is_refurbished']);
            $table->dropColumn(['import_source', 'eline_sifra', 'is_refurbished', 'api_source_id']);
        });

        Schema::dropIfExists('eline_product_overrides');
        Schema::dropIfExists('eline_category_mappings');
        Schema::dropIfExists('eline_categories');
    }
};
