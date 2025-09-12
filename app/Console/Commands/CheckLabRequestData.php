<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LabRequest;
use App\Models\LabSequence;

class CheckLabRequestData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:lab-request-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check lab request data structure';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking lab request data...');
        
        // Check lab sequences
        $this->info('Lab Sequences:');
        $sequences = LabSequence::all();
        if ($sequences->isEmpty()) {
            $this->info('No lab sequences found');
        } else {
            foreach ($sequences as $seq) {
                $this->info("Year: {$seq->year}, Last Sequence: {$seq->last_sequence}");
            }
        }
        
        $this->info('');
        
        // Check lab requests
        $this->info('Lab Requests:');
        $labRequests = LabRequest::with('patient')->get();
        
        foreach ($labRequests as $lr) {
            $this->info("ID: {$lr->id}");
            $this->info("Lab No: {$lr->lab_no}");
            $this->info("Suffix: " . ($lr->suffix ?: 'none'));
            $this->info("Full Lab No: {$lr->full_lab_no}");
            $this->info("Patient: " . ($lr->patient ? $lr->patient->name : 'none'));
            $this->info("Status: {$lr->status}");
            $this->info("Created: {$lr->created_at}");
            $this->info("---");
        }
        
        return 0;
    }
}
