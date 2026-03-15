<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 1. Create default lab
     * 2. Backfill lab_id = 1 for all existing records
     * 3. Make lab_id NOT NULL (except users)
     * 4. Update unique constraints to include lab_id
     */
    public function up(): void
    {
        $defaultLabId = 1;

        // 1. Create default lab if not exists
        if (Schema::hasTable('labs') && DB::table('labs')->count() === 0) {
            DB::table('labs')->insert([
                'name' => 'Default Lab',
                'slug' => 'default',
                'subdomain' => 'default',
                'settings' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Backfill lab_id for existing records
        if (Schema::hasTable('patient') && Schema::hasColumn('patient', 'lab_id')) {
            DB::table('patient')->whereNull('lab_id')->update(['lab_id' => $defaultLabId]);
        }
        if (Schema::hasTable('lab_requests') && Schema::hasColumn('lab_requests', 'lab_id')) {
            DB::table('lab_requests')->whereNull('lab_id')->update(['lab_id' => $defaultLabId]);
        }
        if (Schema::hasTable('visits') && Schema::hasColumn('visits', 'lab_id')) {
            DB::table('visits')->whereNull('lab_id')->update(['lab_id' => $defaultLabId]);
        }
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'lab_id')) {
            DB::table('invoices')->whereNull('lab_id')->update(['lab_id' => $defaultLabId]);
        }
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'lab_id')) {
            DB::table('users')->whereNull('lab_id')->update(['lab_id' => $defaultLabId]);
        }

        // 3. Make lab_id NOT NULL (except users - platform admins keep NULL)
        if (Schema::hasTable('patient')) {
            DB::statement('ALTER TABLE patient MODIFY lab_id BIGINT UNSIGNED NOT NULL');
        }
        if (Schema::hasTable('lab_requests')) {
            DB::statement('ALTER TABLE lab_requests MODIFY lab_id BIGINT UNSIGNED NOT NULL');
        }
        if (Schema::hasTable('visits')) {
            DB::statement('ALTER TABLE visits MODIFY lab_id BIGINT UNSIGNED NOT NULL');
        }
        if (Schema::hasTable('invoices')) {
            DB::statement('ALTER TABLE invoices MODIFY lab_id BIGINT UNSIGNED NOT NULL');
        }

        // 4. Update unique constraints - drop old, add new with lab_id
        $this->updateLabRequestsUnique();
        $this->updateVisitsUnique();
        $this->updateInvoicesUnique();
        $this->updateUsersUnique();
    }

    private function updateLabRequestsUnique(): void
    {
        if (!Schema::hasTable('lab_requests')) {
            return;
        }

        $indexExists = DB::select("SHOW INDEX FROM lab_requests WHERE Key_name = 'lab_requests_lab_no_suffix_unique'");
        if (!empty($indexExists)) {
            Schema::table('lab_requests', function (Blueprint $table) {
                $table->dropUnique('lab_requests_lab_no_suffix_unique');
            });
        }

        Schema::table('lab_requests', function (Blueprint $table) {
            $table->unique(['lab_id', 'lab_no', 'suffix'], 'lab_requests_lab_id_lab_no_suffix_unique');
        });
    }

    private function updateVisitsUnique(): void
    {
        if (!Schema::hasTable('visits') || !Schema::hasColumn('visits', 'visit_number')) {
            return;
        }

        try {
            $indexes = DB::select("SHOW INDEX FROM visits WHERE Column_name = 'visit_number' AND Non_unique = 0");
            if (!empty($indexes)) {
                $keyName = $indexes[0]->Key_name ?? null;
                if ($keyName) {
                    Schema::table('visits', function (Blueprint $table) use ($keyName) {
                        $table->dropUnique($keyName);
                    });
                }
            }
        } catch (\Exception $e) {
            // Index might not exist
        }

        Schema::table('visits', function (Blueprint $table) {
            $table->unique(['lab_id', 'visit_number'], 'visits_lab_id_visit_number_unique');
        });
    }

    private function updateInvoicesUnique(): void
    {
        if (!Schema::hasTable('invoices') || !Schema::hasColumn('invoices', 'invoice_number')) {
            return;
        }

        try {
            $indexes = DB::select("SHOW INDEX FROM invoices WHERE Column_name = 'invoice_number' AND Non_unique = 0");
            if (!empty($indexes)) {
                $keyName = $indexes[0]->Key_name ?? null;
                if ($keyName) {
                    Schema::table('invoices', function (Blueprint $table) use ($keyName) {
                        $table->dropUnique($keyName);
                    });
                }
            }
        } catch (\Exception $e) {
            // Index might not exist
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(['lab_id', 'invoice_number'], 'invoices_lab_id_invoice_number_unique');
        });
    }

    private function updateUsersUnique(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        try {
            $indexes = DB::select("SHOW INDEX FROM users WHERE Column_name = 'email' AND Non_unique = 0");
            if (!empty($indexes)) {
                $keyName = $indexes[0]->Key_name ?? null;
                if ($keyName) {
                    Schema::table('users', function (Blueprint $table) use ($keyName) {
                        $table->dropUnique($keyName);
                    });
                }
            }
        } catch (\Exception $e) {
            // Index might not exist
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['lab_id', 'email'], 'users_lab_id_email_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert unique constraints
        if (Schema::hasTable('lab_requests')) {
            Schema::table('lab_requests', function (Blueprint $table) {
                $table->dropUnique('lab_requests_lab_id_lab_no_suffix_unique');
                $table->unique(['lab_no', 'suffix'], 'lab_requests_lab_no_suffix_unique');
            });
        }

        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->dropUnique('visits_lab_id_visit_number_unique');
                $table->unique('visit_number');
            });
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'invoice_number')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropUnique('invoices_lab_id_invoice_number_unique');
                $table->unique('invoice_number');
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_lab_id_email_unique');
                $table->unique('email');
            });
        }

        // Revert NOT NULL
        if (Schema::hasTable('patient')) {
            DB::statement('ALTER TABLE patient MODIFY lab_id BIGINT UNSIGNED NULL');
        }
        if (Schema::hasTable('lab_requests')) {
            DB::statement('ALTER TABLE lab_requests MODIFY lab_id BIGINT UNSIGNED NULL');
        }
        if (Schema::hasTable('visits')) {
            DB::statement('ALTER TABLE visits MODIFY lab_id BIGINT UNSIGNED NULL');
        }
        if (Schema::hasTable('invoices')) {
            DB::statement('ALTER TABLE invoices MODIFY lab_id BIGINT UNSIGNED NULL');
        }
    }
};
