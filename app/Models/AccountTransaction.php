<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountTransaction extends Model
{
    protected $fillable = [
        'account_id',
        'transaction_date',
        'amount',
        'paid_amount',
        'remaining_amount',
        'type',
        'description',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    /**
     * Get the account that owns this transaction
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Check if this is a purchase transaction
     */
    public function isPurchase(): bool
    {
        return $this->type === 'purchase';
    }

    /**
     * Check if this is a payment transaction
     */
    public function isPayment(): bool
    {
        return $this->type === 'payment';
    }
}
