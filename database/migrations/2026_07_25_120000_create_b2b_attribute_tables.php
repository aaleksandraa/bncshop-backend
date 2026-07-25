<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_attribute_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('input_type', 32)->default('select');
            $table->boolean('is_filterable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('b2b_attribute_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_attribute_definition_id')
                ->constrained('b2b_attribute_definitions')
                ->cascadeOnDelete();
            $table->string('value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['b2b_attribute_definition_id', 'value'], 'b2b_attr_options_definition_value_unique');
        });

        Schema::create('b2b_category_attribute', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_category_id')
                ->constrained('b2b_categories')
                ->cascadeOnDelete();
            $table->foreignId('b2b_attribute_definition_id')
                ->constrained('b2b_attribute_definitions')
                ->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->unique(
                ['b2b_category_id', 'b2b_attribute_definition_id'],
                'b2b_category_attribute_unique',
            );
        });

        Schema::create('b2b_product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_product_id')
                ->constrained('b2b_products')
                ->cascadeOnDelete();
            $table->foreignId('b2b_attribute_definition_id')
                ->constrained('b2b_attribute_definitions')
                ->cascadeOnDelete();
            $table->string('value');
            $table->timestamps();

            $table->unique(
                ['b2b_product_id', 'b2b_attribute_definition_id', 'value'],
                'b2b_product_attr_values_unique',
            );
            $table->index(
                ['b2b_attribute_definition_id', 'value'],
                'b2b_product_attr_values_filter_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_product_attribute_values');
        Schema::dropIfExists('b2b_category_attribute');
        Schema::dropIfExists('b2b_attribute_options');
        Schema::dropIfExists('b2b_attribute_definitions');
    }
};
