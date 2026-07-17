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

        DB::statement('CREATE INDEX idx_products_plp_category_newest ON products (category_id, created_at DESC) WHERE is_public = true AND status = \'active\'');
        DB::statement('CREATE INDEX idx_products_plp_category_price ON products (category_id, display_price) WHERE is_public = true AND status = \'active\'');
        DB::statement('CREATE INDEX idx_products_plp_manufacturer ON products (manufacturer_id, created_at DESC) WHERE is_public = true AND status = \'active\'');
        DB::statement('CREATE INDEX idx_products_on_sale ON products (on_sale, created_at DESC) WHERE is_public = true AND status = \'active\' AND on_sale = true');
        DB::statement('CREATE INDEX idx_pav_attr_value ON product_attribute_values (attribute_definition_id, normalized_value, product_id)');
        DB::statement('CREATE INDEX idx_product_images_active ON product_images (product_id, sort_order) WHERE status = \'active\'');

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX idx_products_name_trgm ON products USING gin (name gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_products_name_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_product_images_active');
        DB::statement('DROP INDEX IF EXISTS idx_pav_attr_value');
        DB::statement('DROP INDEX IF EXISTS idx_products_on_sale');
        DB::statement('DROP INDEX IF EXISTS idx_products_plp_manufacturer');
        DB::statement('DROP INDEX IF EXISTS idx_products_plp_category_price');
        DB::statement('DROP INDEX IF EXISTS idx_products_plp_category_newest');
    }
};
