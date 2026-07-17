<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_definitions', function (Blueprint $table): void {
            $table->foreignId('canonical_attribute_definition_id')
                ->nullable()
                ->after('value_mappings')
                ->constrained('attribute_definitions')
                ->nullOnDelete();

            $table->index('canonical_attribute_definition_id');
        });
    }

    public function down(): void
    {
        Schema::table('attribute_definitions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('canonical_attribute_definition_id');
        });
    }
};
