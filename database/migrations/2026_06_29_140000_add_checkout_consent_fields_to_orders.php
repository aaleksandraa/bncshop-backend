<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('terms_accepted_at')->nullable()->after('notes');
            $table->boolean('create_account_requested')->default(false)->after('terms_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['terms_accepted_at', 'create_account_requested']);
        });
    }
};
