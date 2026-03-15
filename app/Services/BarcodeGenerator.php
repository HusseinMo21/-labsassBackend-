<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Milon\Barcode\DNS1D;
use Milon\Barcode\DNS2D;

class BarcodeGenerator
{
    /**
     * Generate barcode and QR code for a lab request.
     *
     * @param string $fullLabNo The full lab number
     * @param int|null $labId The lab ID for storage path (storage/labs/{lab_id}/barcodes/)
     * @return array Array containing the generated file paths
     */
    public function generateForLabRequest(string $fullLabNo, ?int $labId = null): array
    {
        try {
            $labId = $labId ?? auth()->user()?->lab_id ?? (app()->bound('current_lab_id') ? app('current_lab_id') : 1);
            $barcodePath = $this->generateBarcode($fullLabNo, 'C128', $labId);
            $qrCodePath = $this->generateQrCode($fullLabNo, 200, $labId);
            
            Log::info('Generated barcode and QR code for lab request', [
                'full_lab_no' => $fullLabNo,
                'barcode_path' => $barcodePath,
                'qr_code_path' => $qrCodePath
            ]);
            
            return [
                'barcode' => $barcodePath,
                'qr_code' => $qrCodePath
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate barcode/QR code', [
                'full_lab_no' => $fullLabNo,
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception('Failed to generate barcode/QR code: ' . $e->getMessage());
        }
    }

    /**
     * Generate a barcode for the given text.
     *
     * @param string $text The text to encode
     * @param string $format The barcode format (default: CODE128)
     * @param int|null $labId The lab ID for storage path
     * @return string The file path of the generated barcode
     */
    public function generateBarcode(string $text, string $format = 'C128', ?int $labId = null): string
    {
        $labId = $labId ?? auth()->user()?->lab_id ?? (app()->bound('current_lab_id') ? app('current_lab_id') : 1);

        try {
            $barcode = new DNS1D();
            
            // Clean the text for barcode generation
            $cleanText = preg_replace('/[^A-Za-z0-9]/', '', $text);
            if (empty($cleanText)) {
                $cleanText = str_replace(['-', '_', ' '], '', $text);
            }
            
            // Try different formats and parameters to get a complete barcode
            $formats = ['C128', 'C39', 'C39+', 'C93', 'I25', 'I25+', 'S25', 'S25+'];
            
            $barcodeData = null;
            $usedFormat = null;
            
            foreach ($formats as $testFormat) {
                try {
                    // Try with original text first
                    $testData = $barcode->getBarcodeSVG($text, $testFormat, 2, 50);
                    
                    // If that fails, try with cleaned text
                    if (empty($testData) || !str_contains($testData, '<svg')) {
                        $testData = $barcode->getBarcodeSVG($cleanText, $testFormat, 2, 50);
                    }
                    
                    if (!empty($testData) && str_contains($testData, '<svg')) {
                        // Check if the barcode has reasonable width (more than 50 pixels)
                        if (preg_match('/width="(\d+)"/', $testData, $matches)) {
                            $width = (int)$matches[1];
                            if ($width > 50) { // Reasonable width for a barcode
                                $barcodeData = $testData;
                                $usedFormat = $testFormat;
                                break;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Continue to next format
                    continue;
                }
            }
            
            if (!$barcodeData) {
                throw new \Exception('Failed to generate valid barcode with any format');
            }
            
            // Create filename with SVG extension - use lab-prefixed path for multi-tenant
            $filename = $text . '_barcode.svg';
            $basePath = $labId ? "labs/{$labId}/barcodes" : 'barcodes';
            $path = $basePath . '/' . $filename;

            // Store the barcode
            Storage::disk('public')->put($path, $barcodeData);
            
            Log::info('Barcode generated successfully with milon/barcode', [
                'text' => $text,
                'clean_text' => $cleanText,
                'format' => $usedFormat,
                'path' => $path,
                'data_length' => strlen($barcodeData)
            ]);
            
            return $path;
        } catch (\Exception $e) {
            Log::error('Milon barcode generation failed', [
                'text' => $text,
                'format' => $format,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to simple SVG barcode
            return $this->generateSimpleSvgBarcode($text, $labId);
        }
    }

    /**
     * Generate a simple SVG barcode as fallback.
     */
    private function generateSimpleSvgBarcode(string $text, ?int $labId = null): string
    {
        // Create a simple SVG barcode representation
        $bars = '';
        $textLength = strlen($text);
        $x = 0;
        
        // Generate bars based on text characters
        for ($i = 0; $i < $textLength; $i++) {
            $char = $text[$i];
            $charCode = ord($char);
            
            // Create bars based on character code
            $barWidth = ($charCode % 4) + 1; // 1-4 pixels wide
            $bars .= '<rect x="' . $x . '" y="0" width="' . $barWidth . '" height="38" fill="black" />';
            $x += $barWidth + 1;
        }
        
        $svgContent = '<?xml version="1.0" standalone="no"?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
<svg width="' . ($x + 10) . '" height="50" version="1.1" xmlns="http://www.w3.org/2000/svg">
    <g id="bars" fill="black" stroke="none">
        ' . $bars . '
        <text x="' . (($x + 10) / 2) . '" text-anchor="middle" y="50" id="code" fill="black" font-size="12px">' . htmlspecialchars($text) . '</text>
    </g>
</svg>';
        
        // Save as SVG file - use lab-prefixed path for multi-tenant
        $filename = $text . '_barcode.svg';
        $basePath = $labId ? "labs/{$labId}/barcodes" : 'barcodes';
        $path = $basePath . '/' . $filename;
        Storage::disk('public')->put($path, $svgContent);
        
        Log::info('Simple SVG barcode generated as fallback', [
            'text' => $text,
            'path' => $path
        ]);
        
        return $path;
    }

    /**
     * Generate a simple HTML barcode representation as fallback.
     */
    private function generateSimpleBarcode(string $text): string
    {
        // Create a simple barcode-like representation using HTML/CSS
        $bars = '';
        $textLength = strlen($text);
        
        // Generate bars based on text characters
        for ($i = 0; $i < $textLength; $i++) {
            $char = $text[$i];
            $charCode = ord($char);
            
            // Create bars based on character code
            $barWidth = ($charCode % 4) + 1; // 1-4 pixels wide
            $bars .= '<div style="display:inline-block;width:' . $barWidth . 'px;height:50px;background-color:black;margin-right:1px;"></div>';
        }
        
        $barcodeHtml = '<div style="font-family:monospace;font-size:12px;text-align:center;padding:5px;border:1px solid #ccc;background:white;">
                    <div style="margin-bottom:5px;">' . htmlspecialchars($text) . '</div>
                    <div style="margin:5px 0;">' . $bars . '</div>
                    <div style="font-size:10px;color:#666;">BARCODE</div>
                </div>';
        
        // Save as HTML file
        $filename = $text . '_barcode.html';
        $path = 'barcodes/' . $filename;
        Storage::disk('public')->put($path, $barcodeHtml);
        
        return $path;
    }

    /**
     * Generate a QR code for the given text.
     *
     * @param string $text The text to encode
     * @param int $size The size of the QR code (default: 200)
     * @param int|null $labId The lab ID for storage path
     * @return string The file path of the generated QR code
     */
    public function generateQrCode(string $text, int $size = 200, ?int $labId = null): string
    {
        $labId = $labId ?? auth()->user()?->lab_id ?? (app()->bound('current_lab_id') ? app('current_lab_id') : 1);

        try {
            $qrCode = new DNS2D();
            
            // Generate QR code as SVG
            $qrCodeData = $qrCode->getBarcodeSVG($text, 'QRCODE', $size/10, $size/10);
            
            // Create filename with SVG extension - use lab-prefixed path for multi-tenant
            $filename = $text . '_qr.svg';
            $basePath = $labId ? "labs/{$labId}/qrcodes" : 'qrcodes';
            $path = $basePath . '/' . $filename;

            // Store the QR code
            Storage::disk('public')->put($path, $qrCodeData);

            return $path;
        } catch (\Exception $e) {
            Log::error('QR code generation failed', [
                'text' => $text,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to SimpleSoftwareIO QR code
            try {
                $qrCodeData = QrCode::format('svg')
                    ->size($size)
                    ->margin(1)
                    ->generate($text);

                $filename = $text . '_qr.svg';
                $basePath = $labId ? "labs/{$labId}/qrcodes" : 'qrcodes';
                $path = $basePath . '/' . $filename;
                Storage::disk('public')->put($path, $qrCodeData);
                
                return $path;
            } catch (\Exception $e2) {
                Log::error('QR code fallback generation failed', [
                    'text' => $text,
                    'error' => $e2->getMessage()
                ]);
                
                $basePath = $labId ? "labs/{$labId}/qrcodes" : 'qrcodes';
                return $basePath . '/' . $text . '_qr.svg';
            }
        }
    }

    /**
     * Generate barcode HTML for inline display.
     *
     * @param string $text The text to encode
     * @param string $format The barcode format
     * @return string The HTML barcode
     */
    public function generateBarcodeHtml(string $text, string $format = 'C128'): string
    {
        try {
            $generator = new DNS1D();
            return $generator->getBarcodeSVG($text, $format, 2, 50);
        } catch (\Exception $e) {
            Log::error('Failed to generate barcode HTML with milon/barcode', [
                'text' => $text,
                'format' => $format,
                'error' => $e->getMessage()
            ]);
            
            // Return a simple fallback
            return '<div style="font-family: monospace; font-size: 12px; text-align: center; padding: 5px; border: 1px solid #ccc;">' . htmlspecialchars($text) . '</div>';
        }
    }

    /**
     * Delete barcode and QR code files for a lab request.
     *
     * @param string $fullLabNo The full lab number
     * @return bool True if files were deleted successfully
     */
    public function deleteForLabRequest(string $fullLabNo, ?int $labId = null): bool
    {
        try {
            $labId = $labId ?? auth()->user()?->lab_id ?? (app()->bound('current_lab_id') ? app('current_lab_id') : 1);
            $baseBarcode = $labId ? "labs/{$labId}/barcodes" : 'barcodes';
            $baseQr = $labId ? "labs/{$labId}/qrcodes" : 'qrcodes';
            $barcodePath = $baseBarcode . '/' . $fullLabNo . '_barcode.svg';
            $qrCodePath = $baseQr . '/' . $fullLabNo . '_qr.svg';
            
            $deleted = true;
            
            if (Storage::disk('public')->exists($barcodePath)) {
                $deleted = $deleted && Storage::disk('public')->delete($barcodePath);
            }
            
            if (Storage::disk('public')->exists($qrCodePath)) {
                $deleted = $deleted && Storage::disk('public')->delete($qrCodePath);
            }
            
            Log::info('Deleted barcode and QR code files', [
                'full_lab_no' => $fullLabNo,
                'barcode_path' => $barcodePath,
                'qr_code_path' => $qrCodePath,
                'deleted' => $deleted
            ]);
            
            return $deleted;
        } catch (\Exception $e) {
            Log::error('Failed to delete barcode/QR code files', [
                'full_lab_no' => $fullLabNo,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Get the URL for a barcode file.
     *
     * @param string $fullLabNo The full lab number
     * @return string|null The URL or null if file doesn't exist
     */
    public function getBarcodeUrl(string $fullLabNo, ?int $labId = null): ?string
    {
        $labId = $labId ?? auth()->user()?->lab_id ?? (app()->bound('current_lab_id') ? app('current_lab_id') : 1);
        $basePath = $labId ? "labs/{$labId}/barcodes" : 'barcodes';
        $path = $basePath . '/' . $fullLabNo . '_barcode.svg';
        
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }
        
        return null;
    }

    /**
     * Get the URL for a QR code file.
     *
     * @param string $fullLabNo The full lab number
     * @return string|null The URL or null if file doesn't exist
     */
    public function getQrCodeUrl(string $fullLabNo, ?int $labId = null): ?string
    {
        $labId = $labId ?? auth()->user()?->lab_id ?? (app()->bound('current_lab_id') ? app('current_lab_id') : 1);
        $basePath = $labId ? "labs/{$labId}/qrcodes" : 'qrcodes';
        $path = $basePath . '/' . $fullLabNo . '_qr.svg';
        
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }
        
        return null;
    }
}
