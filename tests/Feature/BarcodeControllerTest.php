<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\LabRequest;
use App\Models\Sample;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BarcodeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $patient;
    protected $visit;
    protected $labRequest;
    protected $sample;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->patient = Patient::factory()->create();
        $this->visit = Visit::factory()->create(['patient_id' => $this->patient->id]);
        $this->labRequest = LabRequest::factory()->create([
            'patient_id' => $this->patient->id,
            'lab_no' => '2025-12'
        ]);
        $this->sample = Sample::factory()->create([
            'lab_request_id' => $this->labRequest->id,
            'barcode' => '2025-12-S1',
            'sample_id' => 'S1'
        ]);
    }

    /** @test */
    public function it_scans_valid_barcode_successfully()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/barcode/scan', [
                'barcode' => '2025-12-S1'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'barcode' => '2025-12-S1',
                'parsed' => [
                    'lab_no' => '2025-12',
                    'sample_id' => 'S1'
                ]
            ])
            ->assertJsonStructure([
                'success',
                'barcode',
                'parsed',
                'sample',
                'lab_request',
                'patient',
                'visit'
            ]);
    }

    /** @test */
    public function it_returns_error_for_invalid_barcode_format()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/barcode/scan', [
                'barcode' => 'invalid-barcode'
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'Invalid barcode format. Expected format: LAB_NO-SAMPLE_ID'
            ]);
    }

    /** @test */
    public function it_returns_error_for_nonexistent_barcode()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/barcode/scan', [
                'barcode' => '2025-12-S999'
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'Sample not found'
            ]);
    }

    /** @test */
    public function it_validates_barcode_format()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/barcode/validate', [
                'barcode' => '2025-12-S1'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'valid' => true,
                'barcode' => '2025-12-S1'
            ]);
    }

    /** @test */
    public function it_returns_false_for_invalid_barcode_format()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/barcode/validate', [
                'barcode' => 'invalid'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'valid' => false,
                'barcode' => 'invalid'
            ]);
    }

    /** @test */
    public function it_parses_barcode_correctly()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/barcode/parse', [
                'barcode' => '2025-12-S1'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'barcode' => '2025-12-S1',
                'parsed' => [
                    'lab_no' => '2025-12',
                    'sample_id' => 'S1'
                ]
            ]);
    }

    /** @test */
    public function it_returns_error_for_invalid_parse_request()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/barcode/parse', [
                'barcode' => 'invalid'
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'Invalid barcode format. Expected format: LAB_NO-SAMPLE_ID'
            ]);
    }

    /** @test */
    public function it_gets_sample_by_barcode()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/barcode/sample?barcode=2025-12-S1');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'success',
                'sample',
                'lab_request',
                'patient',
                'visit'
            ]);
    }

    /** @test */
    public function it_returns_error_for_nonexistent_sample()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/barcode/sample?barcode=nonexistent-S1');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'Sample not found'
            ]);
    }

    /** @test */
    public function it_gets_lab_request_by_lab_number()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/barcode/lab-request?lab_no=2025-12');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'success',
                'lab_request',
                'patient',
                'visit',
                'samples'
            ]);
    }

    /** @test */
    public function it_returns_error_for_nonexistent_lab_request()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/barcode/lab-request?lab_no=nonexistent');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'Lab request not found'
            ]);
    }

    /** @test */
    public function it_generates_next_sample_id()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/barcode/next-sample-id?lab_no=2025-12');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'lab_no' => '2025-12',
                'next_sample_id' => 'S2' // S1 already exists
            ]);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->postJson('/api/barcode/scan', [
            'barcode' => '2025-12-S1'
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/barcode/scan', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['barcode']);
    }
}