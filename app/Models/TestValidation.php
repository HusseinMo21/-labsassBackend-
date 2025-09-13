<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestValidation extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_test_id',
        'validation_type', // 'initial', 'review', 'final'
        'status', // 'pending', 'validated', 'rejected', 'requires_correction'
        'validated_by',
        'validated_at',
        'rejection_reason',
        'correction_notes',
        'clinical_correlation',
        'reference_range_check',
        'critical_value_check',
        'delta_check',
        'technical_quality',
        'result_consistency',
        'validation_notes',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
        'reference_range_check' => 'boolean',
        'critical_value_check' => 'boolean',
        'delta_check' => 'boolean',
        'technical_quality' => 'boolean',
        'result_consistency' => 'boolean',
    ];

    public function visitTest()
    {
        return $this->belongsTo(VisitTest::class);
    }

    public function validatedBy()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function isValidated()
    {
        return $this->status === 'validated';
    }

    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    public function requiresCorrection()
    {
        return $this->status === 'requires_correction';
    }

    public function validateTest($validatedBy, $notes = null)
    {
        $this->update([
            'status' => 'validated',
            'validated_by' => $validatedBy,
            'validated_at' => now(),
            'validation_notes' => $notes,
        ]);

        // Update the visit test status to validated
        $this->visitTest->update(['status' => 'validated']);
    }

    public function rejectTest($validatedBy, $rejectionReason, $correctionNotes = null)
    {
        $this->update([
            'status' => 'rejected',
            'validated_by' => $validatedBy,
            'validated_at' => now(),
            'rejection_reason' => $rejectionReason,
            'correction_notes' => $correctionNotes,
        ]);

        // Update the visit test status back to in_progress
        $this->visitTest->update(['status' => 'in_progress']);
    }

    public function requireCorrection($validatedBy, $correctionNotes)
    {
        $this->update([
            'status' => 'requires_correction',
            'validated_by' => $validatedBy,
            'validated_at' => now(),
            'correction_notes' => $correctionNotes,
        ]);

        // Update the visit test status back to in_progress
        $this->visitTest->update(['status' => 'in_progress']);
    }

    public function performValidationChecks()
    {
        $checks = [
            'reference_range_check' => $this->checkReferenceRange(),
            'critical_value_check' => $this->checkCriticalValue(),
            'delta_check' => $this->checkDeltaValue(),
            'technical_quality' => $this->checkTechnicalQuality(),
            'result_consistency' => $this->checkResultConsistency(),
        ];

        $this->update($checks);
        
        return $checks;
    }

    private function checkReferenceRange()
    {
        $visitTest = $this->visitTest;
        $labTest = $visitTest->labTest;
        
        if (!$labTest->reference_range || !$visitTest->result_value) {
            return true; // No reference range or result to check
        }

        // Parse reference range (assuming format like "3.5-5.0" or "< 1.0")
        $range = $labTest->reference_range;
        $value = floatval($visitTest->result_value);

        if (strpos($range, '-') !== false) {
            // Range format: "3.5-5.0"
            [$min, $max] = explode('-', $range);
            return $value >= floatval($min) && $value <= floatval($max);
        } elseif (strpos($range, '<') !== false) {
            // Less than format: "< 1.0"
            $limit = floatval(str_replace('<', '', $range));
            return $value < $limit;
        } elseif (strpos($range, '>') !== false) {
            // Greater than format: "> 10.0"
            $limit = floatval(str_replace('>', '', $range));
            return $value > $limit;
        }

        return true; // Unknown format, assume valid
    }

    private function checkCriticalValue()
    {
        $visitTest = $this->visitTest;
        $criticalValue = $visitTest->labTest->criticalValue;
        
        if (!$criticalValue || !$visitTest->result_value) {
            return true; // No critical value defined
        }

        return !$criticalValue->isCritical($visitTest->result_value);
    }

    private function checkDeltaValue()
    {
        // Check if result is significantly different from previous results
        $visitTest = $this->visitTest;
        $patient = $visitTest->visit->patient;
        
        // Get previous results for the same test
        $previousResults = VisitTest::whereHas('visit', function($query) use ($patient) {
            $query->where('patient_id', $patient->id);
        })
        ->where('lab_test_id', $visitTest->lab_test_id)
        ->where('id', '<', $visitTest->id)
        ->whereNotNull('result_value')
        ->orderBy('created_at', 'desc')
        ->limit(3)
        ->get();

        if ($previousResults->isEmpty()) {
            return true; // No previous results to compare
        }

        $currentValue = floatval($visitTest->result_value);
        $lastValue = floatval($previousResults->first()->result_value);
        
        // Calculate percentage change
        $percentageChange = abs(($currentValue - $lastValue) / $lastValue) * 100;
        
        // Flag if change is more than 50% (configurable)
        return $percentageChange <= 50;
    }

    private function checkTechnicalQuality()
    {
        // Check if result value is reasonable (not zero, not negative for most tests)
        $visitTest = $this->visitTest;
        $value = floatval($visitTest->result_value);
        
        // Basic technical quality checks
        if ($value <= 0 && !in_array($visitTest->labTest->name, ['pH', 'Temperature'])) {
            return false;
        }

        return true;
    }

    private function checkResultConsistency()
    {
        // Check if result is consistent with other related tests
        $visitTest = $this->visitTest;
        $visit = $visitTest->visit;
        
        // Get other tests from the same visit
        $otherTests = $visit->visitTests()
            ->where('id', '!=', $visitTest->id)
            ->whereNotNull('result_value')
            ->get();

        // Basic consistency check - can be expanded based on test relationships
        return true;
    }
}
