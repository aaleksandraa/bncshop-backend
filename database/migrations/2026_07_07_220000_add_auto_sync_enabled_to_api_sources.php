<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_sources', function (Blueprint $table): void {
            $table->boolean('auto_sync_enabled')->default(true)->after('sync_interval_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('api_sources', function (Blueprint $table): void {
            $table->dropColumn('auto_sync_enabled');
        });
    }
};
