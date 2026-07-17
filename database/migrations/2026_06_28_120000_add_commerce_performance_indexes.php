<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->index('user_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->index('user_id');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->dropIndex(['user_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['customer_id']);
        });
    }
};
