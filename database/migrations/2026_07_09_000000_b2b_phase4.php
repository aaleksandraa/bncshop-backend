<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_password_reset_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('token');
        });

        Schema::table('b2b_orders', function (Blueprint $table): void {
            $table->decimal('shipping_fee', 12, 2)->default(0)->after('discount_total');
            $table->string('invoice_path')->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('b2b_orders', function (Blueprint $table): void {
            $table->dropColumn(['shipping_fee', 'invoice_path']);
        });

        Schema::dropIfExists('b2b_password_reset_tokens');
    }
};
