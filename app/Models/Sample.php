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
        'barcode',
        'sample_id',
        'tsample',
        'nsample',
        'isample',
        'notes',
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
}
