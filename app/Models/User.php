<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function visits()
    {
        return $this->hasMany(Visit::class, 'created_by');
    }

    public function visitTests()
    {
        return $this->hasMany(VisitTest::class, 'performed_by');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'created_by');
    }

    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class, 'updated_by');
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isStaff()
    {
        return $this->role === 'staff';
    }

    public function isDoctor()
    {
        return $this->role === 'doctor';
    }

    public function isPatient()
    {
        return $this->role === 'patient';
    }

    // Legacy methods for backward compatibility
    public function isLabTech()
    {
        return $this->role === 'staff'; // Map lab_tech to staff
    }

    public function isAccountant()
    {
        return $this->role === 'staff'; // Map accountant to staff
    }

    public function hasRole($role)
    {
        return $this->role === $role;
    }

    public function hasAnyRole($roles)
    {
        return in_array($this->role, (array) $roles);
    }

    /**
     * Get the refresh tokens for the user
     */
    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function patient()
    {
        return $this->hasOne(Patient::class, 'user_id');
    }
} 