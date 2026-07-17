<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_margin_rules', function (Blueprint $table): void {
            $table->string('subcategory_scope', 32)->default('category_only')->after('margin_percentage');
            $table->boolean('include_parent_category')->default(true)->after('subcategory_scope');
        });

        Schema::table('supplier_category_margin_rules', function (Blueprint $table): void {
            $table->string('subcategory_scope', 32)->default('category_only')->after('margin_percentage');
            $table->boolean('include_parent_category')->default(true)->after('subcategory_scope');
        });

        DB::table('category_margin_rules')
            ->where('include_subcategories', true)
            ->update(['subcategory_scope' => 'all_descendants']);

        DB::table('supplier_category_margin_rules')
            ->where('include_subcategories', true)
            ->update(['subcategory_scope' => 'all_descendants']);

        Schema::table('category_margin_rules', function (Blueprint $table): void {
            $table->dropColumn('include_subcategories');
        });

        Schema::table('supplier_category_margin_rules', function (Blueprint $table): void {
            $table->dropColumn('include_subcategories');
        });

        Schema::create('category_margin_rule_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_margin_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['category_margin_rule_id', 'category_id'], 'category_margin_rule_target_unique');
        });

        Schema::create('supplier_category_margin_rule_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_category_margin_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['supplier_category_margin_rule_id', 'category_id'], 'supplier_margin_rule_target_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_category_margin_rule_targets');
        Schema::dropIfExists('category_margin_rule_targets');

        Schema::table('category_margin_rules', function (Blueprint $table): void {
            $table->boolean('include_subcategories')->default(false);
            $table->dropColumn(['subcategory_scope', 'include_parent_category']);
        });

        Schema::table('supplier_category_margin_rules', function (Blueprint $table): void {
            $table->boolean('include_subcategories')->default(true);
            $table->dropColumn(['subcategory_scope', 'include_parent_category']);
        });
    }
};
