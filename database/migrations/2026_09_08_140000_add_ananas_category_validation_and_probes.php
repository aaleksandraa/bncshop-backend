<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ananas_category_mappings', function (Blueprint $table) {
            $table->string('category_validation_status')->default('unknown')->after('ananas_category');
            $table->json('observed_categories')->nullable()->after('category_validation_status');
            $table->timestamp('category_validated_at')->nullable()->after('observed_categories');
            $table->text('category_validation_notes')->nullable()->after('category_validated_at');
            $table->foreignId('last_probe_product_id')->nullable()->after('category_validation_notes')
                ->constrained('products')->nullOnDelete();
            $table->uuid('last_probe_progress_id')->nullable()->after('last_probe_product_id');

            $table->index('category_validation_status');
        });

        Schema::create('ananas_category_probes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_mapping_id')->constrained('ananas_category_mappings')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('product_type');
            $table->string('category_candidate');
            $table->uuid('progress_id')->nullable();
            $table->string('status');
            $table->json('observed_categories')->nullable();
            $table->string('observed_product_type')->nullable();
            $table->string('remote_product_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('progress_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ananas_category_probes');

        Schema::table('ananas_category_mappings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_probe_product_id');
            $table->dropColumn([
                'category_validation_status',
                'observed_categories',
                'category_validated_at',
                'category_validation_notes',
                'last_probe_progress_id',
            ]);
        });
    }
};
