<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabTestCategorySetting extends Model
{
    protected $table = 'lab_test_category_settings';

    protected $fillable = [
        'lab_id',
        'test_category_id',
        'is_hidden',
        'display_name',
        'sort_order',
    ];

    protected $casts = [
        'is_hidden' => 'boolean',
    ];

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function testCategory(): BelongsTo
    {
        return $this->belongsTo(TestCategory::class, 'test_category_id');
    }
}
