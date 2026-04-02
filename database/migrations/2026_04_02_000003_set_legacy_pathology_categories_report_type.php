<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Legacy ERP pathology categories: use visit-level narrative form, not per-test parameter table. */
    private const CODES = ['path', 'cytho', 'ihc', 'rev', 'other', 'path_ihc'];

    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('test_categories')) {
            return;
        }
        if (! \Illuminate\Support\Facades\Schema::hasColumn('test_categories', 'report_type')) {
            return;
        }

        DB::table('test_categories')
            ->whereIn('code', self::CODES)
            ->whereNull('lab_id')
            ->update(['report_type' => 'pathology']);
    }

    public function down(): void
    {
        // Intentionally no-op: do not revert to paragraph (wrong UX for these categories).
    }
};
