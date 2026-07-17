<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->string('code', 64)->nullable()->unique()->after('display_name');
            $table->boolean('is_active')->default(true)->after('code');
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'code', 'is_active', 'sort_order']);
        });
    }
};
