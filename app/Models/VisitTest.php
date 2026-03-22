<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Lab;
use App\Models\User;
use App\Models\Notification;

class VisitTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_id',
        'lab_id',
        'lab_test_id',
        'test_category_id',
        'custom_test_name',
        'price',
        'price_at_time',
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
        'price_at_time' => 'decimal:2',
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

    public function lab()
    {
        return $this->belongsTo(Lab::class);
    }

    public function labTest()
    {
        return $this->belongsTo(LabTest::class);
    }

    public function testCategory()
    {
        return $this->hasOneThrough(TestCategory::class, LabTest::class, 'id', 'id', 'lab_test_id', 'category_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
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
     * Price frozen at order time (for invoices/reports after catalog changes).
     */
    public function unitPriceForBilling(): float
    {
        if ($this->final_price !== null) {
            return (float) $this->final_price;
        }
        if ($this->price_at_time !== null) {
            return (float) $this->price_at_time;
        }
        if ($this->custom_price !== null) {
            return (float) $this->custom_price;
        }

        return (float) $this->price;
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
        return $this->labTest && $this->labTest->category ? $this->labTest->category->name : 'Unknown';
    }

    /**
     * Boot method to handle model events
     */
    protected static function booted()
    {
        static::updated(function ($visitTest) {
            // Check if status changed to completed
            if ($visitTest->isDirty('status') && $visitTest->status === 'completed') {
                $visitTest->checkAndCompleteReports();
            }
        });
    }

    /**
     * Check if all tests in the visit are completed and mark reports as completed
     */
    public function checkAndCompleteReports()
    {
        $visit = $this->visit;
        if (!$visit || !$visit->labRequest) {
            return;
        }

        // Check if all tests in this visit are completed
        $totalTests = $visit->visitTests()->count();
        $completedTests = $visit->visitTests()->where('status', 'completed')->count();

        if ($totalTests > 0 && $totalTests === $completedTests) {
            // All tests are completed, mark the visit as completed
            if ($visit->status !== 'completed') {
                $visit->update([
                    'status' => 'completed',
                    'completed_at' => now()
                ]);
                \Log::info('Visit marked as completed: ' . $visit->id);
            }
            
            // Mark the report as completed
            $report = \App\Models\Report::where('lab_request_id', $visit->labRequest->id)->first();
            if ($report && $report->status !== 'completed') {
                $report->update([
                    'status' => 'completed',
                    'generated_at' => now(),
                ]);
                
                \Log::info('Report marked as completed for lab request: ' . $visit->labRequest->id);
            }
        }
    }
} 