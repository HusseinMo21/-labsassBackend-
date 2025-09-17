<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lab_sequences', function (Blueprint $table) {
            $table->id();
            $table->integer('year')->unique();
            $table->integer('last_sequence')->default(0);
            $table->timestamps();
        });
        
        // Initialize with the highest existing lab numbers from each year
        $this->initializeSequences();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_sequences');
    }
    
    /**
     * Initialize lab sequences based on existing data
     */
    private function initializeSequences(): void
    {
        // Get all lab numbers and parse them properly
        $labNumbers = DB::table('patient')
            ->whereNotNull('lab')
            ->where('lab', '!=', '')
            ->pluck('lab');
        
        $yearlyMax = [];
        
        foreach ($labNumbers as $labNo) {
            // Parse lab number format: YYYY-NNNN or YYYY-NNNN-SUFFIX
            if (preg_match('/^(\d{4})-(\d+)(.*)$/', $labNo, $matches)) {
                $year = (int) $matches[1];
                $sequence = (int) $matches[2];
                
                if (!isset($yearlyMax[$year]) || $sequence > $yearlyMax[$year]) {
                    $yearlyMax[$year] = $sequence;
                }
            }
        }
        
        // Insert sequences for each year
        foreach ($yearlyMax as $year => $maxSequence) {
            DB::table('lab_sequences')->insert([
                'year' => $year,
                'last_sequence' => $maxSequence,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        // Ensure current year exists
        $currentYear = now()->year;
        if (!isset($yearlyMax[$currentYear])) {
            DB::table('lab_sequences')->insert([
                'year' => $currentYear,
                'last_sequence' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};