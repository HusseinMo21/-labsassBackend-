<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LabRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'lab_no',
        'suffix',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected $appends = [
        'full_lab_no',
        'barcode_url',
        'qr_code_url',
    ];

    /**
     * Get the patient that owns the lab request.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the samples for the lab request.
     */
    public function samples(): HasMany
    {
        return $this->hasMany(Sample::class);
    }

    /**
     * Get the report for the lab request.
     */
    public function report(): HasOne
    {
        return $this->hasOne(Report::class);
    }

    /**
     * Get the invoice for the lab request.
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * Get the full lab number (lab_no + suffix).
     */
    public function getFullLabNoAttribute(): string
    {
        return $this->lab_no . ($this->suffix ?: '');
    }

    /**
     * Get the barcode URL for the lab request.
     */
    public function getBarcodeUrlAttribute(): string
    {
        return asset('storage/barcodes/' . $this->full_lab_no . '_barcode.png');
    }

    /**
     * Get the QR code URL for the lab request.
     */
    public function getQrCodeUrlAttribute(): string
    {
        return asset('storage/qrcodes/' . $this->full_lab_no . '_qr.png');
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to search by lab number or full lab number.
     */
    public function scopeSearchByLabNo($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('lab_no', 'like', "%{$search}%")
              ->orWhere(function ($subQ) use ($search) {
                  $subQ->whereRaw("lab_no || COALESCE(suffix, '') LIKE ?", ["%{$search}%"]);
              });
        });
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
