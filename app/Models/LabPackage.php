<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabPackage extends Model
{
    protected $fillable = [
        'lab_id',
        'name',
        'code',
        'description',
        'package_price',
        'discount_percent',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'package_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
    ];

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabPackageItem::class);
    }

    public function scopeActiveForLab($query, int $labId)
    {
        $today = now()->toDateString();

        return $query->where('lab_id', $labId)
            ->where('is_active', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today);
            });
    }
}
