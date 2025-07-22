<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Notification;

class VisitTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_id',
        'lab_test_id',
        'price',
        'status',
        'barcode_uid',
        'result_value',
        'result_status',
        'result_notes',
        'performed_by',
        'performed_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'performed_at' => 'datetime',
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function labTest()
    {
        return $this->belongsTo(LabTest::class);
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function sampleTracking()
    {
        return $this->hasOne(SampleTracking::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function checkCriticalValue($value)
    {
        $criticalValue = $this->labTest->criticalValue;
        
        if (!$criticalValue) {
            return null;
        }

        if ($criticalValue->isCritical($value)) {
            // Create critical alert notification
            Notification::createCriticalAlert($this, $criticalValue, $value);
            return $criticalValue->getCriticalType($value);
        }

        return null;
    }

    public static function generateBarcodeUid()
    {
        $prefix = 'VT';
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        
        return $prefix . $timestamp . $random;
    }
} 