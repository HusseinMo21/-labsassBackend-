<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\LabTest;

class CriticalValue extends Model
{
    use HasFactory;

    protected $table = 'critical_values';

    protected $fillable = [
        'lab_test_id',
        'critical_low',
        'critical_high',
        'unit',
        'notification_message',
        'is_active',
    ];

    protected $casts = [
        'critical_low' => 'decimal:2',
        'critical_high' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function labTest()
    {
        return $this->belongsTo(LabTest::class);
    }

    public function isCritical($value)
    {
        if (!$this->is_active) {
            return false;
        }

        $numericValue = is_numeric($value) ? (float) $value : null;
        
        if ($numericValue === null) {
            return false; // Non-numeric values can't be critical
        }

        if ($this->critical_low !== null && $numericValue <= $this->critical_low) {
            return true;
        }

        if ($this->critical_high !== null && $numericValue >= $this->critical_high) {
            return true;
        }

        return false;
    }

    public function getCriticalType($value)
    {
        if (!$this->isCritical($value)) {
            return null;
        }

        $numericValue = (float) $value;

        if ($this->critical_low !== null && $numericValue <= $this->critical_low) {
            return 'low';
        }

        if ($this->critical_high !== null && $numericValue >= $this->critical_high) {
            return 'high';
        }

        return null;
    }

    public function getNotificationMessage($value, $patientName = null)
    {
        $criticalType = $this->getCriticalType($value);
        
        if ($this->notification_message) {
            return str_replace(
                ['{patient}', '{value}', '{unit}', '{type}'],
                [$patientName ?? 'Patient', $value, $this->unit ?? '', $criticalType],
                $this->notification_message
            );
        }

        $type = $criticalType === 'low' ? 'LOW' : 'HIGH';
        $patient = $patientName ? " for patient {$patientName}" : '';
        
        return "CRITICAL {$type} VALUE: {$this->labTest->name} = {$value} {$this->unit}{$patient}";
    }
} 