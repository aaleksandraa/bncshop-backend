<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('b2b_customers')
            ->select('jib', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('jib')
            ->having('aggregate', '>', 1)
            ->pluck('jib');

        foreach ($duplicates as $jib) {
            $ids = DB::table('b2b_customers')
                ->where('jib', $jib)
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids->slice(1) as $index => $id) {
                DB::table('b2b_customers')
                    ->where('id', $id)
                    ->update(['jib' => $jib.'-dup-'.($index + 1)]);
            }
        }

        Schema::table('b2b_customers', function (Blueprint $table) {
            $table->unique('jib');
        });
    }

    public function down(): void
    {
        Schema::table('b2b_customers', function (Blueprint $table) {
            $table->dropUnique(['jib']);
        });
    }
};
