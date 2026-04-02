<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_id',
        'name',
        'code',
        'description',
        'is_active',
        'sort_order',
        'report_type',
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
        return $this->hasMany(LabTest::class, 'category_id');
    }

    public function lab()
    {
        return $this->belongsTo(Lab::class);
    }

    public function scopeGlobalTemplates($query)
    {
        return $query->whereNull('lab_id');
    }

    public function scopeForLab($query, int $labId)
    {
        return $query->where(function ($q) use ($labId) {
            $q->whereNull('lab_id')->orWhere('lab_id', $labId);
        });
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