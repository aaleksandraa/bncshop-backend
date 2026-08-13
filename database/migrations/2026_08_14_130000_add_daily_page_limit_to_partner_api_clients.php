<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_api_clients', function (Blueprint $table): void {
            $table->unsignedInteger('daily_page_limit')->default(2000)->after('rate_limit_per_minute');
        });
    }

    public function down(): void
    {
        Schema::table('partner_api_clients', function (Blueprint $table): void {
            $table->dropColumn('daily_page_limit');
        });
    }
};
