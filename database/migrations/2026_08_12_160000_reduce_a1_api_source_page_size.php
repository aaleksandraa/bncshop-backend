<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('api_sources')
            ->where('target_system_code', config('bnc.a1_api_target_system_code', 'bnc-shop'))
            ->where('page_size', '>', 50)
            ->update(['page_size' => 50]);
    }

    public function down(): void
    {
        // Intentionally left blank — previous page_size values are unknown.
    }
};
