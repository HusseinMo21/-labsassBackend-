<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

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

        // Must run in one transaction so SELECT ... FOR UPDATE and increment stay atomic (also safe when called outside LabNoGenerator).
        return (int) DB::transaction(function () use ($labId, $year) {
            $sequence = static::lockForUpdate()
                ->where('lab_id', $labId)
                ->where('year', $year)
                ->firstOrCreate(
                    ['lab_id' => $labId, 'year' => $year],
                    [
                        // First issued number after increment = start_sequence (e.g. 0 → first id 1, or 7000 → first 7001)
                        'last_sequence' => max(0, (int) config('lab.start_sequence', 1) - 1),
                    ]
                );

            $sequence->increment('last_sequence');
            $sequence->refresh();

            return $sequence->last_sequence;
        });
    }
}
