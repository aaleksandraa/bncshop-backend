<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_campaigns', function (Blueprint $table): void {
            $table->boolean('show_breadcrumbs')->default(true)->after('hero_image_path');
            $table->boolean('show_title')->default(true)->after('show_breadcrumbs');
            $table->boolean('show_product_count')->default(true)->after('show_title');
        });
    }

    public function down(): void
    {
        Schema::table('shop_campaigns', function (Blueprint $table): void {
            $table->dropColumn(['show_breadcrumbs', 'show_title', 'show_product_count']);
        });
    }
};
