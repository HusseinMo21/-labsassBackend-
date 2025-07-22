<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\LabTest;

class TestPanel extends Model
{
    use HasFactory;

    protected $table = 'test_panels';

    protected $fillable = [
        'name',
        'code',
        'description',
        'price',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function panelItems()
    {
        return $this->hasMany(TestPanelItem::class)->orderBy('sort_order');
    }

    public function labTests()
    {
        return $this->belongsToMany(LabTest::class, 'test_panel_items')
                    ->withPivot('sort_order', 'is_required')
                    ->orderBy('sort_order');
    }

    public function getTotalIndividualPrice()
    {
        return $this->labTests->sum('price');
    }

    public function getSavings()
    {
        return $this->getTotalIndividualPrice() - $this->price;
    }

    public function addTest($labTestId, $sortOrder = null, $isRequired = true)
    {
        $maxOrder = $this->panelItems()->max('sort_order') ?? 0;
        $sortOrder = $sortOrder ?? ($maxOrder + 1);

        return $this->panelItems()->create([
            'lab_test_id' => $labTestId,
            'sort_order' => $sortOrder,
            'is_required' => $isRequired,
        ]);
    }

    public function removeTest($labTestId)
    {
        return $this->panelItems()->where('lab_test_id', $labTestId)->delete();
    }

    public function reorderTests($testIds)
    {
        foreach ($testIds as $index => $testId) {
            $this->panelItems()->where('lab_test_id', $testId)->update(['sort_order' => $index + 1]);
        }
    }
} 