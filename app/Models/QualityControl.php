<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QualityControl extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_test_id',
        'qc_type', // 'pre_test', 'post_test', 'batch_control'
        'status', // 'pending', 'passed', 'failed', 'requires_review'
        'control_sample_id',
        'expected_value',
        'actual_value',
        'tolerance_range',
        'performed_by',
        'performed_at',
        'reviewed_by',
        'reviewed_at',
        'notes',
        'equipment_used',
        'reagent_lot_number',
        'reagent_expiry_date',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'reagent_expiry_date' => 'date',
        'expected_value' => 'decimal:4',
        'actual_value' => 'decimal:4',
        'tolerance_range' => 'decimal:2',
    ];

    public function visitTest()
    {
        return $this->belongsTo(VisitTest::class);
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPassed()
    {
        return $this->status === 'passed';
    }

    public function isFailed()
    {
        return $this->status === 'failed';
    }

    public function requiresReview()
    {
        return $this->status === 'requires_review';
    }

    public function calculateDeviation()
    {
        if (!$this->expected_value || !$this->actual_value) {
            return null;
        }

        return abs($this->actual_value - $this->expected_value);
    }

    public function calculatePercentageDeviation()
    {
        if (!$this->expected_value || !$this->actual_value) {
            return null;
        }

        return (abs($this->actual_value - $this->expected_value) / $this->expected_value) * 100;
    }

    public function isWithinTolerance()
    {
        if (!$this->tolerance_range || !$this->actual_value || !$this->expected_value) {
            return false;
        }

        $deviation = $this->calculateDeviation();
        return $deviation <= $this->tolerance_range;
    }

    public function markAsPassed($reviewedBy = null)
    {
        $this->update([
            'status' => 'passed',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ]);
    }

    public function markAsFailed($reviewedBy = null, $notes = null)
    {
        $this->update([
            'status' => 'failed',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'notes' => $notes ? $this->notes . "\n" . $notes : $this->notes,
        ]);
    }

    public function markAsRequiresReview($reviewedBy = null, $notes = null)
    {
        $this->update([
            'status' => 'requires_review',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'notes' => $notes ? $this->notes . "\n" . $notes : $this->notes,
        ]);
    }
}
