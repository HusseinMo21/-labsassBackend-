<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_id',
        'invoice_number',
        'invoice_date',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'amount_paid',
        'balance',
        'status',
        'payment_method',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    protected $appends = ['remaining_balance'];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getRemainingBalanceAttribute()
    {
        return $this->total_amount - $this->amount_paid;
    }

    public function isFullyPaid()
    {
        return $this->remaining_balance <= 0;
    }

    public function isPartiallyPaid()
    {
        return $this->amount_paid > 0 && $this->remaining_balance > 0;
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
        $this->payments()->create([
            'amount' => $amount,
            'payment_method' => $method,
            'payment_date' => now(),
            'notes' => $notes,
        ]);

        $this->amount_paid += $amount;
        $this->balance = $this->remaining_balance;
        $this->status = $this->payment_status;
        $this->save();

        return $this;
    }
} 