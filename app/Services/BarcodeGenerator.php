<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorHTML;

class BarcodeGenerator
{
    /**
     * Generate barcode and QR code for a lab request.
     *
     * @param string $fullLabNo The full lab number
     * @return array Array containing the generated file paths
     */
    public function generateForLabRequest(string $fullLabNo): array
    {
        try {
            $barcodePath = $this->generateBarcode($fullLabNo);
            $qrCodePath = $this->generateQrCode($fullLabNo);
            
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
     * @return string The file path of the generated barcode
     */
    public function generateBarcode(string $text, string $format = 'CODE128'): string
    {
        try {
            $generator = new BarcodeGeneratorPNG();
            
            // Generate barcode image
            $barcodeData = $generator->getBarcode($text, $format, 2, 50);
            
            // Create filename
            $filename = $text . '_barcode.png';
            $path = 'barcodes/' . $filename;
            
            // Store the barcode
            Storage::disk('public')->put($path, $barcodeData);
            
            return $path;
        } catch (\Exception $e) {
            Log::error('Barcode generation failed', [
                'text' => $text,
                'format' => $format,
                'error' => $e->getMessage()
            ]);
            
            // Return a placeholder path if barcode generation fails
            return 'barcodes/' . $text . '_barcode.png';
        }
    }

    /**
     * Generate a QR code for the given text.
     *
     * @param string $text The text to encode
     * @param int $size The size of the QR code (default: 200)
     * @return string The file path of the generated QR code
     */
    public function generateQrCode(string $text, int $size = 200): string
    {
        try {
            // Generate QR code
            $qrCodeData = QrCode::format('png')
                ->size($size)
                ->margin(1)
                ->generate($text);
            
            // Create filename
            $filename = $text . '_qr.png';
            $path = 'qrcodes/' . $filename;
            
            // Store the QR code
            Storage::disk('public')->put($path, $qrCodeData);
            
            return $path;
        } catch (\Exception $e) {
            Log::error('QR code generation failed', [
                'text' => $text,
                'error' => $e->getMessage()
            ]);
            
            // Return a placeholder path if QR generation fails
            return 'qrcodes/' . $text . '_qr.png';
        }
    }

    /**
     * Generate barcode HTML for inline display.
     *
     * @param string $text The text to encode
     * @param string $format The barcode format
     * @return string The HTML barcode
     */
    public function generateBarcodeHtml(string $text, string $format = 'CODE128'): string
    {
        $generator = new BarcodeGeneratorHTML();
        return $generator->getBarcode($text, $format, 2, 50);
    }

    /**
     * Delete barcode and QR code files for a lab request.
     *
     * @param string $fullLabNo The full lab number
     * @return bool True if files were deleted successfully
     */
    public function deleteForLabRequest(string $fullLabNo): bool
    {
        try {
            $barcodePath = 'barcodes/' . $fullLabNo . '_barcode.png';
            $qrCodePath = 'qrcodes/' . $fullLabNo . '_qr.png';
            
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
    public function getBarcodeUrl(string $fullLabNo): ?string
    {
        $path = 'barcodes/' . $fullLabNo . '_barcode.png';
        
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
    public function getQrCodeUrl(string $fullLabNo): ?string
    {
        $path = 'qrcodes/' . $fullLabNo . '_qr.png';
        
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }
        
        return null;
    }
}
