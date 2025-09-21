<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;
    
    public $timestamps = false;

    protected $fillable = [
        'lab',
        'total',
        'paid',
        'remaining',
        'lab_request_id',
        'shift_id',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'paid' => 'decimal:2',
        'remaining' => 'decimal:2',
    ];

    protected $appends = ['remaining_balance', 'lab_number'];

    // Note: Invoice table doesn't have visit_id column, so this relationship is disabled
    // public function visit()
    // {
    //     return $this->belongsTo(Visit::class);
    // }

    public function labRequest()
    {
        return $this->belongsTo(LabRequest::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function getRemainingBalanceAttribute()
    {
        return $this->remaining;
    }

    public function isFullyPaid()
    {
        return $this->remaining <= 0;
    }

    public function isPartiallyPaid()
    {
        return $this->paid > 0 && $this->remaining > 0;
    }

    public function getPaymentStatusAttribute()
    {
        if ($this->isFullyPaid()) {
            return 'paid';
        } elseif ($this->isPartiallyPaid()) {
            return 'partial';
        } else {
            return 'pending';
        }
    }

    public function addPayment($amount, $method = 'cash', $notes = '')
    {
        // Validate that the invoice is not already fully paid
        if ($this->isFullyPaid()) {
            throw new \Exception('Invoice is already fully paid');
        }

        // Validate that the payment amount doesn't exceed remaining balance
        if ($amount > $this->remaining) {
            throw new \Exception('Payment amount exceeds remaining balance');
        }

        // Get current staff shift
        $currentShift = \App\Models\Shift::where('staff_id', auth()->id())
            ->where('status', 'open')
            ->whereDate('opened_at', today())
            ->first();

        $this->payments()->create([
            'paid' => $amount,
            'comment' => $notes,
            'date' => now()->toDateString(),
            'author' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'income' => 1,
            'invoice_id' => $this->id,
            'shift_id' => $currentShift?->id,
        ]);

        // Update invoice totals
        $this->paid += $amount;
        $this->remaining = max(0, $this->total - $this->paid);
        $this->save();

        return $this;
    }

    /**
     * Get the lab number for this invoice.
     */
    public function getLabNumberAttribute()
    {
        if ($this->labRequest) {
            return $this->labRequest->full_lab_no;
        }
        
        // Note: Visit relationship disabled
        // if ($this->visit && $this->visit->labRequest) {
        //     return $this->visit->labRequest->full_lab_no;
        // }
        
        return null;
    }
} 