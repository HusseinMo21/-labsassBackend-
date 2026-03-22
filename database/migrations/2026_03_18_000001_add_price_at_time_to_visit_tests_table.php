<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_tests', function (Blueprint $table) {
            if (!Schema::hasColumn('visit_tests', 'price_at_time')) {
                $table->decimal('price_at_time', 10, 2)->nullable()->after('price');
            }
        });

        // Snapshot: historical line value = what was charged at registration (legacy rows)
        if (Schema::hasColumn('visit_tests', 'price_at_time')) {
            DB::table('visit_tests')
                ->whereNull('price_at_time')
                ->update([
                    'price_at_time' => DB::raw('COALESCE(final_price, custom_price, price)'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('visit_tests', function (Blueprint $table) {
            if (Schema::hasColumn('visit_tests', 'price_at_time')) {
                $table->dropColumn('price_at_time');
            }
        });
    }
};
