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
        if (!$this->closed_at) {
            return 'Ongoing';
        }

        // Handle case where closed_at might be before opened_at (data inconsistency)
        if ($this->closed_at < $this->opened_at) {
            return 'Data Error';
        }

        $duration = $this->opened_at->diffInMinutes($this->closed_at);
        $hours = floor($duration / 60);
        $minutes = $duration % 60;

        return "{$hours}h {$minutes}m";
    }

    /**
     * Calculate payment breakdown for this shift
     */
    public function calculatePaymentBreakdown(): array
    {
        $cashCollected = 0;
        $otherPaymentsCollected = 0;
        $paymentBreakdown = [];

        // Get all visits for this shift
        $visits = $this->visits()->with('patient')->get();

        foreach ($visits as $visit) {
            $visitPaymentAmount = $visit->upfront_payment ?? 0;
            $visitPaymentMethod = $visit->payment_method ?? 'cash';

            // First, try to get payment details from visit metadata (for patient registration)
            $metadata = json_decode($visit->metadata ?? '{}', true);
            $paymentDetails = $metadata['payment_details'] ?? [];
            $patientData = $metadata['patient_data'] ?? [];

            // Check metadata payment details first
            if (isset($paymentDetails['amount_paid_cash']) && $paymentDetails['amount_paid_cash'] > 0) {
                $cashCollected += $paymentDetails['amount_paid_cash'];
            }

            if (isset($paymentDetails['amount_paid_card']) && $paymentDetails['amount_paid_card'] > 0) {
                $otherPaymentsCollected += $paymentDetails['amount_paid_card'];
                $paymentMethod = $paymentDetails['additional_payment_method'] ?? 'Card';
                
                if (!isset($paymentBreakdown[$paymentMethod])) {
                    $paymentBreakdown[$paymentMethod] = 0;
                }
                $paymentBreakdown[$paymentMethod] += $paymentDetails['amount_paid_card'];
            }

            // Check patient_data payment details (for patient registration)
            if (isset($patientData['amount_paid_cash']) && $patientData['amount_paid_cash'] > 0) {
                $cashCollected += $patientData['amount_paid_cash'];
            }

            if (isset($patientData['amount_paid_card']) && $patientData['amount_paid_card'] > 0) {
                $otherPaymentsCollected += $patientData['amount_paid_card'];
                $paymentMethod = $patientData['additional_payment_method'] ?? 'Card';
                
                if (!isset($paymentBreakdown[$paymentMethod])) {
                    $paymentBreakdown[$paymentMethod] = 0;
                }
                $paymentBreakdown[$paymentMethod] += $patientData['amount_paid_card'];
            }

            // If no metadata payment details found, use direct visit payment fields (for CheckIn visits)
            if (empty($paymentDetails) && empty($patientData) && $visitPaymentAmount > 0) {
                if ($visitPaymentMethod === 'cash') {
                    $cashCollected += $visitPaymentAmount;
                } else {
                    $otherPaymentsCollected += $visitPaymentAmount;
                    
                    if (!isset($paymentBreakdown[$visitPaymentMethod])) {
                        $paymentBreakdown[$visitPaymentMethod] = 0;
                    }
                    $paymentBreakdown[$visitPaymentMethod] += $visitPaymentAmount;
                }
            }
        }

        return [
            'cash_collected' => $cashCollected,
            'other_payments_collected' => $otherPaymentsCollected,
            'payment_breakdown' => $paymentBreakdown,
            'total_collected' => $cashCollected + $otherPaymentsCollected,
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
            $metadata = json_decode($visit->metadata ?? '{}', true);
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
            
            $reportData[] = [
                'patient_name' => $patient?->name ?? 'N/A',
                'lab_number' => $labRequest?->full_lab_no ?? 'N/A',
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
