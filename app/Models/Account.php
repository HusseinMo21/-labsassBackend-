<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'name',
        'description',
        'total_amount',
        'total_paid',
        'remaining_balance',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
    ];

    /**
     * Get all transactions for this account
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class)->orderBy('transaction_date', 'desc');
    }

    /**
     * Get the latest transaction
     */
    public function latestTransaction()
    {
        return $this->hasOne(AccountTransaction::class)->latest('transaction_date');
    }

    /**
     * Check if account is fully paid
     */
    public function isFullyPaid(): bool
    {
        return $this->remaining_balance <= 0;
    }

    /**
     * Check if account has any payments
     */
    public function hasPayments(): bool
    {
        return $this->total_paid > 0;
    }
}
