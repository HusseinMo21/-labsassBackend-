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

        $duration = $this->opened_at->diffInMinutes($this->closed_at);
        $hours = floor($duration / 60);
        $minutes = $duration % 60;

        return "{$hours}h {$minutes}m";
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
            
            $reportData[] = [
                'patient_name' => $patient?->name ?? 'N/A',
                'lab_number' => $labRequest?->full_lab_no ?? 'N/A',
                'total_amount' => $invoice?->total ?? $visit->total_amount ?? 0,
                'paid_amount' => $invoice?->paid ?? 0,
                'remaining_amount' => $invoice?->remaining ?? 0,
                'type' => 'PATH', // Default type
                'sender' => $patient?->sender ?? $patient?->doctor?->name ?? 'N/A',
                'visit_date' => $visit->visit_date,
            ];
        }

        return $reportData;
    }
}
