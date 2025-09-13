<?php

namespace App\Services;

use App\Models\Sample;
use App\Models\LabRequest;
use App\Services\BarcodeGenerator;
use Milon\Barcode\DNS1D;
use Illuminate\Support\Facades\Log;

class BarcodeService
{
    protected $barcodeGenerator;

    public function __construct(BarcodeGenerator $barcodeGenerator)
    {
        $this->barcodeGenerator = $barcodeGenerator;
    }
    /**
     * Generate a barcode for a sample.
     *
     * @param string $labNo The lab number
     * @param string $sampleId The sample ID (S1, S2, etc.)
     * @return string The generated barcode
     */
    public function generateBarcode(string $labNo, string $sampleId): string
    {
        return $labNo . '-' . $sampleId;
    }

    /**
     * Parse a barcode to extract lab number and sample ID.
     *
     * @param string $barcode The barcode to parse
     * @return array Array containing 'lab_no' and 'sample_id'
     * @throws \InvalidArgumentException If barcode format is invalid
     */
    public function parseBarcode(string $barcode): array
    {
        // Remove any whitespace
        $barcode = trim($barcode);
        
        // Check if barcode contains a dash
        if (strpos($barcode, '-') === false) {
            throw new \InvalidArgumentException('Invalid barcode format. Expected format: LAB_NO-SAMPLE_ID');
        }
        
        // Split by the last dash (in case lab number contains dashes)
        $parts = explode('-', $barcode);
        if (count($parts) < 2) {
            throw new \InvalidArgumentException('Invalid barcode format. Expected format: LAB_NO-SAMPLE_ID');
        }
        
        $sampleId = array_pop($parts); // Get the last part as sample ID
        $labNo = implode('-', $parts); // Join remaining parts as lab number
        
        // Validate sample ID format (should start with S followed by numbers)
        if (!preg_match('/^S\d+$/', $sampleId)) {
            throw new \InvalidArgumentException('Invalid sample ID format. Expected format: S1, S2, etc.');
        }
        
        return [
            'lab_no' => $labNo,
            'sample_id' => $sampleId,
        ];
    }

