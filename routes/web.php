<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CheckInController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// PDF routes WITHOUT authentication - placed in web.php to avoid sanctum middleware
Route::middleware(['pdf.cors'])->group(function () {
    // Handle OPTIONS requests explicitly with CORS headers
    Route::options('/api/check-in/visits/{visitId}/unpaid-invoice-receipt', function () {
        return response('', 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '86400',
        ]);
    });
    Route::options('/api/check-in/visits/{visitId}/unpaid-invoice-receipt-data', function () {
        return response('', 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '86400',
        ]);
    });
    Route::options('/api/check-in/visits/{visitId}/final-payment-receipt-pdf', function () {
        return response('', 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '86400',
        ]);
    });
    
    // Actual PDF routes - NO AUTHENTICATION REQUIRED
    Route::get('/api/check-in/visits/{visitId}/unpaid-invoice-receipt', [CheckInController::class, 'generateUnpaidInvoiceReceipt']);
    Route::get('/api/check-in/visits/{visitId}/unpaid-invoice-receipt-data', [CheckInController::class, 'getUnpaidInvoiceReceiptData']);
    Route::get('/api/check-in/visits/{visitId}/final-payment-receipt-pdf', [CheckInController::class, 'generateFinalPaymentReceipt']);
});

Route::get('/', function () {
    return view('welcome');
});

// Enhanced Reports Routes
Route::resource('reports', App\Http\Controllers\EnhancedReportController::class);

// Report Workflow Routes
Route::post('reports/{report}/submit-review', [App\Http\Controllers\EnhancedReportController::class, 'submitForReview'])
    ->name('reports.submit-review');
Route::post('reports/{report}/approve', [App\Http\Controllers\EnhancedReportController::class, 'approve'])
    ->name('reports.approve');
Route::post('reports/{report}/print', [App\Http\Controllers\EnhancedReportController::class, 'print'])
    ->name('reports.print-post');
Route::post('reports/{report}/deliver', [App\Http\Controllers\EnhancedReportController::class, 'deliver'])
    ->name('reports.deliver');

// Report Print/Export Routes
Route::get('reports/{report}/print', [App\Http\Controllers\EnhancedReportController::class, 'printReport'])
    ->name('reports.print-get');
Route::get('reports/{report}/pdf', [App\Http\Controllers\EnhancedReportController::class, 'exportPdf'])
    ->name('reports.pdf');

// Report Statistics
Route::get('reports-statistics', [App\Http\Controllers\EnhancedReportController::class, 'statistics'])
    ->name('reports.statistics'); 