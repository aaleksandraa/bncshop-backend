<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('email');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name')->nullable();
            $table->string('product_slug')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('base_price', 12, 2);
            $table->string('installment_type');
            $table->unsignedSmallInteger('months');
            $table->decimal('monthly_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('interest_rate', 8, 4)->default(0);
            $table->decimal('provision_rate', 8, 4)->default(0);
            $table->json('calculation_snapshot')->nullable();
            $table->string('status')->default('nova');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('installment_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_inquiries');
    }
};
