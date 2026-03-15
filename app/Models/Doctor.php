<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Lab;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Doctor extends Model
{
    use HasFactory, \App\Models\Concerns\BelongsToLab;

    protected $fillable = [
        'name',
        'lab_id',
    ];

    public function lab()
    {
        return $this->belongsTo(Lab::class);
    }

    public function patients()
    {
        return $this->hasMany(Patient::class, 'doctor_id', 'name');
    }

    public function getPatientsCountAttribute()
    {
        return $this->patients()->count();
    }
}
