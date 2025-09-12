<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Patient;
use App\Models\LabRequest;
use App\Models\Sample;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class LabRequestTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $patient;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        
        $this->patient = Patient::factory()->create([
            'name' => 'Test Patient',
            'phone' => '01234567890',
        ]);
    }

    /** @test */
    public function it_can_create_lab_request()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/lab-requests', [
                'patient_id' => $this->patient->id,
                'samples' => [
                    [
                        'tsample' => 'Blood Sample',
                        'nsample' => 'Sample 1',
                        'isample' => 'ID001',
                        'notes' => 'Test sample',
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'lab_request' => [
                    'id',
                    'patient_id',
                    'lab_no',
                    'status',
                    'samples',
                ],
            ]);

        $this->assertDatabaseHas('lab_requests', [
            'patient_id' => $this->patient->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('samples', [
            'tsample' => 'Blood Sample',
            'nsample' => 'Sample 1',
            'isample' => 'ID001',
        ]);
    }

    /** @test */
    public function it_can_list_lab_requests()
    {
        LabRequest::factory()->count(5)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/lab-requests');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'lab_no',
                        'status',
                        'patient',
                        'samples',
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_can_show_lab_request()
    {
        $labRequest = LabRequest::factory()->create([
            'patient_id' => $this->patient->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/lab-requests/{$labRequest->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'lab_request' => [
                    'id',
                    'lab_no',
                    'status',
                    'patient',
                    'samples',
                ],
            ]);
    }

    /** @test */
    public function it_can_update_lab_request_status()
    {
        $labRequest = LabRequest::factory()->create([
            'patient_id' => $this->patient->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/lab-requests/{$labRequest->id}", [
                'status' => 'completed',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('lab_requests', [
            'id' => $labRequest->id,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function it_can_update_suffix_for_staff()
    {
        $staffUser = User::factory()->create(['role' => 'staff']);
        $labRequest = LabRequest::factory()->create([
            'patient_id' => $this->patient->id,
            'suffix' => null,
        ]);

        $response = $this->actingAs($staffUser)
            ->putJson("/api/lab-requests/{$labRequest->id}/suffix", [
                'suffix' => 'm',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('lab_requests', [
            'id' => $labRequest->id,
            'suffix' => 'm',
        ]);
    }

    /** @test */
    public function it_cannot_update_suffix_for_patient()
    {
        $patientUser = User::factory()->create(['role' => 'patient']);
        $labRequest = LabRequest::factory()->create([
            'patient_id' => $this->patient->id,
        ]);

        $response = $this->actingAs($patientUser)
            ->putJson("/api/lab-requests/{$labRequest->id}/suffix", [
                'suffix' => 'm',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_search_lab_requests()
    {
        $labRequest1 = LabRequest::factory()->create(['lab_no' => '2025-7001']);
        $labRequest2 = LabRequest::factory()->create(['lab_no' => '2025-7002']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/lab-requests-search?lab_no=7001');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'lab_requests');
    }

    /** @test */
    public function it_can_get_lab_request_stats()
    {
        LabRequest::factory()->count(10)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/lab-requests-stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total',
                'by_status',
                'today',
                'this_week',
                'this_month',
            ]);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/lab-requests', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['samples']);
    }

    /** @test */
    public function it_validates_suffix_values()
    {
        $labRequest = LabRequest::factory()->create();

        $response = $this->actingAs($this->user)
            ->putJson("/api/lab-requests/{$labRequest->id}/suffix", [
                'suffix' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['suffix']);
    }

    /** @test */
    public function it_can_delete_lab_request()
    {
        $labRequest = LabRequest::factory()->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/lab-requests/{$labRequest->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('lab_requests', [
            'id' => $labRequest->id,
        ]);
    }
}
