<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\VisitTest;
use App\Models\TestValidation;
use App\Models\QualityControl;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Mpdf\Mpdf;

class EnhancedReportController extends Controller
{
    /**
     * Generate a professional pathology report with doctor hierarchy workflow
     */
    public function generateProfessionalReport(Request $request, $visitId)
    {
        try {
            $visit = Visit::with([
                'patient',
                'visitTests.labTest',
                'visitTests.testCategory',
                'labRequest'
            ])->findOrFail($visitId);

            // Check if all tests are completed
            $uncompletedTests = $visit->visitTests()
                ->where('status', '!=', 'completed')
                ->count();

            if ($uncompletedTests > 0) {
                return response()->json([
                    'message' => 'Cannot generate report. Some tests are not completed yet.',
                    'uncompleted_tests' => $uncompletedTests,
                ], 400);
            }

            // Check if user has permission to generate report
            $user = auth()->user();
            if (!$user->isAdmin() && !$user->isStaff() && !$user->isDoctor()) {
                return response()->json([
                    'message' => 'Unauthorized to generate reports',
                ], 403);
            }

            // Generate the report
            $report = $this->createReportRecord($visit, $user);
            $pdfContent = $this->generatePDFReport($visit, $report);

            Log::info('Professional report generated', [
                'visit_id' => $visitId,
                'report_id' => $report->id,
                'generated_by' => $user->id,
            ]);

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="pathology_report_' . ($visit->labRequest->lab_no ?? $visit->visit_number) . '.pdf"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate professional report', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to generate report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a report record in the database
     */
    private function createReportRecord(Visit $visit, $user)
    {
        $report = Report::create([
            'lab_request_id' => $visit->labRequest?->id,
            'title' => 'Pathology Report - ' . $visit->patient->name,
            'content' => $this->generateReportContent($visit),
            'status' => 'generated',
            'generated_by' => $user->id,
            'generated_at' => now(),
        ]);

        return $report;
    }

    /**
     * Generate the PDF report content
     */
    private function generatePDFReport(Visit $visit, Report $report)
    {
        // Configure MPDF for Arabic support with proper margins for printing
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_header' => 10,
            'margin_footer' => 10,
            'tempDir' => storage_path('app/temp'),
        ]);

        // Set font for Arabic support
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;

        $html = $this->generateReportHTML($visit, $report);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /**
     * Generate the HTML content for the report
     */
    private function generateReportHTML(Visit $visit, Report $report)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Pathology Report</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .header h1 { margin: 0; font-size: 18px; color: #333; }
                .header h2 { margin: 5px 0; font-size: 14px; color: #666; }
                .patient-info { margin-bottom: 20px; }
                .patient-info table { width: 100%; border-collapse: collapse; }
                .patient-info td { padding: 5px; border: 1px solid #ddd; }
                .patient-info .label { background-color: #f5f5f5; font-weight: bold; width: 30%; }
                .results-section { margin-top: 20px; }
                .results-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .results-table th, .results-table td { padding: 8px; border: 1px solid #ddd; text-align: left; }
                .results-table th { background-color: #f5f5f5; font-weight: bold; }
                .normal { color: #28a745; }
                .abnormal { color: #dc3545; }
                .critical { color: #dc3545; font-weight: bold; }
                .footer { margin-top: 30px; border-top: 1px solid #333; padding-top: 10px; }
                .signature-section { margin-top: 30px; }
                .signature-box { display: inline-block; width: 45%; margin: 10px; text-align: center; }
                .signature-line { border-bottom: 1px solid #333; margin-bottom: 5px; height: 20px; }
                .qc-section { margin-top: 20px; background-color: #f8f9fa; padding: 10px; border-radius: 5px; }
                .validation-section { margin-top: 20px; background-color: #e9ecef; padding: 10px; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>PATHOLOGY LABORATORY REPORT</h1>
                <h2>Professional Medical Laboratory</h2>
            </div>

            <div class="patient-info">
                <table>
                    <tr>
                        <td class="label">Patient Name:</td>
                        <td>' . htmlspecialchars($visit->patient->name) . '</td>
                        <td class="label">Lab Number:</td>
                        <td>' . ($visit->labRequest->full_lab_no ?? $visit->visit_number) . '</td>
                    </tr>
                    <tr>
                        <td class="label">Date of Birth:</td>
                        <td>' . $visit->patient->birth_date->format('Y-m-d') . '</td>
                        <td class="label">Report Date:</td>
                        <td>' . now()->format('Y-m-d H:i') . '</td>
                    </tr>
                    <tr>
                        <td class="label">Gender:</td>
                        <td>' . ucfirst($visit->patient->gender) . '</td>
                        <td class="label">Visit Date:</td>
                        <td>' . $visit->visit_date . '</td>
                    </tr>
                </table>
            </div>

            <div class="results-section">
                <h3>LABORATORY RESULTS</h3>
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Result</th>
                            <th>Reference Range</th>
                            <th>Status</th>
                            <th>Validated By</th>
                        </tr>
                    </thead>
                    <tbody>';

        foreach ($visit->visitTests as $visitTest) {
            $resultClass = $this->getResultClass($visitTest);
            $validatedBy = 'Completed';

            $html .= '
                    <tr>
                        <td>' . htmlspecialchars($visitTest->custom_test_name ?? $visitTest->labTest->name ?? 'Unknown Test') . '</td>
                        <td>' . htmlspecialchars($visitTest->result_value ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($visitTest->labTest->reference_range ?? 'N/A' ?? 'N/A') . '</td>
                        <td class="' . $resultClass . '">' . $this->getResultStatus($visitTest) . '</td>
                        <td>' . htmlspecialchars($validatedBy) . '</td>
                    </tr>';
        }

        $html .= '
                    </tbody>
                </table>
            </div>';

        // Quality Control and Validation sections removed - simplified workflow

        // Add clinical correlation and notes
        $html .= $this->generateClinicalSection($visit);

        // Add signature section
        $html .= $this->generateSignatureSection($visit);

        $html .= '
            <div class="footer">
                <p><strong>Report Generated:</strong> ' . now()->format('Y-m-d H:i:s') . '</p>
                <p><strong>Generated By:</strong> ' . auth()->user()->name . '</p>
                <p><em>This report is confidential and intended for medical use only.</em></p>
            </div>
        </body>
        </html>';

        return $html;
    }


    /**
     * Generate Clinical section
     */
    private function generateClinicalSection(Visit $visit)
    {
        $html = '
        <div class="clinical-section">
            <h3>CLINICAL CORRELATION</h3>
            <p>' . htmlspecialchars($visit->clinical_data ?? 'No clinical data provided.') . '</p>
            
            <h3>MICROSCOPIC DESCRIPTION</h3>
            <p>' . htmlspecialchars($visit->microscopic_description ?? 'No microscopic description provided.') . '</p>
            
            <h3>DIAGNOSIS</h3>
            <p>' . htmlspecialchars($visit->diagnosis ?? 'No diagnosis provided.') . '</p>
            
            <h3>RECOMMENDATIONS</h3>
            <p>' . htmlspecialchars($visit->recommendations ?? 'No recommendations provided.') . '</p>
        </div>';

        return $html;
    }

    /**
     * Generate Signature section
     */
    private function generateSignatureSection(Visit $visit)
    {
        $html = '
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line"></div>
                <p><strong>Laboratory Technician</strong></p>
                <p>Date: _______________</p>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <p><strong>Doctor Review</strong></p>
                <p>Date: _______________</p>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <p><strong>Head of Doctors (Admin)</strong></p>
                <p>Date: _______________</p>
            </div>
        </div>';

        return $html;
    }

    /**
     * Get result class for styling
     */
    private function getResultClass(VisitTest $visitTest)
    {
        if (!$visitTest->result_value) {
            return '';
        }

        // Check for critical values
        if ($visitTest->checkCriticalValue($visitTest->result_value)) {
            return 'critical';
        }

        // Check reference range
        if ($visitTest->labTest->reference_range ?? 'N/A') {
            // Simple check - can be enhanced
            return 'normal';
        }

        return '';
    }

    /**
     * Get result status
     */
    private function getResultStatus(VisitTest $visitTest)
    {
        if (!$visitTest->result_value) {
            return 'Pending';
        }

        if ($visitTest->checkCriticalValue($visitTest->result_value)) {
            return 'Critical';
        }

        return 'Normal';
    }


    /**
     * Generate report content for database storage
     */
    private function generateReportContent(Visit $visit)
    {
        $content = [
            'patient_name' => $visit->patient->name,
            'lab_number' => $visit->labRequest->full_lab_no ?? $visit->visit_number,
            'report_date' => now()->toISOString(),
            'tests' => [],
            'quality_controls' => [],
            'validations' => [],
        ];

        foreach ($visit->visitTests as $visitTest) {
            $content['tests'][] = [
                'test_name' => $visitTest->custom_test_name ?? $visitTest->labTest->name ?? 'Unknown Test',
                'result_value' => $visitTest->result_value,
                'reference_range' => $visitTest->labTest->reference_range ?? 'N/A',
                'status' => $visitTest->status,
            ];
        }

        return json_encode($content);
    }

    /**
     * Get report generation status for a visit
     */
    public function getReportStatus($visitId)
    {
        try {
            $visit = Visit::with(['visitTests.testCategory'])->findOrFail($visitId);

            $status = [
                'visit_id' => $visit->id,
                'can_generate_report' => false,
                'tests_status' => [],
                'blocking_issues' => [],
            ];

            // Check test completion status
            foreach ($visit->visitTests as $visitTest) {
                $testName = $visitTest->custom_test_name ?? $visitTest->labTest->name ?? 'Unknown Test';
                $status['tests_status'][] = [
                    'test_name' => $testName,
                    'is_completed' => $visitTest->status === 'completed',
                    'status' => $visitTest->status,
                    'completed_at' => $visitTest->status === 'completed' ? $visitTest->updated_at : null,
                ];

                if ($visitTest->status !== 'completed') {
                    $status['blocking_issues'][] = "Test '{$testName}' is not completed yet";
                }
            }

            // Determine if report can be generated
            $status['can_generate_report'] = empty($status['blocking_issues']);

            return response()->json($status);

        } catch (\Exception $e) {
            Log::error('Failed to get report status', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to get report status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all generated reports
     */
    public function listReports(Request $request)
    {
        try {
            $query = Report::with(['generatedBy', 'labRequest.patient']);

            // Filter by date range
            if ($request->has('date_from')) {
                $query->whereDate('generated_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('generated_at', '<=', $request->date_to);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $reports = $query->orderBy('generated_at', 'desc')->paginate(20);

            return response()->json($reports);

        } catch (\Exception $e) {
            Log::error('Failed to list reports', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to list reports',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
