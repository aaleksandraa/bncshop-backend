<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->text('cancellation_reason')->nullable()->after('notes');
        });

        $cancelledIds = DB::table('orders')
            ->where('status', 'otkazano')
            ->pluck('id');

        foreach ($cancelledIds as $orderId) {
            $note = DB::table('order_status_history')
                ->where('order_id', $orderId)
                ->where('new_status', 'otkazano')
                ->whereNotNull('note')
                ->orderByDesc('id')
                ->value('note');

            $reason = is_string($note) ? trim($note) : '';

            if ($reason === '') {
                continue;
            }

            DB::table('orders')
                ->where('id', $orderId)
                ->update(['cancellation_reason' => $reason]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('cancellation_reason');
        });
    }
};