    /**
     * Find a sample by its barcode.
     *
     * @param string $barcode The barcode to search for
     * @return Sample|null The sample if found, null otherwise
     */
    public function findSampleByBarcode(string $barcode): ?Sample
    {
        try {
            return Sample::where('barcode', $barcode)->first();
        } catch (\Exception $e) {
            Log::error('Error finding sample by barcode: ' . $e->getMessage(), [
                'barcode' => $barcode,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find a lab request by lab number.
     *
     * @param string $labNo The lab number to search for
     * @return LabRequest|null The lab request if found, null otherwise
     */
    public function findLabRequestByLabNo(string $labNo): ?LabRequest
    {
        try {
            return LabRequest::where('lab_no', $labNo)->first();
        } catch (\Exception $e) {
            Log::error('Error finding lab request by lab number: ' . $e->getMessage(), [
                'lab_no' => $labNo,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get comprehensive data for a barcode scan.
     *
     * @param string $barcode The barcode to scan
     * @return array Array containing sample, lab request, patient, and visit data
     */
    public function getBarcodeData(string $barcode): array
    {
        try {
            // Parse the barcode
            $parsed = $this->parseBarcode($barcode);
            
            // Find the sample
            $sample = $this->findSampleByBarcode($barcode);
            if (!$sample) {
                return [
                    'success' => false,
                    'error' => 'Sample not found',
                    'barcode' => $barcode,
                    'parsed' => $parsed
                ];
            }
            
            // Load relationships
            $sample->load(['labRequest.patient', 'labRequest.visit']);
            
            return [
                'success' => true,
                'barcode' => $barcode,
                'parsed' => $parsed,
                'sample' => $sample,
                'lab_request' => $sample->labRequest,
                'patient' => $sample->labRequest?->patient,
                'visit' => $sample->labRequest?->visit,
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'barcode' => $barcode
            ];
        } catch (\Exception $e) {
            Log::error('Error getting barcode data: ' . $e->getMessage(), [
                'barcode' => $barcode,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Internal server error',
                'barcode' => $barcode
            ];
        }
    }

    /**
     * Generate the next sample ID for a lab request.
     *
     * @param string $labNo The lab number
     * @return string The next sample ID (S1, S2, etc.)
     */
    public function generateNextSampleId(string $labNo): string
    {
        // Find the lab request
        $labRequest = $this->findLabRequestByLabNo($labNo);
        if (!$labRequest) {
            return 'S1'; // Default to S1 if lab request not found
        }
        
        // Count existing samples for this lab request
        $sampleCount = $labRequest->samples()->count();
        
        return 'S' . ($sampleCount + 1);
    }

    /**
     * Validate barcode format.
     *
     * @param string $barcode The barcode to validate
     * @return bool True if valid, false otherwise
     */
    public function isValidBarcode(string $barcode): bool
    {
        try {
            $this->parseBarcode($barcode);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Generate HTML barcode for receipt display.
     *
     * @param string $text The text to encode (lab number)
     * @param string $format The barcode format (default: CODE128)
     * @return string The HTML barcode
     */
    public function generateReceiptBarcode(string $text, string $format = 'C128'): string
    {
        try {
            $generator = new DNS1D();
            
            // Clean the text for barcode generation (remove special characters that might cause issues)
            $cleanText = preg_replace('/[^A-Za-z0-9]/', '', $text);
            if (empty($cleanText)) {
                $cleanText = str_replace(['-', '_', ' '], '', $text);
            }
            
            // Try different barcode types with milon/barcode
            $barcodeTypes = [
                'C128',  // CODE 128 - Most flexible
                'C39',   // CODE 39 - Good for alphanumeric
                'C39+',  // CODE 39 Extended
                'C93',   // CODE 93
                'I25',   // Interleaved 2 of 5
                'I25+',  // Interleaved 2 of 5 Extended
                'S25',   // Standard 2 of 5
                'S25+',  // Standard 2 of 5 Extended
            ];
            
            foreach ($barcodeTypes as $type) {
                try {
                    // Try with original text first
                    $barcode = $generator->getBarcodeSVG($text, $type, 2, 50);
                    
                    // If that fails, try with cleaned text
                    if (empty($barcode) || !str_contains($barcode, '<svg')) {
                        $barcode = $generator->getBarcodeSVG($cleanText, $type, 2, 50);
                    }
                    
                    // Check if we got a valid SVG barcode
                    if (!empty($barcode) && str_contains($barcode, '<svg')) {
                        Log::info('Successfully generated barcode with milon/barcode', [
                            'text' => $text,
                            'clean_text' => $cleanText,
                            'type' => $type,
                            'barcode_length' => strlen($barcode)
                        ]);
                        return $barcode;
                    }
                } catch (\Exception $e) {
                    Log::debug('Milon barcode generation failed for type ' . $type, [
                        'text' => $text,
                        'error' => $e->getMessage()
                    ]);
                    // Continue to next type
                    continue;
                }
            }
            
            throw new \Exception('Failed to generate barcode with any supported milon/barcode type');
            
        } catch (\Exception $e) {
            Log::error('Failed to generate receipt barcode with milon/barcode', [
                'text' => $text,
                'format' => $format,
                'error' => $e->getMessage()
            ]);
            
            // Return a more visually appealing fallback
            return $this->generateFallbackBarcode($text);
        }
    }

    /**
     * Generate a fallback barcode when the main generator fails.
     */
    private function generateFallbackBarcode(string $text): string
    {
        // Create a more realistic barcode representation using HTML/CSS
        $bars = '';
        $textLength = strlen($text);
        
        // Generate a more realistic barcode pattern
        $barPattern = [];
        for ($i = 0; $i < $textLength; $i++) {
            $char = $text[$i];
            $charCode = ord($char);
            
            // Create a pattern of bars based on character code
            $pattern = [];
            $temp = $charCode;
            for ($j = 0; $j < 4; $j++) {
                $pattern[] = ($temp % 3) + 1; // 1-3 pixels wide
                $temp = intval($temp / 3);
            }
            $barPattern = array_merge($barPattern, $pattern);
        }
        
        // Add start and stop patterns
        array_unshift($barPattern, 2, 1, 1); // Start pattern
        $barPattern = array_merge($barPattern, [1, 1, 2]); // Stop pattern
        
        // Generate the actual bars
        $x = 0;
        foreach ($barPattern as $width) {
            $bars .= '<rect x="' . $x . '" y="0" width="' . $width . '" height="40" fill="black" />';
            $x += $width;
        }
        
        // Create SVG barcode
        $svgWidth = $x + 10;
        $svgContent = '<svg width="' . $svgWidth . '" height="60" xmlns="http://www.w3.org/2000/svg">
            <g>' . $bars . '</g>
            <text x="' . ($svgWidth / 2) . '" y="55" text-anchor="middle" font-family="monospace" font-size="10" fill="black">' . htmlspecialchars($text) . '</text>
        </svg>';
        
        return '<div style="text-align:center;margin:4px 0;">' . $svgContent . '</div>';
    }

    /**
     * Generate barcode image file for lab number.
     *
     * @param string $labNo The lab number
     * @return string The file path of the generated barcode
     */
    public function generateLabNumberBarcode(string $labNo): string
    {
        try {
            return $this->barcodeGenerator->generateBarcode($labNo, 'C128');
        } catch (\Exception $e) {
            Log::error('Failed to generate lab number barcode', [
                'lab_no' => $labNo,
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception('Failed to generate barcode for lab number: ' . $e->getMessage());
        }
    }
}
