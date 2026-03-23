<?php

namespace App\Services;

use App\Models\LabSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LabNoGenerator
{
    /**
     * Generate a new lab number for the given year and lab.
     *
     * @param int|null $year The year for the lab number (defaults to current year)
     * @param string|null $suffix Optional suffix to append
     * @param int|null $labId The lab ID (defaults to current lab from auth/container)
     * @return array Array containing 'base' and 'full' lab numbers
     */
    public function generate(?int $year = null, ?string $suffix = null, ?int $labId = null): array
    {
        $year = $year ?? now()->year;
        $labId = $labId ?? auth()->user()?->lab_id ?? (app()->bound('current_lab_id') ? app('current_lab_id') : 1);

        try {
            return DB::transaction(function () use ($year, $suffix, $labId) {
                // Get next sequence number with row-level locking
                $sequence = LabSequence::getNextSequence($year, $labId);
                
                // Format: {year}-{n} per lab (e.g. 2026-1, 2026-2). Sequence is scoped by lab_id + year in lab_sequences.
                $baseLabNo = sprintf('%d-%d', $year, $sequence);
                
                // Create full lab number with suffix
                $fullLabNo = $baseLabNo . ($suffix ?: '');
                
                Log::info('Generated lab number', [
                    'year' => $year,
                    'sequence' => $sequence,
                    'base_lab_no' => $baseLabNo,
                    'full_lab_no' => $fullLabNo,
                    'suffix' => $suffix
                ]);
                
                return [
                    'base' => $baseLabNo,
                    'full' => $fullLabNo,
                    'sequence' => $sequence,
                    'year' => $year,
                    'suffix' => $suffix
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to generate lab number', [
                'year' => $year,
                'suffix' => $suffix,
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception('Failed to generate lab number: ' . $e->getMessage());
        }
    }

    /**
     * Generate a lab number with suffix for staff use.
     *
     * @param string $suffix The suffix to append ('m' or 'h')
     * @param int|null $year The year for the lab number
     * @return array Array containing 'base' and 'full' lab numbers
     */
    public function generateWithSuffix(string $suffix, ?int $year = null, ?int $labId = null): array
    {
        if (!in_array($suffix, ['m', 'h'])) {
            throw new \InvalidArgumentException('Suffix must be either "m" or "h"');
        }

        return $this->generate($year, $suffix, $labId);
    }

    /**
     * Parse a lab number to extract its components.
     *
     * @param string $labNo The lab number to parse
     * @return array Array containing parsed components
     */
    public function parse(string $labNo): array
    {
        // Remove any suffix first
        $suffix = '';
        if (preg_match('/^(.+)([mh])$/', $labNo, $matches)) {
            $labNo = $matches[1];
            $suffix = $matches[2];
        }
        
        $yearInRange = static function (int $y): bool {
            return $y >= 2000 && $y <= 2100;
        };

        // New format: year-sequence (e.g. 2026-1), per lab
        if (preg_match('/^(\d{4})-(\d+)$/', $labNo, $matches)) {
            $y = (int) $matches[1];
            if ($yearInRange($y)) {
                return [
                    'year' => $y,
                    'sequence' => (int) $matches[2],
                    'base' => $labNo,
                    'suffix' => $suffix,
                    'full' => $labNo . $suffix,
                ];
            }
            // e.g. 7001-2025: first segment is not a calendar year — try legacy sequence-year below
        }

        // Legacy: sequence-year (e.g. 1-2026, 7001-2025)
        if (preg_match('/^(\d+)-(\d{4})$/', $labNo, $matches)) {
            $y = (int) $matches[2];
            if (!$yearInRange($y)) {
                throw new \InvalidArgumentException('Invalid lab number format: ' . $labNo);
            }

            return [
                'sequence' => (int) $matches[1],
                'year' => $y,
                'base' => $labNo,
                'suffix' => $suffix,
                'full' => $labNo . $suffix,
            ];
        }

        throw new \InvalidArgumentException('Invalid lab number format: ' . $labNo);
    }

    /**
     * Validate if a lab number format is correct.
     *
     * @param string $labNo The lab number to validate
     * @return bool True if valid, false otherwise
     */
    public function isValid(string $labNo): bool
    {
        try {
            $this->parse($labNo);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Get the next available sequence for a year with atomic increment.
     *
     * @param int $year The year to check
     * @return int The next sequence number
     */
    public function getNextSequenceForYear(int $year, ?int $labId = null): int
    {
        return LabSequence::getNextSequence($year, $labId);
    }

    /**
     * Preview the next lab number without consuming a sequence (best-effort; concurrent registrations may take it first).
     *
     * @return array{base: string, full: string, sequence: int, year: int}
     */
    public function preview(?int $year = null, ?int $labId = null): array
    {
        $year = (int) ($year ?? now()->year);
        $labId = $labId ?? auth()->user()?->lab_id ?? (app()->bound('current_lab_id') ? app('current_lab_id') : 1);

        $row = LabSequence::query()
            ->where('lab_id', $labId)
            ->where('year', $year)
            ->first();

        if ($row) {
            $nextSeq = (int) $row->last_sequence + 1;
        } else {
            $nextSeq = (int) config('lab.start_sequence', 1);
        }

        $baseLabNo = sprintf('%d-%d', $year, $nextSeq);

        return [
            'base' => $baseLabNo,
            'full' => $baseLabNo,
            'sequence' => $nextSeq,
            'year' => $year,
        ];
    }
}
