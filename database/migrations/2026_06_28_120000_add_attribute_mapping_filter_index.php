<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_category_mappings', function (Blueprint $table): void {
            $table->index(
                ['category_id', 'is_filter_enabled', 'sort_order'],
                'idx_acm_category_filter_sort',
            );
        });
    }

    public function down(): void
    {
        Schema::table('attribute_category_mappings', function (Blueprint $table): void {
            $table->dropIndex('idx_acm_category_filter_sort');
        });
    }
};
