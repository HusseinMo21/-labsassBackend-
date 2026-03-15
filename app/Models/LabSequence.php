<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LabSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_id',
        'year',
        'last_sequence',
    ];

    protected $casts = [
        'year' => 'integer',
        'last_sequence' => 'integer',
    ];

    public function lab()
    {
        return $this->belongsTo(Lab::class);
    }

    /**
     * Get the next sequence number for a given lab and year.
     */
    public static function getNextSequence(int $year, ?int $labId = null): int
    {
        $labId = $labId ?? auth()->user()?->lab_id ?? (app()->bound('current_lab_id') ? app('current_lab_id') : 1);

        $sequence = static::lockForUpdate()
            ->where('lab_id', $labId)
            ->where('year', $year)
            ->firstOrCreate(
                ['lab_id' => $labId, 'year' => $year],
                ['last_sequence' => config('lab.start_sequence', 0)]
            );

        $sequence->increment('last_sequence');
        return $sequence->last_sequence;
    }
}
