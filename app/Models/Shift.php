<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = [
        'staff_id',
        'shift_type',
        'opened_at',
        'closed_at',
        'total_collected',
        'cash_collected',
        'other_payments_collected',
        'payment_breakdown',
        'patients_served',
        'visits_processed',
        'payments_processed',
        'notes',
        'status',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'total_collected' => 'decimal:2',
        'cash_collected' => 'decimal:2',
        'other_payments_collected' => 'decimal:2',
        'payment_breakdown' => 'array',
    ];

    // Relationships
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeByStaff($query, $staffId)
    {
        return $query->where('staff_id', $staffId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('opened_at', today());
    }

    // Methods
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function closeShift(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    public function getDurationAttribute(): string
    {
        // If shift is still open, calculate duration from opened_at to now
        if (!$this->closed_at || $this->status === 'open') {
            if (!$this->opened_at) {
                return 'Unknown';
            }
            
            $endTime = now();
            $duration = $this->opened_at->diffInMinutes($endTime);
            
            // If duration is 0 or negative, return at least 1 minute
            if ($duration < 1) {
                return '1m';
            }
            
            $hours = floor($duration / 60);
            $minutes = round($duration % 60);
            
            if ($hours > 0) {
                return "{$hours}h {$minutes}m";
            } else {
                return "{$minutes}m";
            }
        }

        // Handle case where closed_at might be before opened_at (data inconsistency)
        if ($this->closed_at < $this->opened_at) {
            \Log::warning('Shift has closed_at before opened_at', [
                'shift_id' => $this->id,
                'opened_at' => $this->opened_at,
                'closed_at' => $this->closed_at
            ]);
            // Don't update the database, just calculate from opened_at to now
            $endTime = now();
            $duration = $this->opened_at->diffInMinutes($endTime);
            if ($duration < 1) {
                return '1m';
            }
            $hours = floor($duration / 60);
            $minutes = round($duration % 60);
            if ($hours > 0) {
                return "{$hours}h {$minutes}m";
            } else {
                return "{$minutes}m";
            }
        }

        // Calculate duration for closed shift
        $duration = $this->opened_at->diffInMinutes($this->closed_at);
        
        // Handle case where duration is less than 1 minute
        if ($duration < 1) {
            return "1m"; // Show at least 1 minute for very short shifts
        }

        $hours = floor($duration / 60);
        $minutes = round($duration % 60);

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } else {
            return "{$minutes}m";
        }
    }

    /**
     * Calculate payment breakdown for this shift
     */
    public function calculatePaymentBreakdown(): array
    {
        $cashCollected = 0;
        $otherPaymentsCollected = 0;
        $paymentBreakdown = [];

        try {
            // Get all visits for this shift
            $visits = $this->visits()->with('patient')->get();

            foreach ($visits as $visit) {
                // Priority 1: Use patient.amount_paid as source of truth (same as UnpaidInvoicesController)
                $totalPaidAmount = floatval($visit->patient->amount_paid ?? 0);
                
                // Fallback to visit upfront_payment if patient.amount_paid is not available
                if ($totalPaidAmount == 0) {
                    $totalPaidAmount = floatval($visit->upfront_payment ?? 0);
                }

                // Get payment breakdown from visit metadata
                $metadata = [];
                if ($visit->metadata) {
                    if (is_array($visit->metadata)) {
                        $metadata = $visit->metadata;
                    } elseif (is_string($visit->metadata)) {
                        try {
                            $metadata = json_decode($visit->metadata, true) ?? [];
                        } catch (\Exception $e) {
                            \Log::warning('Failed to parse visit metadata in calculatePaymentBreakdown', [
                                'visit_id' => $visit->id,
                                'error' => $e->getMessage()
                            ]);
                            $metadata = [];
                        }
                    }
                }
                $paymentDetails = $metadata['payment_details'] ?? [];
                $patientData = $metadata['patient_data'] ?? [];

                $amountPaidCash = 0;
                $amountPaidCard = 0;
                $cardPaymentMethod = 'Card';
                $totalPaidFromBreakdown = 0;

                // Get payment breakdown from metadata
                if (!empty($paymentDetails)) {
                    $amountPaidCash = floatval($paymentDetails['amount_paid_cash'] ?? 0);
                    $amountPaidCard = floatval($paymentDetails['amount_paid_card'] ?? 0);
                    $cardPaymentMethod = $paymentDetails['additional_payment_method'] ?? 'Card';
                    $totalPaidFromBreakdown = $amountPaidCash + $amountPaidCard;
                }
                // Fall back to patient_data if payment_details is empty
                elseif (!empty($patientData)) {
                    $amountPaidCash = floatval($patientData['amount_paid_cash'] ?? 0);
                    $amountPaidCard = floatval($patientData['amount_paid_card'] ?? 0);
                    $cardPaymentMethod = $patientData['additional_payment_method'] ?? 'Card';
                    $totalPaidFromBreakdown = $amountPaidCash + $amountPaidCard;
                }

                // If breakdown exists but doesn't match total_paid, normalize it (same as CheckInController)
                if ($totalPaidFromBreakdown > 0 && abs($totalPaidFromBreakdown - $totalPaidAmount) > 0.01 && $totalPaidAmount > 0) {
                    // Scale down the breakdown to match total_paid
                    $scaleFactor = $totalPaidAmount / $totalPaidFromBreakdown;
                    $amountPaidCash = round($amountPaidCash * $scaleFactor, 2);
                    $amountPaidCard = round($amountPaidCard * $scaleFactor, 2);
                }
                
                // If no breakdown exists but we have total paid amount, create a simple breakdown
                if ($amountPaidCash == 0 && $amountPaidCard == 0 && $totalPaidAmount > 0) {
                    $visitPaymentMethod = $visit->payment_method ?? 'cash';
                    if (strtolower($visitPaymentMethod) === 'cash' || !$visitPaymentMethod) {
                        $amountPaidCash = $totalPaidAmount;
                    } else {
                        $amountPaidCard = $totalPaidAmount;
                        $cardPaymentMethod = $visitPaymentMethod;
                    }
                }

                // Add to totals
                if ($amountPaidCash > 0) {
                    $cashCollected += $amountPaidCash;
                }

                if ($amountPaidCard > 0) {
                    $otherPaymentsCollected += $amountPaidCard;
                    
                    if (!isset($paymentBreakdown[$cardPaymentMethod])) {
                        $paymentBreakdown[$cardPaymentMethod] = 0;
                    }
                    $paymentBreakdown[$cardPaymentMethod] += $amountPaidCard;
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error in calculatePaymentBreakdown for shift ' . $this->id . ': ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            // Return default values on error
        }

        return [
            'cash_collected' => $cashCollected,
            'other_payments_collected' => $otherPaymentsCollected,
            'payment_breakdown' => $paymentBreakdown,
            'total_collected' => $cashCollected + $otherPaymentsCollected,
        ];
    }

    /**
     * Calculate expenses breakdown for this shift
     */
    public function calculateExpensesBreakdown(): array
    {
        $expenses = $this->expenses()->get();
        $totalExpenses = 0;
        $expensesBreakdown = [];

        foreach ($expenses as $expense) {
            $totalExpenses += $expense->amount;
            $category = $expense->category ?? 'General';
            if (!isset($expensesBreakdown[$category])) {
                $expensesBreakdown[$category] = [];
            }
            $expensesBreakdown[$category][] = [
                'id' => $expense->id,
                'description' => $expense->description,
                'amount' => $expense->amount,
                'payment_method' => $expense->payment_method,
            ];
        }

        return [
            'total_expenses' => $totalExpenses,
            'expenses_breakdown' => $expensesBreakdown,
            'expenses_list' => $expenses->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'description' => $expense->description,
                    'amount' => $expense->amount,
                    'category' => $expense->category,
                    'payment_method' => $expense->payment_method,
                    'expense_date' => $expense->expense_date,
                ];
            })->toArray(),
        ];
    }

    /**
     * Update shift with calculated payment breakdown
     */
    public function updatePaymentBreakdown(): void
    {
        $breakdown = $this->calculatePaymentBreakdown();
        
        $this->update([
            'cash_collected' => $breakdown['cash_collected'],
            'other_payments_collected' => $breakdown['other_payments_collected'],
            'payment_breakdown' => $breakdown['payment_breakdown'],
            'total_collected' => $breakdown['total_collected'],
        ]);
    }

    public function getShiftReportData(): array
    {
        $visits = $this->visits()->with(['patient', 'labRequest'])->get();
        $payments = $this->payments()->get();
        $invoices = $this->invoices()->get();

        $reportData = [];
        
        foreach ($visits as $visit) {
            $patient = $visit->patient;
            $labRequest = $visit->labRequest;
            $invoice = $invoices->where('lab_request_id', $labRequest?->id)->first();
            
            // Calculate actual paid amount from visit data
            $paidAmount = 0;
            $totalAmount = $invoice?->total ?? $visit->total_amount ?? $visit->final_amount ?? 0;
            
            // Get payment details from visit metadata
            // Handle metadata - it might be an array (from cast) or a JSON string
            $metadata = [];
            if ($visit->metadata) {
                if (is_array($visit->metadata)) {
                    $metadata = $visit->metadata;
                } elseif (is_string($visit->metadata)) {
                    $metadata = json_decode($visit->metadata, true) ?? [];
                }
            }
            $paymentDetails = $metadata['payment_details'] ?? [];
            $patientData = $metadata['patient_data'] ?? [];
            
            // Calculate paid amount from metadata
            if (isset($paymentDetails['total_paid'])) {
                $paidAmount = $paymentDetails['total_paid'];
            } elseif (isset($patientData['total_paid'])) {
                $paidAmount = $patientData['total_paid'];
            } else {
                // Fallback to direct visit payment
                $paidAmount = $visit->upfront_payment ?? 0;
            }
            
            $remainingAmount = $totalAmount - $paidAmount;
            
            // Get lab number from multiple sources
            $labNumber = 'N/A';
            if ($labRequest?->full_lab_no) {
                $labNumber = $labRequest->full_lab_no;
            } elseif (isset($patientData['lab_number']) && $patientData['lab_number']) {
                $labNumber = $patientData['lab_number'];
            } elseif ($patient?->lab) {
                $labNumber = $patient->lab;
            }
            
            $reportData[] = [
                'patient_name' => $patient?->name ?? 'N/A',
                'lab_number' => $labNumber,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'type' => 'PATH', // Default type
                'sender' => $patient?->sender ?? $patient?->doctor?->name ?? 'N/A',
                'visit_date' => $visit->visit_date,
            ];
        }

        return $reportData;
    }
}
