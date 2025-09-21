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
use App\Http\Controllers\Api\SampleTrackingController;
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
Route::get('/test/sample-tracking/stats', [SampleTrackingController::class, 'getStats']);
Route::get('/test/notifications/stats', [NotificationController::class, 'getStats']);

// Public invoice PDF/preview routes (bypass default CORS middleware)
Route::group(['prefix' => 'invoices', 'middleware' => []], function () {
    Route::options('/{id}/download', [App\Http\Controllers\Api\InvoiceController::class, 'optionsInvoiceDownload']);
    Route::get('/{id}/download', [App\Http\Controllers\Api\InvoiceController::class, 'downloadInvoicePdf']);
    Route::get('/{id}/preview', [App\Http\Controllers\Api\InvoiceController::class, 'previewInvoiceHtml']);
});

// Protected routes (authentication and CSRF protection required)
Route::middleware(['auth:sanctum', 'api.csrf'])->group(function () {
    // Auth routes
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // Patient routes
    Route::get('/patients/by-test', [PatientController::class, 'patientsByTest']);
    Route::get('/patients/{id}/full-history', [PatientController::class, 'fullHistory']);
    Route::get('/patients/{id}/reports', [PatientController::class, 'reportsList']);
    Route::get('/patients/{id}/payments', [PatientController::class, 'paymentsHistory']);
    Route::middleware('pdf.cors')->group(function () {
        Route::get('/patients/{id}/print-reports', [PatientController::class, 'printAllReports']);
        Route::get('/reports/{report_id}/print', [PatientController::class, 'printSingleReport']);
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
    Route::put('/visits/{visit}', [VisitController::class, 'update']);
    Route::delete('/visits/{visit}', [VisitController::class, 'destroy']);
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
    });
    
    // PDF Report generation
    Route::get('/visits/{visitId}/report/pdf', [ReportController::class, 'generateProfessionalReport']);
    Route::get('/visits/{visitId}/report/pdf/with-header', [ReportController::class, 'generateReportWithHeader']);
    Route::get('/visits/{visitId}/report/pdf/without-header', [ReportController::class, 'generateReportWithoutHeader']);
    
    // Report data saving
    Route::post('/visits/{visitId}/report', [ReportController::class, 'saveReport']);

    // Sample Tracking routes (admin and staff only)
    Route::middleware(['role:admin,staff'])->group(function () {
        // Specific routes must come before resource routes to avoid conflicts
        Route::get('/sample-tracking/stats', [SampleTrackingController::class, 'getStats']);
        Route::post('/sample-tracking/create/{labRequestId}', [SampleTrackingController::class, 'createSample']);
        Route::put('/sample-tracking/{id}/status', [SampleTrackingController::class, 'updateStatus']);
        Route::get('/sample-tracking/lab-request/{labRequestId}', [SampleTrackingController::class, 'getSampleByLabRequest']);
        
        // Resource routes come last
        Route::apiResource('sample-tracking', SampleTrackingController::class);
    });


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
        Route::get('/check-in/visits/{visitId}/sample-label', [CheckInController::class, 'getSampleLabel']);
        Route::get('/check-in/visits/{visitId}/final-payment-receipt', [CheckInController::class, 'getFinalPaymentReceipt']);
    });

    // Patient Registration routes
    Route::middleware(['auth:api', 'role:admin,staff'])->group(function () {
        Route::get('/patient-registration/search', [PatientRegistrationController::class, 'search']);
        Route::get('/patient-registration/next-lab-number', [PatientRegistrationController::class, 'getNextLabNumber']);
        Route::post('/patient-registration/submit', [PatientRegistrationController::class, 'submit']);
        Route::get('/patient-registration/test-categories', [PatientRegistrationController::class, 'getTestCategories']);
    });

    // Unpaid Invoices and Patient Balance routes (admin and staff only)
    Route::middleware(['role:admin,staff'])->group(function () {
        Route::get('/unpaid-invoices/search', [UnpaidInvoicesController::class, 'searchUnpaidInvoices']);
        Route::get('/unpaid-invoices/summary', [UnpaidInvoicesController::class, 'getUnpaidInvoicesSummary']);
        Route::get('/patients/{patientId}/balance', [UnpaidInvoicesController::class, 'getPatientBalance']);
        Route::post('/invoices/{invoiceId}/add-payment', [UnpaidInvoicesController::class, 'addPayment']);
        Route::get('/patients/{patientId}/portal-access', [UnpaidInvoicesController::class, 'checkPatientPortalAccess']);
        Route::get('/invoices/{invoiceId}/final-payment-receipt', [UnpaidInvoicesController::class, 'getFinalPaymentReceiptData']);
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
    });



    // Enhanced Report Generation routes
    Route::middleware(['role:admin,staff,doctor'])->group(function () {
        Route::get('/enhanced-reports/professional/{visitId}', [App\Http\Controllers\Api\EnhancedReportController::class, 'generateProfessionalReport']);
        Route::get('/enhanced-reports/status/{visitId}', [App\Http\Controllers\Api\EnhancedReportController::class, 'getReportStatus']);
        Route::get('/enhanced-reports', [App\Http\Controllers\Api\EnhancedReportController::class, 'listReports']);
    });

    // Enhanced Reports API routes (new report system)
    Route::middleware(['role:admin,staff,doctor'])->group(function () {
        Route::apiResource('enhanced-reports', App\Http\Controllers\Api\EnhancedReportApiController::class);
        Route::post('/enhanced-reports/{report}/submit-review', [App\Http\Controllers\Api\EnhancedReportApiController::class, 'submitForReview']);
        Route::post('/enhanced-reports/{report}/approve', [App\Http\Controllers\Api\EnhancedReportApiController::class, 'approve']);
        Route::post('/enhanced-reports/{report}/deliver', [App\Http\Controllers\Api\EnhancedReportApiController::class, 'deliver']);
        Route::get('/enhanced-reports-statistics', [App\Http\Controllers\Api\EnhancedReportApiController::class, 'statistics']);
        Route::post('/enhanced-reports/{report}/upload-image', [App\Http\Controllers\Api\EnhancedReportApiController::class, 'uploadImage']);
        Route::delete('/enhanced-reports/{report}/remove-image', [App\Http\Controllers\Api\EnhancedReportApiController::class, 'removeImage']);
    });

    // Enhanced Reports PDF routes (with CORS support)
    Route::middleware(['role:admin,staff,doctor'])->group(function () {
        Route::middleware('pdf.cors')->group(function () {
            Route::get('/enhanced-reports/{report}/print', [App\Http\Controllers\Api\EnhancedReportApiController::class, 'print']);
            Route::get('/enhanced-reports/{report}/print-view', [App\Http\Controllers\Api\EnhancedReportApiController::class, 'printView']);
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