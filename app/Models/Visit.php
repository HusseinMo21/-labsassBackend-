<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'lab_request_id',
        'visit_number',
        'visit_date',
        'visit_time',
        'total_amount',
        'discount_amount',
        'final_amount',
        'upfront_payment',
        'remaining_balance',
        'minimum_upfront_percentage',
        'payment_method',
        'receipt_number',
        'expected_delivery_date',
        'barcode',
        'check_in_by',
        'check_in_at',
        'billing_status',
        'status',
        'remarks',
        'completed_at',
        'clinical_data',
        'microscopic_description',
        'diagnosis',
        'recommendations',
        'referred_doctor',
        'test_status',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'visit_time' => 'datetime:H:i',
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'upfront_payment' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'minimum_upfront_percentage' => 'decimal:2',
        'expected_delivery_date' => 'date',
        'check_in_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = ['lab_number'];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function visitTests()
    {
        return $this->hasMany(VisitTest::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function labRequest()
    {
        return $this->belongsTo(LabRequest::class);
    }

    public function reports()
    {
        return $this->hasManyThrough(Report::class, LabRequest::class, 'id', 'lab_request_id', 'lab_request_id', 'id');
    }

    public static function generateReceiptNumber()
    {
        $prefix = 'RCP';
        $date = now()->format('Ymd');
        $lastReceipt = self::where('receipt_number', 'like', $prefix . $date . '%')
                           ->orderBy('receipt_number', 'desc')
                           ->first();
        
        if ($lastReceipt) {
            $lastNumber = intval(substr($lastReceipt->receipt_number, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $date . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public static function generateBarcode()
    {
        $prefix = 'LAB';
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        
        $barcodeText = $prefix . $timestamp . $random;
        
        // Generate simple HTML barcode for receipt display
        try {
            // Create a simple barcode representation using HTML/CSS
            $bars = '';
            $textLength = strlen($barcodeText);
            
            // Generate bars based on text characters
            for ($i = 0; $i < $textLength; $i++) {
                $char = $barcodeText[$i];
                $charCode = ord($char);
                
                // Create bars based on character code
                $barWidth = ($charCode % 4) + 1; // 1-4 pixels wide
                $bars .= '<div style="display:inline-block;width:' . $barWidth . 'px;height:40px;background-color:black;margin-right:1px;"></div>';
            }
            
            return '<div style="font-family:monospace;font-size:12px;text-align:center;padding:8px;border:1px solid #ccc;background:white;margin:4px 0;">
                        <div style="margin-bottom:5px;font-weight:bold;">' . htmlspecialchars($barcodeText) . '</div>
                        <div style="margin:5px 0;">' . $bars . '</div>
                        <div style="font-size:10px;color:#666;">BARCODE</div>
                    </div>';
        } catch (\Exception $e) {
            // Fallback to text if barcode generation fails
            \Log::error('Failed to generate barcode in Visit model: ' . $e->getMessage());
            return $barcodeText;
        }
    }

    public static function generateVisitNumber()
    {
        $prefix = 'VIS';
        $date = now()->format('Ymd');
        $lastVisit = self::where('visit_number', 'like', $prefix . $date . '%')
                         ->orderBy('visit_number', 'desc')
                         ->first();
        
        if ($lastVisit) {
            $lastNumber = intval(substr($lastVisit->visit_number, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $date . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function calculateMinimumUpfront()
    {
        return ($this->final_amount * $this->minimum_upfront_percentage) / 100;
    }

    public function processPayment($amountPaid, $paymentMethod = 'cash')
    {
        $this->upfront_payment = $amountPaid;
        $this->remaining_balance = $this->final_amount - $amountPaid;
        $this->payment_method = $paymentMethod;
        
        if ($this->remaining_balance <= 0) {
            $this->billing_status = 'paid';
        } elseif ($this->upfront_payment > 0) {
            $this->billing_status = 'partial';
        } else {
            $this->billing_status = 'pending';
        }
        
        $this->save();
    }

    public function getExpectedDeliveryDate()
    {
        if ($this->expected_delivery_date) {
            return $this->expected_delivery_date;
        }
        
        // Calculate based on test turnaround times
        $maxTurnaroundHours = $this->visitTests()
            ->join('lab_tests', 'visit_tests.lab_test_id', '=', 'lab_tests.id')
            ->max('lab_tests.turnaround_time_hours') ?? 24;
        
        return now()->addHours($maxTurnaroundHours)->toDateString();
    }

    /**
     * Get the lab number for this visit.
     */
    public function getLabNumberAttribute()
    {
        if ($this->labRequest) {
            return $this->labRequest->full_lab_no;
        }
        
        return null;
    }
} 