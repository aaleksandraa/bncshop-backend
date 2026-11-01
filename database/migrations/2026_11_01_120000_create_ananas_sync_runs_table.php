<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ananas_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('job_key', 64);
            $table->string('status', 32);
            $table->string('triggered_by', 32)->default('cron');
            $table->unsignedBigInteger('triggered_by_user_id')->nullable();
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_item_count')->default(0);
            $table->json('failed_inventory_ids')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('last_progress_at')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['job_key', 'status']);
            $table->index(['job_key', 'created_at']);
        });

        Schema::table('ananas_product_mappings', function (Blueprint $table): void {
            $table->timestamp('remote_missing_at')->nullable()->after('remote_modified_at');
            $table->string('takedown_state_hash', 64)->nullable()->after('remote_missing_at');
        });
    }

    public function down(): void
    {
        Schema::table('ananas_product_mappings', function (Blueprint $table): void {
            $table->dropColumn(['remote_missing_at', 'takedown_state_hash']);
        });

        Schema::dropIfExists('ananas_sync_runs');
    }
};
