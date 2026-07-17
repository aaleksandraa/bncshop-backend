<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_definitions', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->string('display_unit', 32)->nullable()->after('display_name');
            $table->unsignedInteger('detail_sort_order')->default(0)->after('is_filter');
            $table->boolean('is_mapped')->default(false)->after('detail_sort_order');
            $table->json('value_mappings')->nullable()->after('parsed_options');
        });
    }

    public function down(): void
    {
        Schema::table('attribute_definitions', function (Blueprint $table) {
            $table->dropColumn([
                'display_name',
                'display_unit',
                'detail_sort_order',
                'is_mapped',
                'value_mappings',
            ]);
        });
    }
};
