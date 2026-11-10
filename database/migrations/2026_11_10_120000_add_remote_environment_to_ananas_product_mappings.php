<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ananas_product_mappings', function (Blueprint $table): void {
            $table->string('remote_environment', 16)->nullable()->after('local_status');
            $table->index('remote_environment');
        });

        DB::table('ananas_product_mappings')
            ->where(function ($query): void {
                $query->whereNotNull('ananas_product_id')
                    ->orWhereIn('local_status', ['LINKED', 'SUBMITTED', 'PENDING_ONBOARDING', 'FAILED']);
            })
            ->update(['remote_environment' => 'stage']);
    }

    public function down(): void
    {
        Schema::table('ananas_product_mappings', function (Blueprint $table): void {
            $table->dropIndex(['remote_environment']);
            $table->dropColumn('remote_environment');
        });
    }
};
