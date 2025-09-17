<?php

namespace App\Services;

use App\Models\LabSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LabNoGenerator
{
    /**
     * Generate a new lab number for the given year.
     *
     * @param int|null $year The year for the lab number (defaults to current year)
     * @param string|null $suffix Optional suffix to append
     * @return array Array containing 'base' and 'full' lab numbers
     */
    public function generate(?int $year = null, ?string $suffix = null): array
    {
        $year = $year ?? now()->year;
        
        try {
            return DB::transaction(function () use ($year, $suffix) {
                // Get next sequence number with row-level locking
                $sequence = LabSequence::getNextSequence($year);
                
                // Format the base lab number (sequence-year format to match existing data)
                $baseLabNo = sprintf('%d-%d', $sequence, $year);
                
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
    public function generateWithSuffix(string $suffix, ?int $year = null): array
    {
        if (!in_array($suffix, ['m', 'h'])) {
            throw new \InvalidArgumentException('Suffix must be either "m" or "h"');
        }
        
        return $this->generate($year, $suffix);
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
        
        // Parse sequence and year (sequence-year format)
        if (preg_match('/^(\d+)-(\d{4})$/', $labNo, $matches)) {
            return [
                'sequence' => (int) $matches[1],
                'year' => (int) $matches[2],
                'base' => $labNo,
                'suffix' => $suffix,
                'full' => $labNo . $suffix
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
    public function getNextSequenceForYear(int $year): int
    {
        return LabSequence::getNextSequence($year);
    }
}
