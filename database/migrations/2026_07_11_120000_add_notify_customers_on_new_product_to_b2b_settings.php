<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2b_settings', function (Blueprint $table): void {
            $table->boolean('notify_customers_on_new_product')->default(false)->after('admin_notification_email');
        });
    }

    public function down(): void
    {
        Schema::table('b2b_settings', function (Blueprint $table): void {
            $table->dropColumn('notify_customers_on_new_product');
        });
    }
};
