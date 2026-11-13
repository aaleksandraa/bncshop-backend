<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ananas_orders', function (Blueprint $table) {
            $table->id();
            $table->string('ananas_order_id')->unique();
            $table->string('remote_environment', 32)->index();
            $table->string('suborder_id')->nullable()->index();
            $table->timestamp('ananas_created_at')->nullable()->index();
            $table->decimal('total_price', 12, 2)->default(0);
            $table->string('currency', 8)->default('BAM');
            $table->json('payment_methods')->nullable();
            $table->json('billing_address')->nullable();
            $table->string('remote_status')->nullable()->index();
            $table->string('status_bucket', 40)->default('awaiting_confirm')->index();
            $table->boolean('is_terminal')->default(false)->index();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('ananas_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ananas_order_id')->constrained('ananas_orders')->cascadeOnDelete();
            $table->string('ananas_line_id')->index();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ananas_product_mapping_id')->nullable()->constrained('ananas_product_mappings')->nullOnDelete();
            $table->unsignedBigInteger('merchant_inventory_id')->nullable()->index();
            $table->string('product_ean')->nullable()->index();
            $table->string('product_sku')->nullable();
            $table->string('product_name')->nullable();
            $table->unsignedInteger('quantity_ordered')->default(0);
            $table->unsignedInteger('quantity_confirmed')->nullable();
            $table->unsignedInteger('quantity_packed')->nullable();
            $table->unsignedInteger('committed_quantity')->default(0);
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('line_total', 12, 2)->nullable();
            $table->decimal('vat', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['ananas_order_id', 'ananas_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ananas_order_items');
        Schema::dropIfExists('ananas_orders');
    }
};
