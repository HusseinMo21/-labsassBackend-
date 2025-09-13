<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\BarcodeService;
use App\Models\Sample;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BarcodeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $barcodeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->barcodeService = new BarcodeService();
    }

    /** @test */
    public function it_generates_barcode_in_correct_format()
    {
        $labNo = '2025-12';
        $sampleId = 'S1';
        
        $barcode = $this->barcodeService->generateBarcode($labNo, $sampleId);
        
        $this->assertEquals('2025-12-S1', $barcode);
    }

    /** @test */
    public function it_parses_barcode_correctly()
    {
        $barcode = '2025-12-S1';
        
        $parsed = $this->barcodeService->parseBarcode($barcode);
        
        $this->assertEquals('2025-12', $parsed['lab_no']);
        $this->assertEquals('S1', $parsed['sample_id']);
    }

    /** @test */
    public function it_parses_barcode_with_multiple_dashes_in_lab_number()
    {
        $barcode = '2025-12-extra-S2';
        
        $parsed = $this->barcodeService->parseBarcode($barcode);
        
        $this->assertEquals('2025-12-extra', $parsed['lab_no']);
        $this->assertEquals('S2', $parsed['sample_id']);
    }

    /** @test */
    public function it_throws_exception_for_barcode_without_dash()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid barcode format. Expected format: LAB_NO-SAMPLE_ID');
        
        $this->barcodeService->parseBarcode('invalidbarcode');
    }

    /** @test */
    public function it_throws_exception_for_invalid_sample_id_format()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sample ID format. Expected format: S1, S2, etc.');
        
        $this->barcodeService->parseBarcode('2025-12-INVALID');
    }

    /** @test */
    public function it_validates_barcode_format()
    {
        $this->assertTrue($this->barcodeService->isValidBarcode('2025-12-S1'));
        $this->assertTrue($this->barcodeService->isValidBarcode('2025-12-extra-S2'));
        $this->assertFalse($this->barcodeService->isValidBarcode('invalid'));
        $this->assertFalse($this->barcodeService->isValidBarcode('2025-12-INVALID'));
    }

    /** @test */
    public function it_finds_sample_by_barcode()
    {
        // Create test data
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $labRequest = LabRequest::factory()->create([
            'patient_id' => $patient->id,
            'lab_no' => '2025-12'
        ]);
        $sample = Sample::factory()->create([
            'lab_request_id' => $labRequest->id,
            'barcode' => '2025-12-S1',
            'sample_id' => 'S1'
        ]);

        $foundSample = $this->barcodeService->findSampleByBarcode('2025-12-S1');
        
        $this->assertNotNull($foundSample);
        $this->assertEquals($sample->id, $foundSample->id);
        $this->assertEquals('2025-12-S1', $foundSample->barcode);
    }

    /** @test */
    public function it_returns_null_for_nonexistent_barcode()
    {
        $foundSample = $this->barcodeService->findSampleByBarcode('nonexistent-S1');
        
        $this->assertNull($foundSample);
    }

    /** @test */
    public function it_finds_lab_request_by_lab_number()
    {
        // Create test data
        $patient = Patient::factory()->create();
        $labRequest = LabRequest::factory()->create([
            'patient_id' => $patient->id,
            'lab_no' => '2025-12'
        ]);

        $foundLabRequest = $this->barcodeService->findLabRequestByLabNo('2025-12');
        
        $this->assertNotNull($foundLabRequest);
        $this->assertEquals($labRequest->id, $foundLabRequest->id);
        $this->assertEquals('2025-12', $foundLabRequest->lab_no);
    }

    /** @test */
    public function it_returns_null_for_nonexistent_lab_number()
    {
        $foundLabRequest = $this->barcodeService->findLabRequestByLabNo('nonexistent');
        
        $this->assertNull($foundLabRequest);
    }

    /** @test */
    public function it_generates_next_sample_id()
    {
        // Create test data
        $patient = Patient::factory()->create();
        $labRequest = LabRequest::factory()->create([
            'patient_id' => $patient->id,
            'lab_no' => '2025-12'
        ]);

        // No samples yet, should return S1
        $sampleId = $this->barcodeService->generateNextSampleId('2025-12');
        $this->assertEquals('S1', $sampleId);

        // Create one sample
        Sample::factory()->create([
            'lab_request_id' => $labRequest->id,
            'barcode' => '2025-12-S1',
            'sample_id' => 'S1'
        ]);

        // Should return S2
        $sampleId = $this->barcodeService->generateNextSampleId('2025-12');
        $this->assertEquals('S2', $sampleId);
    }

    /** @test */
    public function it_returns_s1_for_nonexistent_lab_request()
    {
        $sampleId = $this->barcodeService->generateNextSampleId('nonexistent');
        $this->assertEquals('S1', $sampleId);
    }

    /** @test */
    public function it_gets_comprehensive_barcode_data()
    {
        // Create test data
        $patient = Patient::factory()->create(['name' => 'Test Patient']);
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $labRequest = LabRequest::factory()->create([
            'patient_id' => $patient->id,
            'lab_no' => '2025-12'
        ]);
        $sample = Sample::factory()->create([
            'lab_request_id' => $labRequest->id,
            'barcode' => '2025-12-S1',
            'sample_id' => 'S1'
        ]);

        $data = $this->barcodeService->getBarcodeData('2025-12-S1');
        
        // Debug: print the actual data
        if (!$data['success']) {
            dump('Barcode data failed:', $data);
        }
        
        $this->assertTrue($data['success']);
        $this->assertEquals('2025-12-S1', $data['barcode']);
        $this->assertEquals('2025-12', $data['parsed']['lab_no']);
        $this->assertEquals('S1', $data['parsed']['sample_id']);
        $this->assertNotNull($data['sample']);
        $this->assertNotNull($data['lab_request']);
        $this->assertNotNull($data['patient']);
        $this->assertEquals('Test Patient', $data['patient']->name);
    }

    /** @test */
    public function it_returns_error_for_invalid_barcode_format()
    {
        $data = $this->barcodeService->getBarcodeData('invalid-barcode');
        
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Invalid sample ID format', $data['error']);
    }

    /** @test */
    public function it_returns_error_for_nonexistent_sample()
    {
        $data = $this->barcodeService->getBarcodeData('2025-12-S999');
        
        $this->assertFalse($data['success']);
        $this->assertEquals('Sample not found', $data['error']);
    }
}