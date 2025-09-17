<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'name',
        'amount',
        'date',
        'author',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the user who created the expense
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author');
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by author
     */
    public function scopeByAuthor($query, $authorId)
    {
        return $query->where('author', $authorId);
    }
}
