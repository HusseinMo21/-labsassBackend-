<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\BarcodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class BarcodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected $barcodeGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->barcodeGenerator = new BarcodeGenerator();
        
        // Use fake storage for testing
        Storage::fake('public');
    }

    /** @test */
    public function it_generates_barcode_for_lab_request()
    {
        $fullLabNo = '2025-7001';
        
        $result = $this->barcodeGenerator->generateForLabRequest($fullLabNo);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('barcode', $result);
        $this->assertArrayHasKey('qr_code', $result);
        
        // Check if files were created
        Storage::disk('public')->assertExists($result['barcode']);
        Storage::disk('public')->assertExists($result['qr_code']);
    }

    /** @test */
    public function it_generates_barcode_file()
    {
        $text = '2025-7001';
        $path = $this->barcodeGenerator->generateBarcode($text);
        
        $this->assertIsString($path);
        $this->assertStringContains('barcodes/', $path);
        $this->assertStringContains($text, $path);
        
        Storage::disk('public')->assertExists($path);
    }

    /** @test */
    public function it_generates_qr_code_file()
    {
        $text = '2025-7001';
        $path = $this->barcodeGenerator->generateQrCode($text);
        
        $this->assertIsString($path);
        $this->assertStringContains('qrcodes/', $path);
        $this->assertStringContains($text, $path);
        
        Storage::disk('public')->assertExists($path);
    }

    /** @test */
    public function it_generates_barcode_html()
    {
        $text = '2025-7001';
        $html = $this->barcodeGenerator->generateBarcodeHtml($text);
        
        $this->assertIsString($html);
        $this->assertStringContains('<svg', $html);
    }

    /** @test */
    public function it_deletes_barcode_and_qr_files()
    {
        $fullLabNo = '2025-7001';
        
        // Generate files first
        $this->barcodeGenerator->generateForLabRequest($fullLabNo);
        
        // Delete files
        $result = $this->barcodeGenerator->deleteForLabRequest($fullLabNo);
        
        $this->assertTrue($result);
        
        // Check if files were deleted
        $barcodePath = 'barcodes/' . $fullLabNo . '_barcode.png';
        $qrCodePath = 'qrcodes/' . $fullLabNo . '_qr.png';
        
        Storage::disk('public')->assertMissing($barcodePath);
        Storage::disk('public')->assertMissing($qrCodePath);
    }

    /** @test */
    public function it_gets_barcode_url()
    {
        $fullLabNo = '2025-7001';
        
        // Generate barcode first
        $this->barcodeGenerator->generateBarcode($fullLabNo);
        
        $url = $this->barcodeGenerator->getBarcodeUrl($fullLabNo);
        
        $this->assertIsString($url);
        $this->assertStringContains('storage/barcodes/', $url);
    }

    /** @test */
    public function it_gets_qr_code_url()
    {
        $fullLabNo = '2025-7001';
        
        // Generate QR code first
        $this->barcodeGenerator->generateQrCode($fullLabNo);
        
        $url = $this->barcodeGenerator->getQrCodeUrl($fullLabNo);
        
        $this->assertIsString($url);
        $this->assertStringContains('storage/qrcodes/', $url);
    }

    /** @test */
    public function it_returns_null_for_non_existent_files()
    {
        $fullLabNo = 'non-existent';
        
        $barcodeUrl = $this->barcodeGenerator->getBarcodeUrl($fullLabNo);
        $qrCodeUrl = $this->barcodeGenerator->getQrCodeUrl($fullLabNo);
        
        $this->assertNull($barcodeUrl);
        $this->assertNull($qrCodeUrl);
    }

    /** @test */
    public function it_handles_deletion_of_non_existent_files()
    {
        $fullLabNo = 'non-existent';
        
        $result = $this->barcodeGenerator->deleteForLabRequest($fullLabNo);
        
        $this->assertTrue($result);
    }
}
