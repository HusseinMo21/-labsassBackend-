<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'unit',
        'quantity',
        'minimum_quantity',
        'unit_price',
        'supplier',
        'expiry_date',
        'status',
        'updated_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'minimum_quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getIsLowStockAttribute()
    {
        return $this->quantity <= $this->minimum_quantity;
    }

    public function getIsOutOfStockAttribute()
    {
        return $this->quantity <= 0;
    }

    public function getIsExpiredAttribute()
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'active' => 'success',
            'low_stock' => 'warning',
            'out_of_stock' => 'danger',
            'expired' => 'secondary',
            default => 'info'
        };
    }

    public function getFormattedUnitPriceAttribute()
    {
        return $this->unit_price ? '$' . number_format($this->unit_price, 2) : 'N/A';
    }

    public function getTotalValueAttribute()
    {
        return $this->unit_price ? $this->quantity * $this->unit_price : 0;
    }

    public function getFormattedTotalValueAttribute()
    {
        return '$' . number_format($this->total_value, 2);
    }
} 