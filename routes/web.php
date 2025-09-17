<?php

use Illuminate\Support\Facades\Route;

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
    ->name('reports.print');
Route::post('reports/{report}/deliver', [App\Http\Controllers\EnhancedReportController::class, 'deliver'])
    ->name('reports.deliver');

// Report Print/Export Routes
Route::get('reports/{report}/print', [App\Http\Controllers\EnhancedReportController::class, 'printReport'])
    ->name('reports.print');
Route::get('reports/{report}/pdf', [App\Http\Controllers\EnhancedReportController::class, 'exportPdf'])
    ->name('reports.pdf');

// Report Statistics
Route::get('reports-statistics', [App\Http\Controllers\EnhancedReportController::class, 'statistics'])
    ->name('reports.statistics'); 