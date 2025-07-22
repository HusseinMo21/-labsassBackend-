<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'gender',
        'birth_date',
        'phone',
        'email',
        'address',
        'national_id',
        'insurance_provider',
        'insurance_number',
        'has_insurance',
        'insurance_coverage',
        'billing_address',
        'emergency_contact',
        'emergency_phone',
        'emergency_relationship',
        'medical_history',
        'allergies',
        'username',
        'password',
        'user_id',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'has_insurance' => 'boolean',
        'insurance_coverage' => 'decimal:2',
    ];

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

    public function invoices()
    {
        return $this->hasManyThrough(Invoice::class, Visit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function credentials()
    {
        return $this->hasOne(PatientCredential::class);
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
        return $this->birth_date->age;
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
} 