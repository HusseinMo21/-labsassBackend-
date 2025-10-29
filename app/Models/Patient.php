<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $table = 'patient';
    public $timestamps = true;

    protected $fillable = [
        // Modern fields
        'name',
        'gender',
        'birth_date',
        'phone',
        'whatsapp_number',
        'address',
        'emergency_contact',
        'emergency_phone',
        'medical_history',
        'allergies',
        'address_required',
        'address_optional',
        'organization',
        'status',
        'user_id',
        'doctor_id', // Store doctor name as string
        'organization_id', // Store organization name as string
        
        // Patient Registration fields
        'sample_type',
        'case_type',
        'sample_size',
        'number_of_samples',
        'day_of_week',
        'previous_tests',
        'attendance_date',
        'delivery_date',
        'total_amount',
        'amount_paid',
        
        // Legacy fields
        'entry',
        'deli',
        'time',
        'age',
        'tsample',
        'nsample',
        'isample',
        'paid',
        'had',
        'sender',
        'pleft',
        'total',
        'lab',
        'entryday',
        'deliday',
        'type',
        // Delivery tracking fields
        'report_delivered',
        'report_delivery_date',
        'report_delivery_notes',
        'report_delivered_by',
        'wax_blocks_delivered',
        'wax_blocks_delivery_date',
        'wax_blocks_delivery_notes',
        'wax_blocks_delivered_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'age' => 'integer',
        'paid' => 'integer',
        'pleft' => 'integer',
        'total' => 'integer',
        'number_of_samples' => 'integer',
        'attendance_date' => 'date',
        'delivery_date' => 'date',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'report_delivered' => 'boolean',
        'report_delivery_date' => 'date',
        'wax_blocks_delivered' => 'boolean',
        'wax_blocks_delivery_date' => 'date',
    ];

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

    public function labRequest()
    {
        return $this->hasOne(LabRequest::class);
    }

    public function invoices()
    {
        return $this->hasManyThrough(Invoice::class, LabRequest::class, 'patient_id', 'lab_request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

        // Custom relationships that work with string-based storage
        public function doctor()
        {
            return $this->belongsTo(Doctor::class, 'doctor_id', 'name');
        }

        public function organization()
        {
            return $this->belongsTo(Organization::class, 'organization_id', 'name');
        }

    public function credentials()
    {
        return $this->hasOne(PatientCredential::class);
    }

    public function labRequests()
    {
        return $this->hasMany(LabRequest::class);
    }

    public function reports()
    {
        return $this->hasManyThrough(Report::class, LabRequest::class, 'patient_id', 'lab_request_id');
    }

    public function getPortalCredentials()
    {
        // First check if patient has credentials in the patients table (original registration)
        if ($this->username) {
            // Check if we have credentials in patient_credentials table
            $credentials = $this->credentials;
            if ($credentials) {
                return [
                    'username' => $credentials->username,
                    'password' => $credentials->original_password,
                ];
            }
            
            // If no credentials in patient_credentials table, return the username from patients table
            // but we can't return the original password since it's hashed
            return [
                'username' => $this->username,
                'password' => 'Contact administrator for password reset',
            ];
        }
        
        // Check patient_credentials table (new system)
        $credentials = $this->credentials;
        if ($credentials) {
            return [
                'username' => $credentials->username,
                'password' => $credentials->original_password,
            ];
        }
        
        return null;
    }

    public function getAgeAttribute()
    {
        if (isset($this->attributes['age']) && $this->attributes['age']) {
            return $this->attributes['age'];
        }
        
        if ($this->birth_date) {
            return \Carbon\Carbon::parse($this->birth_date)->age;
        }
        
        return null;
    }

    public function getBirthDateAttribute()
    {
        if (isset($this->attributes['birth_date']) && $this->attributes['birth_date']) {
            return $this->attributes['birth_date'];
        }
        
        if (isset($this->attributes['age']) && $this->attributes['age']) {
            return now()->subYears($this->attributes['age'])->format('Y-m-d');
        }
        
        return null;
    }

    public function getLatestVisitAttribute()
    {
        return $this->visits()->latest()->first();
    }

    public function getTotalVisitsAttribute()
    {
        return $this->visits()->count();
    }

    public function getTotalSpentAttribute()
    {
        return $this->visits()->sum('final_amount');
    }

    public static function generateUsername($name)
    {
        return PatientCredential::generateUsername($name);
    }

    public static function generatePassword()
    {
        return PatientCredential::generatePassword();
    }

    public function getInsuranceDiscountAmount($totalAmount)
    {
        if (!$this->has_insurance || $this->insurance_coverage <= 0) {
            return 0;
        }
        
        return ($totalAmount * $this->insurance_coverage) / 100;
    }

    public function getBillingAddressAttribute($value)
    {
        return $value ?: $this->address;
    }

    /**
     * Get the doctor name from the sender field
     */
    public function getDoctorNameAttribute()
    {
        return $this->sender ?: 'N/A';
    }

    /**
     * Get the doctor name for display purposes
     */
    public function getDoctorDisplayNameAttribute()
    {
        if ($this->sender) {
            return $this->sender;
        }
        
        // Fallback to doctor relationship if available
        if ($this->doctor) {
            return $this->doctor->name;
        }
        
        return 'N/A';
    }
} 