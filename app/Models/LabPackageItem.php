<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabPackageItem extends Model
{
    protected $fillable = [
        'lab_package_id',
        'lab_test_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function labPackage(): BelongsTo
    {
        return $this->belongsTo(LabPackage::class);
    }

    public function labTest(): BelongsTo
    {
        return $this->belongsTo(LabTest::class);
    }
}
