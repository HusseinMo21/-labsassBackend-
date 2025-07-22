<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function labTests()
    {
        return $this->hasMany(LabTest::class, 'category_id');
    }

    public function getActiveTestsAttribute()
    {
        return $this->labTests()->where('is_active', true);
    }
} 