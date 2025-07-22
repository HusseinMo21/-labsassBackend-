<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\LabTest;

class TestPanelItem extends Model
{
    use HasFactory;

    protected $table = 'test_panel_items';

    protected $fillable = [
        'test_panel_id',
        'lab_test_id',
        'sort_order',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function testPanel()
    {
        return $this->belongsTo(TestPanel::class);
    }

    public function labTest()
    {
        return $this->belongsTo(LabTest::class);
    }
} 