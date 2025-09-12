<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LabSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'last_sequence',
    ];

    protected $casts = [
        'year' => 'integer',
        'last_sequence' => 'integer',
    ];

    /**
     * Get the next sequence number for a given year.
     */
    public static function getNextSequence(int $year): int
    {
        $sequence = static::lockForUpdate()
            ->where('year', $year)
            ->firstOrCreate(
                ['year' => $year],
                ['last_sequence' => config('lab.start_sequence', 0)]
            );
        
        $sequence->increment('last_sequence');
        return $sequence->last_sequence;
    }
}
