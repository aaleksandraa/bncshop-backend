<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'updated_at')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->index('updated_at', 'products_updated_at_idx');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_updated_at_idx');
        });
    }
};
