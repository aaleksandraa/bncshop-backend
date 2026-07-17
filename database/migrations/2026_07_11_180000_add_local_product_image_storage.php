<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table): void {
            $table->string('local_path')->nullable()->after('public_url');
            $table->uuid('stored_file_id')->nullable()->after('external_image_id');
            $table->string('storage_key')->nullable()->after('local_path');
            $table->string('original_file_name')->nullable()->after('storage_key');
            $table->string('stored_file_name')->nullable()->after('original_file_name');
            $table->string('file_extension')->nullable()->after('content_type');
            $table->unsignedTinyInteger('file_type')->nullable()->after('file_extension');
            $table->boolean('is_public')->nullable()->after('file_type');
            $table->unsignedInteger('width')->nullable()->after('file_size_bytes');
            $table->unsignedInteger('height')->nullable()->after('width');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->uuid('api_default_image_id')->nullable()->after('default_image_id');
            $table->string('api_default_image_url')->nullable()->after('api_default_image_id');
        });

        Schema::table('product_attribute_values', function (Blueprint $table): void {
            $table->string('external_id')->nullable()->after('attribute_definition_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_attribute_values', function (Blueprint $table): void {
            $table->dropColumn('external_id');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['api_default_image_id', 'api_default_image_url']);
        });

        Schema::table('product_images', function (Blueprint $table): void {
            $table->dropColumn([
                'local_path',
                'stored_file_id',
                'storage_key',
                'original_file_name',
                'stored_file_name',
                'file_extension',
                'file_type',
                'is_public',
                'width',
                'height',
            ]);
        });
    }
};
