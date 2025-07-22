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
use App\Http\Controllers\Api\CriticalValueController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\TestPanelController;
use App\Http\Controllers\Api\CheckInController;
use App\Http\Controllers\Api\UnpaidInvoicesController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/tests/categories', [LabTestController::class, 'categories']);
Route::get('/patients/search', [VisitController::class, 'searchPatients']);
Route::get('/lab-tests', [VisitController::class, 'getLabTests']);
Route::get('/visits', [VisitController::class, 'getVisits']);
Route::get('/visits/{id}', [VisitController::class, 'getVisit']);
Route::post('/visits', [VisitController::class, 'createVisit']);
Route::get('/dashboard/stats', [VisitController::class, 'getDashboardStats']);
// Make roles endpoint available to any authenticated user (or move to public for demo)
Route::middleware('auth:web')->get('/users/roles', [UserController::class, 'getRoles']);

// Temporary public routes for testing (remove after fixing authentication)
Route::get('/test/sample-tracking/stats', [SampleTrackingController::class, 'getStats']);
Route::get('/test/notifications/stats', [NotificationController::class, 'getStats']);
Route::get('/test/audit-logs/stats', [AuditLogController::class, 'getStats']);

// Public invoice PDF/preview routes
Route::get('/invoices/{id}/download', [App\Http\Controllers\Api\InvoiceController::class, 'downloadInvoicePdf']);
Route::get('/invoices/{id}/preview', [App\Http\Controllers\Api\InvoiceController::class, 'previewInvoiceHtml']);

