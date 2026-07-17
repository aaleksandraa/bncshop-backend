<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');
            $table->unsignedInteger('points_required');
            $table->decimal('reward_value', 12, 2)->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('apply_to')->default('cart');
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('max_uses_per_customer')->nullable();
            $table->unsignedInteger('total_max_uses')->nullable();
            $table->unsignedInteger('times_redeemed')->default(0);
            $table->timestamps();
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->integer('points');
            $table->integer('balance_after');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loyalty_reward_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['order_id', 'type'], 'loyalty_transactions_order_type_unique');
        });

        Schema::create('loyalty_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_reward_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('points_spent');
            $table->string('status')->default('available');
            $table->string('generated_code')->nullable()->unique();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('loyalty_pending_earnings', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('points');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['email', 'status']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('loyalty_points_balance')->default(0)->after('jib');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('points_earned')->default(0)->after('items_count');
            $table->unsignedInteger('points_redeemed')->default(0)->after('points_earned');
            $table->decimal('loyalty_discount_amount', 12, 2)->default(0)->after('points_redeemed');
            $table->foreignId('loyalty_reward_id')->nullable()->after('loyalty_discount_amount')->constrained()->nullOnDelete();
            $table->foreignId('loyalty_redemption_id')->nullable()->after('loyalty_reward_id')->constrained()->nullOnDelete();
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->foreignId('loyalty_reward_id')->nullable()->after('coupon_code')->constrained()->nullOnDelete();
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->boolean('is_loyalty_reward')->default(false)->after('price_confirmed');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('is_loyalty_reward');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loyalty_reward_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loyalty_redemption_id');
            $table->dropConstrainedForeignId('loyalty_reward_id');
            $table->dropColumn(['points_earned', 'points_redeemed', 'loyalty_discount_amount']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('loyalty_points_balance');
        });

        Schema::dropIfExists('loyalty_pending_earnings');
        Schema::dropIfExists('loyalty_redemptions');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_rewards');
    }
};
