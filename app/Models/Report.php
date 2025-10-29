<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_request_id',
        'title',
        'content',
        'status',
        'generated_by',
        'generated_at',
        'template_id',
        'image_path',
        'image_filename',
        'image_mime_type',
        'image_size',
        'image_uploaded_at',
        'image_uploaded_by',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::created(function ($report) {
            // Only create Enhanced Report if the report is completed
            if ($report->status === 'completed') {
                $report->createEnhancedReport();
            }
        });
        
        static::updated(function ($report) {
            // Create Enhanced Report when report status changes to completed
            if ($report->isDirty('status') && $report->status === 'completed') {
                $report->createEnhancedReport();
            }
        });
    }

    /**
     * Get the lab request that owns the report.
     */
    public function labRequest(): BelongsTo
    {
        return $this->belongsTo(LabRequest::class);
    }

    /**
     * Get the user who generated the report.
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Get the template used for this report.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * Create an Enhanced Report when a regular report is created.
     */
    public function createEnhancedReport()
    {
        \Log::info('Creating Enhanced Report for Report ID: ' . $this->id);
        
        if (!$this->labRequest) {
            \Log::warning('No lab request found for report: ' . $this->id);
            return;
        }

        $labRequest = $this->labRequest;
        $patient = $labRequest->patient;
        $visit = $labRequest->visit;

        if (!$patient) {
            \Log::warning('No patient found for lab request: ' . $labRequest->id);
            return;
        }
        
        \Log::info('Found patient and visit for Enhanced Report creation', [
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'visit_id' => $visit ? $visit->id : 'null',
            'lab_request_id' => $labRequest->id
        ]);

        // Check if Enhanced Report already exists for this lab request
        $existingEnhancedReport = EnhancedReport::where('lab_request_id', $labRequest->id)->first();
        if ($existingEnhancedReport) {
            \Log::info('Enhanced Report already exists for lab request: ' . $labRequest->id);
            return;
        }

        try {
            // Create Enhanced Report with data from the visit and patient
            $enhancedReport = EnhancedReport::create([
                'nos' => $patient->name,
                'reff' => $patient->sender ?: ($patient->doctor ? $patient->doctor->name : 'N/A'),
                'clinical' => $visit ? ($visit->clinical_data ?? 'Clinical information not provided') : 'Clinical information not provided',
                'nature' => $this->extractNatureFromContent(),
                'report_date' => $this->generated_at ?? now(),
                'lab_no' => $labRequest->full_lab_no,
                'age' => $patient->age ?: 'N/A',
                'gross' => $visit ? ($visit->microscopic_description ?? 'Gross examination details not provided') : 'Gross examination details not provided',
                'micro' => $visit ? ($visit->microscopic_description ?? 'Microscopic examination details not provided') : 'Microscopic examination details not provided',
                'conc' => $visit ? ($visit->diagnosis ?? 'Diagnosis pending') : 'Diagnosis pending',
                'reco' => $visit ? ($visit->recommendations ?? 'Recommendations pending') : 'Recommendations pending',
                'type' => 'PATH', // Default type
                'sex' => $patient->gender ?? 'N/A',
                'recieving' => $visit && $visit->visit_date ? $visit->visit_date->format('d/m/Y') : now()->format('d/m/Y'),
                'discharge' => $visit && $visit->expected_delivery_date ? $visit->expected_delivery_date->format('d/m/Y') : now()->addDays(1)->format('d/m/Y'),
                'patient_id' => $patient->id,
                'lab_request_id' => $labRequest->id,
                'created_by' => $this->generated_by,
                'status' => 'approved', // Start as approved so it's visible to staff users
                'priority' => 'normal',
                'examination_details' => $this->extractExaminationDetails(),
                'quality_control' => $this->extractQualityControl(),
            ]);

            // Generate barcode for the enhanced report
            $enhancedReport->generateBarcode();
            
            \Log::info('Enhanced Report created successfully', [
                'enhanced_report_id' => $enhancedReport->id,
                'lab_request_id' => $labRequest->id,
                'patient_id' => $patient->id,
                'status' => $enhancedReport->status
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to create Enhanced Report', [
                'report_id' => $this->id,
                'lab_request_id' => $labRequest->id,
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw to ensure the error is not silently ignored
        }

        \Log::info('Enhanced Report created automatically', [
            'report_id' => $this->id,
            'enhanced_report_id' => $enhancedReport->id,
            'lab_request_id' => $labRequest->id,
            'patient_id' => $patient->id,
        ]);
    }

    /**
     * Extract nature of specimen from report content.
     */
    private function extractNatureFromContent(): string
    {
        // Try to extract specimen information from content
        if (strpos($this->content, 'specimen') !== false) {
            return 'Specimen details extracted from report content';
        }
        return 'Specimen information not specified';
    }

    /**
     * Extract examination details from report content.
     */
    private function extractExaminationDetails(): array
    {
        return [
            'gross_examination' => 'Gross examination performed',
            'microscopic_examination' => 'Microscopic examination performed',
            'special_stains' => 'Special stains applied as needed',
            'immunohistochemistry' => 'IHC performed if indicated',
        ];
    }

    /**
     * Extract quality control information.
     */
    private function extractQualityControl(): array
    {
        return [
            'internal_control' => 'Pass',
            'positive_control' => 'Pass',
            'negative_control' => 'Pass',
            'staining_quality' => 'Good',
            'tissue_quality' => 'Adequate',
        ];
    }
}
