<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_category', function (Blueprint $table) {
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['discount_id', 'category_id']);
        });

        Schema::create('discount_manufacturer', function (Blueprint $table) {
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manufacturer_id')->constrained()->cascadeOnDelete();
            $table->primary(['discount_id', 'manufacturer_id']);
        });

        DB::table('discounts')
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->each(function (object $discount): void {
                DB::table('discount_category')->insertOrIgnore([
                    'discount_id' => $discount->id,
                    'category_id' => $discount->category_id,
                ]);
            });

        DB::table('discounts')
            ->whereNotNull('manufacturer_id')
            ->orderBy('id')
            ->each(function (object $discount): void {
                DB::table('discount_manufacturer')->insertOrIgnore([
                    'discount_id' => $discount->id,
                    'manufacturer_id' => $discount->manufacturer_id,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_manufacturer');
        Schema::dropIfExists('discount_category');
    }
};
