<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_product_id')->constrained('b2b_products')->cascadeOnDelete();
            $table->integer('delta');
            $table->unsignedInteger('quantity_before');
            $table->unsignedInteger('quantity_after');
            $table->string('type');
            $table->string('summary');
            $table->foreignId('b2b_order_id')->nullable()->constrained('b2b_orders')->nullOnDelete();
            $table->foreignId('b2b_customer_id')->nullable()->constrained('b2b_customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['b2b_product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_stock_movements');
    }
};
