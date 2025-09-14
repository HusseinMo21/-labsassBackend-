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
        'test_category_id',
        'custom_test_name',
        'price',
        'custom_price',
        'discount_amount',
        'discount_percentage',
        'final_price',
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
        'custom_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'final_price' => 'decimal:2',
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

    public function testCategory()
    {
        return $this->belongsTo(TestCategory::class);
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

    /**
     * Get the test name (either from lab test or custom name)
     */
    public function getTestNameAttribute()
    {
        if ($this->custom_test_name) {
            return $this->custom_test_name;
        }
        
        return $this->labTest ? $this->labTest->name : 'Unknown Test';
    }

    /**
     * Get the test price (either from lab test or custom price)
     */
    public function getTestPriceAttribute()
    {
        if ($this->custom_price) {
            return $this->custom_price;
        }
        
        return $this->price;
    }

    /**
     * Calculate final price after discount
     */
    public function calculateFinalPrice()
    {
        $basePrice = $this->test_price;
        
        // Apply percentage discount first
        if ($this->discount_percentage > 0) {
            $discountAmount = ($basePrice * $this->discount_percentage) / 100;
            $basePrice = $basePrice - $discountAmount;
        }
        
        // Apply fixed discount
        if ($this->discount_amount > 0) {
            $basePrice = $basePrice - $this->discount_amount;
        }
        
        // Ensure price doesn't go below 0
        return max(0, $basePrice);
    }

    /**
     * Get the category name
     */
    public function getCategoryNameAttribute()
    {
        return $this->testCategory ? $this->testCategory->name : 'Unknown';
    }
} 