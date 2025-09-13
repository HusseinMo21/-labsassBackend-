<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function visitTests()
    {
        return $this->hasMany(VisitTest::class);
    }

    public function labTests()
    {
        return $this->hasMany(LabTest::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getMainCategories()
    {
        return [
            'PATH' => 'Pathology',
            'CYTHO' => 'Cytology',
            'IHC' => 'Immunohistochemistry',
            'REV' => 'Review',
            'OTHER' => 'Other',
            'PATH+IHC' => 'Pathology + Immunohistochemistry',
        ];
    }
}