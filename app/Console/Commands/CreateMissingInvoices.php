<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Visit;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class CreateMissingInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:create-missing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create invoices for visits that don\'t have invoices';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to create missing invoices...');

        // Find visits without invoices
        $visitsWithoutInvoices = Visit::whereDoesntHave('invoice')->get();
        
        if ($visitsWithoutInvoices->isEmpty()) {
            $this->info('All visits already have invoices!');
            return;
        }

        $this->info("Found {$visitsWithoutInvoices->count()} visits without invoices.");

        $bar = $this->output->createProgressBar($visitsWithoutInvoices->count());
        $bar->start();

        $createdCount = 0;
        $errorCount = 0;

        foreach ($visitsWithoutInvoices as $visit) {
            try {
                DB::beginTransaction();

                // Create invoice for this visit
                $invoice = Invoice::create([
                    'visit_id' => $visit->id,
                    'invoice_number' => 'INV' . now()->format('Ymd') . str_pad($visit->id, 4, '0', STR_PAD_LEFT),
                    'invoice_date' => $visit->visit_date,
                    'subtotal' => $visit->total_amount,
                    'discount_amount' => $visit->discount_amount,
                    'tax_amount' => 0, // No tax for now
                    'total_amount' => $visit->final_amount,
                    'amount_paid' => $visit->upfront_payment,
                    'balance' => $visit->remaining_balance,
                    'status' => $visit->billing_status,
                    'payment_method' => $visit->payment_method,
                    'notes' => $visit->remarks,
                    'created_by' => 1, // Default to admin user
                ]);

                DB::commit();
                $createdCount++;
                $this->line("\nCreated invoice {$invoice->invoice_number} for visit {$visit->visit_number}");

            } catch (\Exception $e) {
                DB::rollBack();
                $errorCount++;
                $this->error("\nError creating invoice for visit {$visit->visit_number}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Completed! Created {$createdCount} invoices, {$errorCount} errors.");
        
        if ($errorCount > 0) {
            $this->warn("Some invoices could not be created. Check the logs for details.");
        }
    }
}
