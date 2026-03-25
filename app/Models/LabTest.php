<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CriticalValue;
use App\Models\TestPanel;

class LabTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_id',
        'name',
        'code',
        'description',
        'price',
        'unit',
        'reference_range',
        'preparation_instructions',
        'turnaround_time_hours',
        'category_id',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function lab()
    {
        return $this->belongsTo(Lab::class);
    }

    public function category()
    {
        return $this->belongsTo(TestCategory::class, 'category_id');
    }

    public function scopePlatformMaster($query)
    {
        return $query->whereNull('lab_id');
    }

    public function scopeForLabCatalog($query, int $labId)
    {
        return $query->where(function ($q) use ($labId) {
            $q->whereNull('lab_id')->orWhere('lab_id', $labId);
        });
    }

    public function visitTests()
    {
        return $this->hasMany(VisitTest::class);
    }

    public function offerings()
    {
        return $this->hasMany(LabTestOffering::class);
    }

    public function criticalValue()
    {
        return $this->hasOne(CriticalValue::class);
    }

    public function testPanels()
    {
        return $this->belongsToMany(TestPanel::class, 'test_panel_items')
                    ->withPivot('sort_order', 'is_required');
    }

    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->price, 2);
    }

    public function getTurnaroundTimeFormattedAttribute()
    {
        if ($this->turnaround_time_hours < 24) {
            return $this->turnaround_time_hours . ' hours';
        }
        
        $days = floor($this->turnaround_time_hours / 24);
        $hours = $this->turnaround_time_hours % 24;
        
        if ($hours > 0) {
            return $days . ' days, ' . $hours . ' hours';
        }
        
        return $days . ' days';
    }
} 