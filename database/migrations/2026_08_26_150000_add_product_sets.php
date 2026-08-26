<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_set')->default(false)->after('is_refurbished');
            $table->index('is_set');
        });

        Schema::create('product_set_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('set_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['set_product_id', 'component_product_id']);
            $table->index('component_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_set_items');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['is_set']);
            $table->dropColumn('is_set');
        });
    }
};
