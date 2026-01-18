<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\LabTestController;
use App\Http\Controllers\Api\VisitController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\CheckInController;
use App\Http\Controllers\Api\UnpaidInvoicesController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\LabRequestController;
use App\Http\Controllers\Api\TestCategoryController;
use App\Http\Controllers\Api\PatientRegistrationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Debug routes (remove in production)
Route::get('/debug/age-column', function () {
    try {
        $columnInfo = DB::select("SHOW COLUMNS FROM `patient` WHERE Field = 'age'");
        $latestPatient = \App\Models\Patient::latest()->first();
        
        return response()->json([
            'column_info' => $columnInfo,
            'column_type' => $columnInfo[0]->Type ?? 'unknown',
            'latest_patient' => $latestPatient ? [
                'id' => $latestPatient->id,
                'name' => $latestPatient->name,
                'age_from_model' => $latestPatient->age,
                'age_from_attributes' => $latestPatient->getAttributes()['age'] ?? null,
                'age_from_db_raw' => DB::table('patient')->where('id', $latestPatient->id)->value('age'),
                'age_type' => gettype($latestPatient->getAttributes()['age'] ?? null),
            ] : null,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::get('/debug/db', function () {
    try {
        DB::connection()->getPdo();
        return response()->json(['status' => 'Database connected successfully']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::get('/debug/patient-registration', function () {
    try {
        // Test if all required models exist
        $models = [
            'Patient' => \App\Models\Patient::class,
            'User' => \App\Models\User::class,
            'LabRequest' => \App\Models\LabRequest::class,
            'Visit' => \App\Models\Visit::class,
            'Invoice' => \App\Models\Invoice::class,
        ];
        
        $results = [];
        foreach ($models as $name => $class) {
            try {
                $count = $class::count();
                $results[$name] = "OK (count: $count)";
            } catch (\Exception $e) {
                $results[$name] = "ERROR: " . $e->getMessage();
            }
        }
        
        return response()->json(['models' => $results]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Health check routes (no authentication required)
Route::get('/health', [App\Http\Controllers\HealthCheckController::class, 'health']);
Route::get('/health/detailed', [App\Http\Controllers\HealthCheckController::class, 'detailed']);

// Public routes (no authentication required)
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/csrf-token', [AuthController::class, 'csrfToken']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);

// Public invoice PDF/preview routes (bypass default CORS middleware)
Route::group(['prefix' => 'invoices', 'middleware' => []], function () {
    Route::options('/{id}/download', [App\Http\Controllers\Api\InvoiceController::class, 'optionsInvoiceDownload']);
    Route::get('/{id}/download', [App\Http\Controllers\Api\InvoiceController::class, 'downloadInvoicePdf']);
    Route::get('/{id}/preview', [App\Http\Controllers\Api\InvoiceController::class, 'previewInvoiceHtml']);
});

// Temporary public routes for testing (remove after fixing authentication)
Route::get('/test/notifications/stats', [NotificationController::class, 'getStats']);

// Public invoice PDF/preview routes (bypass default CORS middleware)
Route::group(['prefix' => 'invoices', 'middleware' => []], function () {
    Route::options('/{id}/download', [App\Http\Controllers\Api\InvoiceController::class, 'optionsInvoiceDownload']);
    Route::get('/{id}/download', [App\Http\Controllers\Api\InvoiceController::class, 'downloadInvoicePdf']);
    Route::get('/{id}/preview', [App\Http\Controllers\Api\InvoiceController::class, 'previewInvoiceHtml']);
});

// Protected routes (authentication required)
Route::middleware(['auth:sanctum'])->group(function () {
    // Auth routes
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // Patient routes
    Route::get('/patients/search', [PatientController::class, 'search']);
    Route::get('/patients/{id}/visits', [PatientController::class, 'visits']);
    Route::get('/patients/by-test', [PatientController::class, 'patientsByTest']);
    Route::get('/patients/{id}/full-history', [PatientController::class, 'fullHistory']);
    Route::get('/patients/{id}/reports', [PatientController::class, 'reportsList']);
    Route::get('/patients/{id}/payments', [PatientController::class, 'paymentsHistory']);
    Route::post('/patients/{id}/extra-payment', [PatientController::class, 'addExtraPayment']);
    Route::middleware('pdf.cors')->group(function () {
        Route::get('/patients/{id}/print-reports', [PatientController::class, 'printAllReports'])->name('patients.print-reports');
        Route::get('/reports/{report_id}/print', [PatientController::class, 'printSingleReport'])->name('reports.print-single');
    });
    Route::apiResource('patients', PatientController::class);
    Route::get('/patient/me', [PatientController::class, 'me']);

    // Doctor routes
    Route::apiResource('doctors', DoctorController::class);
    Route::get('/doctors/{doctor}/patients', [DoctorController::class, 'patients']);
    Route::get('/doctors-search', [DoctorController::class, 'search']);

    // Organization routes
    Route::apiResource('organizations', OrganizationController::class);
    Route::get('/organizations/{organization}/patients', [OrganizationController::class, 'patients']);
    Route::get('/organizations-search', [OrganizationController::class, 'search']);

    // Lab Request routes
    Route::apiResource('lab-requests', LabRequestController::class);
    Route::put('/lab-requests/{labRequest}/suffix', [LabRequestController::class, 'updateSuffix']);
    Route::get('/lab-requests-search', [LabRequestController::class, 'search']);
    Route::get('/lab-requests-stats', [LabRequestController::class, 'getStats']);
    Route::get('/lab-requests-patient-details', [LabRequestController::class, 'getPatientDetailsByLabNo']);
    Route::get('/lab-requests/{labRequest}/visit', [LabRequestController::class, 'getVisitByLabRequest']);

    // Barcode routes
    Route::post('/barcode/scan', [App\Http\Controllers\Api\BarcodeController::class, 'scan']);
    Route::post('/barcode/parse', [App\Http\Controllers\Api\BarcodeController::class, 'parse']);
    Route::post('/barcode/validate', [App\Http\Controllers\Api\BarcodeController::class, 'validate']);
    Route::get('/barcode/sample', [App\Http\Controllers\Api\BarcodeController::class, 'getSample']);
    Route::get('/barcode/lab-request', [App\Http\Controllers\Api\BarcodeController::class, 'getLabRequest']);
    Route::get('/barcode/next-sample-id', [App\Http\Controllers\Api\BarcodeController::class, 'generateNextSampleId']);
    Route::post('/barcode/test-generate', [App\Http\Controllers\Api\BarcodeController::class, 'testGenerate']);

    // Lab test routes
    Route::apiResource('tests', LabTestController::class);
    
    // Test category routes
    Route::apiResource('test-categories', TestCategoryController::class);

    // Visit routes
    Route::get('/visits', [VisitController::class, 'index']);
    Route::post('/visits', [VisitController::class, 'store']);
    Route::get('/visits/{visit}', [VisitController::class, 'show']);
    Route::get('/visits/{id}/debug-reports', [VisitController::class, 'debugReports']);
    Route::put('/visits/{visit}', [VisitController::class, 'update']);
    Route::delete('/visits/{visit}', [VisitController::class, 'destroy']);
    Route::get('/visits/{visitId}/receipt', [VisitController::class, 'getReceiptDetails']);
    Route::put('/visits/{visit}/results', [VisitController::class, 'updateVisitResults']);
    Route::put('/visits/{visit}/tests/{visitTest}/result', [VisitController::class, 'updateTestResult']);
    Route::put('/visits/{visit}/complete', [VisitController::class, 'completeVisit']);
    Route::post('/visits/{visit}/mark-checked', [VisitController::class, 'markAsChecked']);
    Route::get('/visits/{visit}/report', [VisitController::class, 'generateReport']);
    Route::post('/visits/{visit}/upload-image', [VisitController::class, 'uploadImage']);
    Route::delete('/visits/{visit}/remove-image', [VisitController::class, 'removeImage']);
    
    // Visit Report PDF routes (with CORS support)
    Route::middleware('pdf.cors')->group(function () {
        Route::get('/visits/{visit}/report-pdf', [VisitController::class, 'generateReport']);
    });
    
    // Dashboard stats route
    Route::get('/dashboard/stats', [VisitController::class, 'getDashboardStats']);
    // Barcode endpoint for VisitTest (sample)
    Route::get('/visits/tests/{visitTest}/barcode', [VisitController::class, 'barcode']);
    // Print label for VisitTest
    Route::get('/visits/tests/{visitTest}/print-label', [VisitController::class, 'printLabel']);

    // Visit workflow (new)
    Route::get('/visits/{visitId}/tests/{testId}/print-label', [VisitController::class, 'printLabel']);

    // Debug endpoint for Enhanced Reports
    Route::get('/debug/enhanced-reports', function() {
        $enhancedReports = \App\Models\EnhancedReport::with(['patient', 'labRequest.visit'])->get();
        $reports = \App\Models\Report::with(['labRequest.patient'])->get();
        
        return response()->json([
            'enhanced_reports_count' => $enhancedReports->count(),
            'reports_count' => $reports->count(),
            'enhanced_reports' => $enhancedReports->map(function($er) {
                return [
                    'id' => $er->id,
                    'status' => $er->status,
                    'patient_name' => $er->patient ? $er->patient->name : 'No patient',
                    'lab_no' => $er->lab_no,
                    'created_at' => $er->created_at,
                    'lab_request_id' => $er->lab_request_id
                ];
            }),
            'reports' => $reports->map(function($r) {
                return [
                    'id' => $r->id,
                    'status' => $r->status,
                    'lab_request_id' => $r->lab_request_id,
                    'patient_name' => $r->labRequest && $r->labRequest->patient ? $r->labRequest->patient->name : 'No patient',
                    'created_at' => $r->created_at
                ];
            })
        ]);
    });

    // Debug endpoint for visits and test statuses
    Route::get('/debug/visits', function() {
        $visits = \App\Models\Visit::with(['patient', 'visitTests.labTest'])->get();
        $testStatuses = \App\Models\VisitTest::select('status')->distinct()->pluck('status');
        $visitStatuses = \App\Models\Visit::select('status')->distinct()->pluck('status');
        
        return response()->json([
            'total_visits' => $visits->count(),
            'visit_statuses' => $visitStatuses,
            'test_statuses' => $testStatuses,
            'visits_with_tests' => $visits->filter(function($visit) {
                return $visit->visitTests && $visit->visitTests->count() > 0;
            })->count(),
            'visits' => $visits->map(function($visit) {
                return [
                    'id' => $visit->id,
                    'visit_number' => $visit->visit_number,
                    'status' => $visit->status,
                    'patient_name' => $visit->patient ? $visit->patient->name : 'No patient',
                    'test_count' => $visit->visitTests ? $visit->visitTests->count() : 0,
                    'test_statuses' => $visit->visitTests ? $visit->visitTests->pluck('status')->unique()->values() : []
                ];
            })
        ]);
    });

    // Simple test endpoint to check basic data
    Route::get('/debug/simple', function() {
        $visitCount = \App\Models\Visit::count();
        $patientCount = \App\Models\Patient::count();
        $visitTestCount = \App\Models\VisitTest::count();
        
        return response()->json([
            'message' => 'Debug endpoint working',
            'visit_count' => $visitCount,
            'patient_count' => $patientCount,
            'visit_test_count' => $visitTestCount,
            'has_visits' => $visitCount > 0,
            'has_patients' => $patientCount > 0,
            'has_tests' => $visitTestCount > 0
        ]);
    });

    // Debug endpoint to check checked_by_doctors data
    Route::get('/debug/checked-by', function() {
        $visits = \App\Models\Visit::select('id', 'visit_number', 'checked_by_doctors', 'last_checked_at')
            ->whereNotNull('checked_by_doctors')
            ->get();
        
        $allVisits = \App\Models\Visit::select('id', 'visit_number', 'checked_by_doctors', 'last_checked_at')
            ->get();
        
        return response()->json([
            'visits_with_checked_by' => $visits,
            'all_visits_checked_by_data' => $allVisits->map(function($visit) {
                return [
                    'id' => $visit->id,
                    'visit_number' => $visit->visit_number,
                    'checked_by_doctors_raw' => $visit->getRawOriginal('checked_by_doctors'),
                    'checked_by_doctors_casted' => $visit->checked_by_doctors,
                    'last_checked_at' => $visit->last_checked_at
                ];
            })
        ]);
    });

    // Only admin, patient, and lab_tech can access pathology report PDF
    Route::middleware(['role:admin,patient,lab_tech'])->group(function () {
        Route::get('/visits/{visit}/pathology-report', [VisitController::class, 'generatePathologyReport']);
    });

    // Public invoice routes for testing
    Route::apiResource('invoices', App\Http\Controllers\Api\InvoiceController::class);
    Route::post('/invoices/{invoiceId}/payments', [App\Http\Controllers\Api\InvoiceController::class, 'addPayment']);
    Route::get('/invoices/{id}/report', [App\Http\Controllers\Api\InvoiceController::class, 'generateInvoiceReport']);
    Route::get('/invoices/stats', [App\Http\Controllers\Api\InvoiceController::class, 'getInvoiceStats']);

    // Expense routes (admin and staff only)
    Route::middleware(['role:admin,staff'])->group(function () {
        Route::get('/expenses/stats', [\App\Http\Controllers\Api\ExpenseController::class, 'stats']);
        Route::apiResource('expenses', \App\Http\Controllers\Api\ExpenseController::class);
    });

    // User management (admin only)
    Route::middleware(['role:admin'])->group(function () {
        Route::apiResource('users', UserController::class);
        Route::get('/users/stats', [UserController::class, 'getStats']);
        Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::patch('/users/{user}/change-password', [UserController::class, 'changePassword']);
    });

    // Inventory routes (admin and staff only)
    Route::middleware(['role:admin,staff'])->group(function () {
        // Specific routes first to avoid conflicts with apiResource
        Route::get('/inventory/stats', [InventoryController::class, 'getStats']);
        Route::get('/inventory/alerts', [InventoryController::class, 'getAlerts']);
        Route::get('/inventory/low-stock', [InventoryController::class, 'getLowStockItems']);
        Route::get('/inventory/expired', [InventoryController::class, 'getExpiredItems']);
        Route::patch('/inventory/{inventoryItem}/adjust-quantity', [InventoryController::class, 'adjustQuantity']);
        Route::post('/inventory/bulk-update', [InventoryController::class, 'bulkUpdate']);
        
        // Resource routes last
        Route::apiResource('inventory', InventoryController::class);
    });

    // Reports (admin and staff)
    Route::middleware(['role:admin,staff'])->group(function () {
        Route::get('/reports/revenue', [ReportController::class, 'revenue']);
        Route::get('/reports/patients', [ReportController::class, 'patients']);
        Route::get('/reports/tests', [ReportController::class, 'tests']);
        Route::get('/reports/financial', [ReportController::class, 'financial']);
        Route::get('/reports/export', [ReportController::class, 'export']);
        Route::get('/reports', [ReportController::class, 'getReports']);
    });
    
    // PDF Report generation
    Route::get('/visits/{visitId}/report/pdf', [ReportController::class, 'generateProfessionalReport']);
    Route::get('/visits/{visitId}/report/pdf/with-header', [ReportController::class, 'generateReportWithHeader']);
    Route::get('/visits/{visitId}/report/pdf/without-header', [ReportController::class, 'generateReportWithoutHeader']);
    
    // Report data saving
    Route::post('/visits/{visitId}/report', [ReportController::class, 'saveReport']);



    // Notifications routes (admin and staff only)
    Route::middleware(['role:admin,staff'])->group(function () {
        Route::apiResource('notifications', NotificationController::class);
        Route::post('/notifications/send-result', [NotificationController::class, 'sendResultNotification']);
        Route::post('/notifications/{id}/resend', [NotificationController::class, 'resendNotification']);
        Route::get('/notifications/pending', [NotificationController::class, 'getPendingNotifications']);
        Route::get('/notifications/stats', [NotificationController::class, 'getStats']);
        Route::put('/notifications/{id}/delivered', [NotificationController::class, 'markAsDelivered']);
        Route::put('/notifications/{id}/failed', [NotificationController::class, 'markAsFailed']);
    });



    // Check-in and Billing Workflow routes (admin and staff only)
    Route::middleware(['role:admin,staff'])->group(function () {
        Route::post('/check-in/register-patient', [CheckInController::class, 'registerPatient']);
        Route::post('/check-in/create-visit', [CheckInController::class, 'createVisitWithBilling']);
        Route::get('/check-in/patients/search', [CheckInController::class, 'searchPatients']);
        Route::get('/check-in/tests', [CheckInController::class, 'getAvailableTests']);
        Route::get('/check-in/test-categories', [CheckInController::class, 'getTestCategories']);
        Route::post('/check-in/calculate-billing', [CheckInController::class, 'calculateBilling']);
        Route::get('/check-in/visits/{visitId}/receipt', [CheckInController::class, 'getReceipt']);
        Route::get('/check-in/visits/{visitId}/receipt-a4', [CheckInController::class, 'generateA4Receipt']);
        Route::get('/check-in/visits/{visitId}/sample-label', [CheckInController::class, 'getSampleLabel']);
        Route::get('/check-in/visits/{visitId}/final-payment-receipt', [CheckInController::class, 'getFinalPaymentReceipt']);
    });
    

    // Patient Registration routes (admin and staff only)
    Route::middleware(['role:admin,staff'])->group(function () {
        Route::get('/patient-registration/search', [PatientRegistrationController::class, 'search']);
        Route::get('/patient-registration/next-lab-number', [PatientRegistrationController::class, 'getNextLabNumber']);
        Route::post('/patient-registration/submit', [PatientRegistrationController::class, 'submit']);
        Route::get('/patient-registration/test-categories', [PatientRegistrationController::class, 'getTestCategories']);
    });

    // Test route (no authentication required)
    Route::get('/unpaid-invoices/test', [UnpaidInvoicesController::class, 'testEndpoint']);
    
    // Temporary: Search route without authentication for debugging
    Route::get('/unpaid-invoices/search', [UnpaidInvoicesController::class, 'searchUnpaidInvoices']);

    // Unpaid Invoices and Patient Balance routes (admin and staff only)
    Route::middleware(['role:admin,staff'])->group(function () {
        Route::get('/unpaid-invoices/summary', [UnpaidInvoicesController::class, 'getUnpaidInvoicesSummary']);
        Route::get('/patients/{patientId}/balance', [UnpaidInvoicesController::class, 'getPatientBalance']);
        Route::post('/invoices/{invoiceId}/add-payment', [UnpaidInvoicesController::class, 'addPayment']);
        Route::get('/patients/{patientId}/portal-access', [UnpaidInvoicesController::class, 'checkPatientPortalAccess']);
        Route::get('/invoices/{invoiceId}/final-payment-receipt', [UnpaidInvoicesController::class, 'getFinalPaymentReceiptData']);
        
        // Visit-based payment routes (for compatibility with frontend)
        Route::post('/visits/{visitId}/add-payment', [UnpaidInvoicesController::class, 'addPayment']);
        Route::get('/visits/{visitId}/final-payment-receipt', [UnpaidInvoicesController::class, 'getFinalPaymentReceiptData']);
    });

    // Doctor routes (doctors can view and approve reports)
    Route::middleware(['role:doctor'])->group(function () {
        Route::get('/doctor/reports', [ReportController::class, 'doctorReports']);
        Route::get('/doctor/reports/{reportId}', [ReportController::class, 'getDoctorReport']);
        Route::put('/doctor/reports/{reportId}/approve', [ReportController::class, 'approveReport']);
        Route::put('/doctor/reports/{reportId}/fill-data', [ReportController::class, 'fillReportData']);
    });

    // Lab Insights routes (admin and staff only)
    Route::middleware(['role:admin,staff'])->group(function () {
        Route::get('/lab-insights', [App\Http\Controllers\Api\LabInsightsController::class, 'getInsights']);
    });

    // Professional report generation (accessible by admin, staff, doctor)
    Route::middleware(['role:admin,staff,doctor'])->group(function () {
        Route::get('/reports/professional/{visitId}', [ReportController::class, 'generateProfessionalReport']);
    });

    // Patient routes (patients can view their own reports after payment)
    Route::middleware(['role:patient'])->group(function () {
        Route::get('/patient/my-reports', [PatientController::class, 'myReports']);
        Route::get('/patient/my-reports/{reportId}', [PatientController::class, 'getMyReport']);
        Route::get('/patient/my-visits', [PatientController::class, 'myVisits']);
        Route::get('/patient/my-invoices', [PatientController::class, 'myInvoices']);
        Route::middleware('pdf.cors')->group(function () {
            Route::get('/patient/my-reports/{reportId}/print', [PatientController::class, 'printMyReport']);
        });
    });

    // Shift Management routes (staff and admin)
    Route::middleware(['role:staff,admin'])->group(function () {
        Route::get('/shifts/current', [App\Http\Controllers\Api\ShiftController::class, 'getCurrentShift']);
        Route::post('/shifts/open', [App\Http\Controllers\Api\ShiftController::class, 'openShift']);
        Route::post('/shifts/close', [App\Http\Controllers\Api\ShiftController::class, 'closeShift']);
        Route::get('/shifts/history', [App\Http\Controllers\Api\ShiftController::class, 'getShiftHistory']);
        Route::get('/shifts/by-date', [App\Http\Controllers\Api\ShiftController::class, 'getShiftsByDate']);
        Route::get('/shifts/{shiftId}/report', [App\Http\Controllers\Api\ShiftController::class, 'getShiftReport']);
    });

    // Accounts Management routes (admin and staff only)
    Route::middleware(['role:admin,staff'])->group(function () {
        Route::apiResource('accounts', App\Http\Controllers\Api\AccountController::class);
        Route::post('/accounts/{account}/transactions', [App\Http\Controllers\Api\AccountController::class, 'addTransaction']);
        Route::post('/accounts/{account}/payments', [App\Http\Controllers\Api\AccountController::class, 'addPayment']);
        Route::post('/accounts/{account}/mark-completed', [App\Http\Controllers\Api\AccountController::class, 'markCompleted']);
        Route::get('/accounts-summary', [App\Http\Controllers\Api\AccountController::class, 'getSummary']);
    });



    // Enhanced Report Generation routes
    Route::middleware(['role:admin,staff,doctor'])->group(function () {
        Route::get('/enhanced-reports/professional/{visitId}', [App\Http\Controllers\Api\EnhancedReportController::class, 'generateProfessionalReport']);
        Route::get('/enhanced-reports/status/{visitId}', [App\Http\Controllers\Api\EnhancedReportController::class, 'getReportStatus']);
        Route::get('/reports/list', [App\Http\Controllers\Api\EnhancedReportController::class, 'listReports']); // Changed route to avoid conflict
    });

    // Enhanced Reports API routes (new report system)
    Route::middleware(['role:admin,staff,doctor'])->group(function () {
        Route::apiResource('enhanced-reports', App\Http\Controllers\Api\EnhancedReportApiController::class);
        Route::post('/enhanced-reports/{report}/submit-review', [App\Http\Controllers\Api\EnhancedReportApiController::class, 'submitForReview']);
        Route::post('/enhanced-reports/{report}/approve', [App\Http\Controllers\Api\EnhancedReportApiController::class, 'approve']);
        Route::post('/enhanced-reports/{report}/deliver', [App\Http\Controllers\Api\EnhancedReportApiController::class, 'deliver']);
        Route::post('/enhanced-reports/{report}/send-to-patient', [App\Http\Controllers\Api\EnhancedReportApiController::class, 'sendToPatient']);
        Route::get('/enhanced-reports-statistics', [App\Http\Controllers\Api\EnhancedReportApiController::class, 'statistics']);
        Route::post('/enhanced-reports/{report}/upload-image', [App\Http\Controllers\Api\EnhancedReportApiController::class, 'uploadImage']);
        Route::delete('/enhanced-reports/{report}/remove-image', [App\Http\Controllers\Api\EnhancedReportApiController::class, 'removeImage']);
        Route::get('/reports/search', [App\Http\Controllers\Api\EnhancedReportApiController::class, 'search']);
    });

    // Enhanced Reports PDF routes (with CORS support)
    Route::middleware(['role:admin,staff,doctor'])->group(function () {
        Route::middleware('pdf.cors')->group(function () {
            Route::get('/enhanced-reports/{report}/print', [App\Http\Controllers\Api\EnhancedReportApiController::class, 'print'])->name('enhanced-reports.print');
            Route::get('/enhanced-reports/{report}/print-view', [App\Http\Controllers\Api\EnhancedReportApiController::class, 'printView'])->name('enhanced-reports.print-view');
        });
    });

    // Template routes (admin, staff, and doctor can manage templates)
    Route::middleware(['role:admin,staff,doctor'])->group(function () {
        Route::apiResource('templates', App\Http\Controllers\Api\TemplateController::class);
        Route::post('/templates/from-report', [App\Http\Controllers\Api\TemplateController::class, 'createFromReport']);
    });


    // Admin Review Reports routes (Head of Doctors - Final Approval)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/admin/review-reports', [App\Http\Controllers\Api\ReviewReportsController::class, 'index']);
        Route::get('/admin/review-reports/{visitId}', [App\Http\Controllers\Api\ReviewReportsController::class, 'show']);
        Route::post('/admin/review-reports/{visitId}/approve', [App\Http\Controllers\Api\ReviewReportsController::class, 'approve']);
        Route::post('/admin/review-reports/{visitId}/reject', [App\Http\Controllers\Api\ReviewReportsController::class, 'reject']);
        Route::get('/admin/review-reports/pending', [App\Http\Controllers\Api\ReviewReportsController::class, 'pending']);
    });
}); 