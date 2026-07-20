<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_import_job_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_import_job_id')->constrained('api_import_jobs')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('external_product_id');
            $table->string('product_name')->nullable();
            $table->string('action', 32);
            $table->json('changed_fields')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['api_import_job_id', 'action']);
            $table->index('external_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_import_job_changes');
    }
};
