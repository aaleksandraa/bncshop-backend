<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table): void {
            $table->string('storage_disk', 32)->nullable()->after('local_path');
            $table->timestamp('optimized_at')->nullable()->after('storage_disk');
        });

        Schema::table('manufacturers', function (Blueprint $table): void {
            $table->string('storage_disk', 32)->nullable()->after('logo_path');
            $table->timestamp('optimized_at')->nullable()->after('storage_disk');
        });

        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->string('storage_disk', 32)->nullable()->after('featured_image_path');
            $table->timestamp('optimized_at')->nullable()->after('storage_disk');
        });

        Schema::table('b2b_product_images', function (Blueprint $table): void {
            $table->string('storage_disk', 32)->nullable()->after('path');
            $table->timestamp('optimized_at')->nullable()->after('storage_disk');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table): void {
            $table->dropColumn(['storage_disk', 'optimized_at']);
        });

        Schema::table('manufacturers', function (Blueprint $table): void {
            $table->dropColumn(['storage_disk', 'optimized_at']);
        });

        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropColumn(['storage_disk', 'optimized_at']);
        });

        Schema::table('b2b_product_images', function (Blueprint $table): void {
            $table->dropColumn(['storage_disk', 'optimized_at']);
        });
    }
};
