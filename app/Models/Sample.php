<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sample extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_request_id',
        'sample_type',
        'sample_id',
        'collection_date',
        'received_date',
        'status',
        'notes',
        'barcode',
        'tsample',
        'nsample',
        'isample',
        'processing_started_at',
        'analysis_started_at',
        'completed_at',
        'disposed_at',
        'collected_by',
        'received_by',
        'processed_by',
        'analyzed_by',
        'disposed_by',
        'location',
    ];

    protected $casts = [
        'collection_date' => 'datetime',
        'received_date' => 'datetime',
        'processing_started_at' => 'datetime',
        'analysis_started_at' => 'datetime',
        'completed_at' => 'datetime',
        'disposed_at' => 'datetime',
    ];

    /**
     * Get the lab request that owns the sample.
     */
    public function labRequest(): BelongsTo
    {
        return $this->belongsTo(LabRequest::class);
    }

    /**
     * Get the full sample identifier.
     */
    public function getFullSampleIdAttribute(): string
    {
        $parts = array_filter([$this->tsample, $this->nsample, $this->isample]);
        return implode('-', $parts);
    }

    /**
     * Get the user who collected the sample.
     */
    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    /**
     * Get the user who received the sample.
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Get the user who processed the sample.
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get the user who analyzed the sample.
     */
    public function analyzedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analyzed_by');
    }

    /**
     * Get the user who disposed the sample.
     */
    public function disposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposed_by');
    }

    /**
     * Update sample status and track the user who performed the action.
     */
    public function updateStatus($status, $userId = null)
    {
        $this->status = $status;
        
        switch ($status) {
            case 'collected':
                $this->collection_date = now();
                $this->collected_by = $userId;
                break;
            case 'received':
                $this->received_date = now();
                $this->received_by = $userId;
                break;
            case 'processing':
                $this->processing_started_at = now();
                $this->processed_by = $userId;
                break;
            case 'analyzing':
                $this->analysis_started_at = now();
                $this->analyzed_by = $userId;
                break;
            case 'completed':
                $this->completed_at = now();
                break;
            case 'disposed':
                $this->disposed_at = now();
                $this->disposed_by = $userId;
                break;
        }
        
        $this->save();
    }

    /**
     * Generate a unique sample ID.
     */
    public static function generateSampleId()
    {
        return 'SMP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }
}
