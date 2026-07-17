<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement(
            'CREATE INDEX IF NOT EXISTS b2b_products_name_trgm_idx ON b2b_products USING gin (name gin_trgm_ops)'
        );

        DB::statement(
            'CREATE INDEX IF NOT EXISTS b2b_products_sku_trgm_idx ON b2b_products USING gin (sku gin_trgm_ops)'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS b2b_products_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS b2b_products_sku_trgm_idx');
    }
};
