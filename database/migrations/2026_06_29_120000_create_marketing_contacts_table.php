<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('type', 20);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('orders_total', 12, 2)->default(0);
            $table->timestamp('last_order_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->string('brevo_contact_id')->nullable();
            $table->timestamp('brevo_synced_at')->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamps();

            $table->index('type');
            $table->index('brevo_synced_at');
            $table->index('last_order_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_contacts');
    }
};
