<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnhancedReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'nos',
        'reff',
        'clinical',
        'nature',
        'report_date',
        'lab_no',
        'age',
        'gross',
        'micro',
        'conc',
        'reco',
        'type',
        'sex',
        'recieving',
        'discharge',
        'confirm',
        'print',
        'patient_id',
        'lab_request_id',
        'created_by',
        'reviewed_by',
        'approved_by',
        'status',
        'priority',
        'examination_details',
        'quality_control',
        'barcode',
        'digital_signature',
        'reviewed_at',
        'approved_at',
        'printed_at',
        'delivered_at',
        'image_path',
        'image_filename',
        'image_mime_type',
        'image_size',
        'image_uploaded_at',
        'image_uploaded_by',
    ];

    protected $casts = [
        'report_date' => 'date',
        'confirm' => 'boolean',
        'print' => 'boolean',
        'examination_details' => 'array',
        'quality_control' => 'array',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'printed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'image_uploaded_at' => 'datetime',
        'image_size' => 'integer',
    ];

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function labRequest(): BelongsTo
    {
        return $this->belongsTo(LabRequest::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function imageUploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'image_uploaded_by');
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', 'under_review');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePrinted($query)
    {
        return $query->where('status', 'printed');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('confirm', true);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    // Accessors & Mutators
    public function getFormattedReportDateAttribute()
    {
        return $this->report_date ? $this->report_date->format('Y-m-d') : null;
    }

    public function getStatusBadgeAttribute()
    {
        $badges = [
            'draft' => 'secondary',
            'under_review' => 'warning',
            'approved' => 'success',
            'printed' => 'info',
            'delivered' => 'primary',
        ];

        return $badges[$this->status] ?? 'secondary';
    }

    public function getPriorityBadgeAttribute()
    {
        $badges = [
            'low' => 'secondary',
            'normal' => 'primary',
            'high' => 'warning',
            'urgent' => 'danger',
        ];

        return $badges[$this->priority] ?? 'primary';
    }

    // Methods
    public function markAsUnderReview($reviewedBy = null)
    {
        $this->update([
            'status' => 'under_review',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ]);
    }

    public function markAsApproved($approvedBy = null)
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'confirm' => true,
        ]);
    }

    public function markAsPrinted()
    {
        $this->update([
            'status' => 'printed',
            'printed_at' => now(),
            'print' => true,
        ]);
    }

    public function markAsDelivered()
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function generateBarcode()
    {
        if (!$this->barcode) {
            $this->barcode = 'RPT-' . str_pad($this->id, 6, '0', STR_PAD_LEFT);
            $this->save();
        }
        return $this->barcode;
    }

    public function isEditable()
    {
        return in_array($this->status, ['draft', 'under_review']);
    }

    public function canBeApproved()
    {
        return $this->status === 'under_review';
    }

    public function canBePrinted()
    {
        return $this->status !== 'draft';
    }

    public function canBeDelivered()
    {
        return $this->status === 'printed';
    }

    // Image-related methods
    public function hasImage()
    {
        return !empty($this->image_path);
    }

    public function getImageUrl()
    {
        if (!$this->hasImage()) {
            return null;
        }
        
        return asset('storage/' . $this->image_path);
    }

    public function getImageSizeFormatted()
    {
        if (!$this->image_size) {
            return null;
        }

        $bytes = $this->image_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function deleteImage()
    {
        if ($this->hasImage() && \Storage::disk('public')->exists($this->image_path)) {
            \Storage::disk('public')->delete($this->image_path);
        }
        
        $this->update([
            'image_path' => null,
            'image_filename' => null,
            'image_mime_type' => null,
            'image_size' => null,
            'image_uploaded_at' => null,
            'image_uploaded_by' => null,
        ]);
    }
}
