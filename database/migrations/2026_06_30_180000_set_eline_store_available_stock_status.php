<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where('import_source', 'eline')
            ->where('stock_status', 'in_stock')
            ->where('available_stock', '>', 0)
            ->update(['stock_status' => 'store_available']);
    }

    public function down(): void
    {
        DB::table('products')
            ->where('import_source', 'eline')
            ->where('stock_status', 'store_available')
            ->update(['stock_status' => 'in_stock']);
    }
};
