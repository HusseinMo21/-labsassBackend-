<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'username',
        'original_password',
        'hashed_password',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public static function generateUsername($name)
    {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
        $username = $base . rand(100, 999);
        
        // Ensure uniqueness
        $counter = 1;
        while (self::where('username', $username)->exists()) {
            $username = $base . rand(100, 999) . $counter;
            $counter++;
        }
        
        return $username;
    }

    public static function generatePassword()
    {
        return strtoupper(substr(md5(uniqid()), 0, 8));
    }

    public function updateLastUsed()
    {
        $this->update(['last_used_at' => now()]);
    }
} 