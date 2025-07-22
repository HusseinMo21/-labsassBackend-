<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('username')->unique()->nullable()->after('allergies');
            $table->string('password')->nullable()->after('username');
            $table->string('email')->nullable()->after('phone');
            $table->string('national_id')->nullable()->after('email');
            $table->string('insurance_provider')->nullable()->after('national_id');
            $table->string('insurance_number')->nullable()->after('insurance_provider');
            $table->boolean('has_insurance')->default(false)->after('insurance_number');
            $table->decimal('insurance_coverage', 5, 2)->default(0)->after('has_insurance'); // percentage
            $table->text('billing_address')->nullable()->after('insurance_coverage');
            $table->string('emergency_relationship')->nullable()->after('emergency_phone');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn([
                'username', 'password', 'email', 'national_id', 
                'insurance_provider', 'insurance_number', 'has_insurance',
                'insurance_coverage', 'billing_address', 'emergency_relationship'
            ]);
        });
    }
}; 