<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLab;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LabRequest extends Model
{
    use BelongsToLab, HasFactory;

    protected $fillable = [
        'lab_id',
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

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

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
     * Get the reports for the lab request.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Get the invoice for the lab request.
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * Get the visit for the lab request.
     */
    public function visit(): HasOne
    {
        return $this->hasOne(Visit::class, 'lab_request_id', 'id');
    }

    /**
     * Get the visits for the lab request.
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class, 'lab_request_id', 'id');
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
        $labId = $this->lab_id ?? auth()->user()?->lab_id ?? (app()->bound('current_lab_id') ? app('current_lab_id') : 1);
        $basePath = $labId ? "labs/{$labId}/barcodes" : 'barcodes';
        return asset('storage/' . $basePath . '/' . $this->full_lab_no . '_barcode.svg');
    }

    /**
     * Get the QR code URL for the lab request.
     */
    public function getQrCodeUrlAttribute(): string
    {
        $labId = $this->lab_id ?? auth()->user()?->lab_id ?? (app()->bound('current_lab_id') ? app('current_lab_id') : 1);
        $basePath = $labId ? "labs/{$labId}/qrcodes" : 'qrcodes';
        return asset('storage/' . $basePath . '/' . $this->full_lab_no . '_qr.svg');
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to search by lab number, full lab number, or patient information.
     */
    public function scopeSearchByLabNo($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('lab_no', 'like', "%{$search}%")
              ->orWhere(function ($subQ) use ($search) {
                  $subQ->whereRaw("CONCAT(lab_no, COALESCE(suffix, '')) LIKE ?", ["%{$search}%"]);
              })
              ->orWhereHas('patient', function ($patientQuery) use ($search) {
                  $patientQuery->where('name', 'like', "%{$search}%")
                              ->orWhere('phone', 'like', "%{$search}%");
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
