<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Template extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'clinical_data',
        'microscopic',
        'diagnosis',
        'recommendations',
        'created_by',
    ];

    /**
     * Get the user who created this template.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the reports that use this template.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Scope to get templates created by a specific user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope to get public templates (created by admins/doctors).
     */
    public function scopePublic($query)
    {
        return $query->whereHas('createdBy', function ($q) {
            $q->whereIn('role', ['admin', 'doctor']);
        });
    }
}
