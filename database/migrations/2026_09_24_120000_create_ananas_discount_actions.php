<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ananas_discount_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ananas_product_mapping_id')->nullable()
                ->constrained('ananas_product_mappings')->nullOnDelete();
            $table->unsignedBigInteger('merchant_inventory_id');
            $table->uuid('ananas_discount_id')->nullable();
            $table->string('discount_type');
            $table->decimal('discount_price', 12, 2);
            $table->string('currency', 8)->default('RSD');
            $table->date('date_from');
            $table->date('date_to')->nullable();
            $table->string('local_status')->default('SCHEDULED');
            $table->text('last_error')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->index(['merchant_inventory_id', 'local_status']);
            $table->index('ananas_discount_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ananas_discount_actions');
    }
};
