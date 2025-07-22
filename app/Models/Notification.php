<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        'visit_test_id',
        'patient_id',
        'type',
        'recipient_type',
        'recipient_contact',
        'message',
        'status',
        'sent_at',
        'delivered_at',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function visitTest()
    {
        return $this->belongsTo(VisitTest::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function markAsSent()
    {
        $this->status = 'sent';
        $this->sent_at = now();
        $this->save();
    }

    public function markAsDelivered()
    {
        $this->status = 'delivered';
        $this->delivered_at = now();
        $this->save();
    }

    public function markAsFailed($errorMessage = null)
    {
        $this->status = 'failed';
        $this->error_message = $errorMessage;
        $this->save();
    }

    public static function createResultNotification($visitTest, $recipientType = 'patient')
    {
        $patient = $visitTest->visit->patient;
        $test = $visitTest->labTest;
        
        $message = "Your test result for {$test->name} is ready. Please contact the laboratory for details.";
        
        if ($recipientType === 'patient') {
            $contact = $patient->phone;
            $type = 'sms';
        } else {
            // For doctors, you might want to use email
            $contact = $patient->email ?? $patient->phone;
            $type = 'email';
        }

        return self::create([
            'visit_test_id' => $visitTest->id,
            'patient_id' => $patient->id,
            'type' => $type,
            'recipient_type' => $recipientType,
            'recipient_contact' => $contact,
            'message' => $message,
            'status' => 'pending',
        ]);
    }

    public static function createCriticalAlert($visitTest, $criticalValue, $value)
    {
        $patient = $visitTest->visit->patient;
        $test = $visitTest->labTest;
        
        $message = $criticalValue->getNotificationMessage($value, $patient->name);
        
        return self::create([
            'visit_test_id' => $visitTest->id,
            'patient_id' => $patient->id,
            'type' => 'critical_alert',
            'recipient_type' => 'lab_staff',
            'recipient_contact' => 'lab@example.com', // Configure this
            'message' => $message,
            'status' => 'pending',
        ]);
    }
} 