// Protected routes
Route::middleware('auth:web')->group(function () {
    // Auth routes
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // Patient routes
    Route::get('/patients/by-test', [PatientController::class, 'patientsByTest']);
    Route::get('/patients/{id}/full-history', [PatientController::class, 'fullHistory']);
    Route::get('/patients/{id}/reports', [PatientController::class, 'reportsList']);
    Route::get('/patients/{id}/payments', [PatientController::class, 'paymentsHistory']);
    Route::get('/patients/{id}/print-reports', [PatientController::class, 'printAllReports']);
    Route::get('/reports/{report_id}/print', [PatientController::class, 'printSingleReport']);
    Route::apiResource('patients', PatientController::class);
    Route::get('/patient/me', [PatientController::class, 'me']);

    // Lab test routes
    Route::apiResource('tests', LabTestController::class);

    // Visit routes
    // Route::apiResource('visits', VisitController::class); // Removed to fix missing index method error
    Route::put('/visits/{visit}', [VisitController::class, 'update']);
    Route::put('/visits/{visit}/results', [VisitController::class, 'updateVisitResults']);
    Route::put('/visits/{visit}/tests/{visitTest}/result', [VisitController::class, 'updateTestResult']);
    Route::get('/visits/{visit}/report', [VisitController::class, 'generateReport']);
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

    // Expense routes (admin and accountant only)
    Route::apiResource('expenses', \App\Http\Controllers\Api\ExpenseController::class);
    Route::get('/expenses/stats', [\App\Http\Controllers\Api\ExpenseController::class, 'getStats']);
    Route::get('/expenses/categories', [\App\Http\Controllers\Api\ExpenseController::class, 'getCategories']);

    // User management (admin only)
    Route::middleware(['role:admin'])->group(function () {
        Route::apiResource('users', UserController::class);
        Route::get('/users/stats', [UserController::class, 'getStats']);
        Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::patch('/users/{user}/change-password', [UserController::class, 'changePassword']);
    });

    // Inventory routes (admin and lab tech only)
    Route::middleware(['role:admin,lab_tech'])->group(function () {
        Route::apiResource('inventory', InventoryController::class);
        Route::get('/inventory/stats', [InventoryController::class, 'getStats']);
        Route::get('/inventory/low-stock', [InventoryController::class, 'getLowStockItems']);
        Route::get('/inventory/expired', [InventoryController::class, 'getExpiredItems']);
        Route::patch('/inventory/{inventoryItem}/adjust-quantity', [InventoryController::class, 'adjustQuantity']);
        Route::post('/inventory/bulk-update', [InventoryController::class, 'bulkUpdate']);
    });

    // Reports (admin only)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/reports/revenue', [ReportController::class, 'revenue']);
        Route::get('/reports/patients', [ReportController::class, 'patients']);
        Route::get('/reports/tests', [ReportController::class, 'tests']);
        Route::get('/reports/financial', [ReportController::class, 'financial']);
        Route::get('/reports/export', [ReportController::class, 'export']);
    });

    // Sample Tracking routes (admin and lab tech only)
    Route::middleware(['role:admin,lab_tech'])->group(function () {
        Route::apiResource('sample-tracking', SampleTrackingController::class);
        Route::get('/sample-tracking/stats', [SampleTrackingController::class, 'getStats']);
        Route::post('/sample-tracking/create/{visitTestId}', [SampleTrackingController::class, 'createSample']);
        Route::put('/sample-tracking/{id}/status', [SampleTrackingController::class, 'updateStatus']);
        Route::get('/sample-tracking/visit-test/{visitTestId}', [SampleTrackingController::class, 'getSampleByVisitTest']);
    });

    // Critical Values routes (admin and lab tech only)
    Route::middleware(['role:admin,lab_tech'])->group(function () {
        Route::apiResource('critical-values', CriticalValueController::class);
        Route::post('/critical-values/check', [CriticalValueController::class, 'checkCriticalValue']);
        Route::get('/critical-values/test/{testId}', [CriticalValueController::class, 'getByTest']);
    });

    // Notifications routes (admin and lab tech only)
    Route::middleware(['role:admin,lab_tech'])->group(function () {
        Route::apiResource('notifications', NotificationController::class);
        Route::post('/notifications/send-result', [NotificationController::class, 'sendResultNotification']);
        Route::post('/notifications/{id}/resend', [NotificationController::class, 'resendNotification']);
        Route::get('/notifications/pending', [NotificationController::class, 'getPendingNotifications']);
        Route::get('/notifications/stats', [NotificationController::class, 'getStats']);
        Route::put('/notifications/{id}/delivered', [NotificationController::class, 'markAsDelivered']);
        Route::put('/notifications/{id}/failed', [NotificationController::class, 'markAsFailed']);
    });

    // Audit Logs routes (admin only)
    Route::middleware(['role:admin'])->group(function () {
        Route::apiResource('audit-logs', AuditLogController::class);
        Route::get('/audit-logs/user/{userId}', [AuditLogController::class, 'getActivityByUser']);
        Route::get('/audit-logs/model/{modelType}/{modelId}', [AuditLogController::class, 'getActivityByModel']);
        Route::get('/audit-logs/stats', [AuditLogController::class, 'getStats']);
        Route::get('/audit-logs/export', [AuditLogController::class, 'export']);
    });

    // Test Panels routes (admin and lab tech only)
    Route::middleware(['role:admin,lab_tech'])->group(function () {
        Route::get('/test-panels/available-tests', [TestPanelController::class, 'getAvailableTests']);
        Route::get('/test-panels/stats', [TestPanelController::class, 'getStats']);
        Route::apiResource('test-panels', TestPanelController::class);
        Route::post('/test-panels/{id}/tests', [TestPanelController::class, 'addTest']);
        Route::delete('/test-panels/{id}/tests/{testId}', [TestPanelController::class, 'removeTest']);
        Route::put('/test-panels/{id}/reorder', [TestPanelController::class, 'reorderTests']);
    });

    // Check-in and Billing Workflow routes (admin, lab_tech, and accountant)
    Route::middleware(['role:admin,lab_tech,accountant'])->group(function () {
        Route::post('/check-in/register-patient', [CheckInController::class, 'registerPatient']);
        Route::post('/check-in/create-visit', [CheckInController::class, 'createVisitWithBilling']);
        Route::get('/check-in/patients/search', [CheckInController::class, 'searchPatients']);
        Route::get('/check-in/tests', [CheckInController::class, 'getAvailableTests']);
        Route::post('/check-in/calculate-billing', [CheckInController::class, 'calculateBilling']);
        Route::get('/check-in/visits/{visitId}/receipt', [CheckInController::class, 'getReceipt']);
        Route::get('/check-in/visits/{visitId}/sample-label', [CheckInController::class, 'getSampleLabel']);
        Route::get('/check-in/visits/{visitId}/final-payment-receipt', [CheckInController::class, 'getFinalPaymentReceipt']);
    });

    // Unpaid Invoices and Patient Balance routes (admin and accountant)
    Route::middleware(['role:admin,accountant'])->group(function () {
        Route::get('/unpaid-invoices/search', [UnpaidInvoicesController::class, 'searchUnpaidInvoices']);
        Route::get('/unpaid-invoices/summary', [UnpaidInvoicesController::class, 'getUnpaidInvoicesSummary']);
        Route::get('/patients/{patientId}/balance', [UnpaidInvoicesController::class, 'getPatientBalance']);
        Route::post('/invoices/{invoiceId}/add-payment', [UnpaidInvoicesController::class, 'addPayment']);
        Route::get('/patients/{patientId}/portal-access', [UnpaidInvoicesController::class, 'checkPatientPortalAccess']);
    });
}); 