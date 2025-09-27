# Production Deployment Instructions

## Files to Update on Production Server

### 1. Backend Routes
**File:** `backend/routes/api.php`
**Change:** Add the missing receipt route
```php
Route::get('/visits/{visitId}/receipt', [VisitController::class, 'getReceiptDetails']);
```

### 2. Visit Controller
**File:** `backend/app/Http/Controllers/Api/VisitController.php`
**Changes:** 
- Updated `getReceiptDetails` method
- Fixed metadata handling in `getTestsForReceipt` method

### 3. Shift Model
**File:** `backend/app/Models/Shift.php`
**Changes:**
- Updated `calculatePaymentBreakdown` method
- Updated `getShiftReportData` method
- Fixed duration calculation

### 4. Shift Controller
**File:** `backend/app/Http/Controllers/Api/ShiftController.php`
**Changes:**
- Added payment breakdown fields to shift report response
- Fixed undefined variable issues

## Commands to Run on Production Server

After uploading the files, run these commands on the production server:

```bash
# Clear route cache
php artisan route:clear

# Clear config cache
php artisan config:clear

# Clear application cache
php artisan cache:clear

# Optimize for production
php artisan optimize
```

## Frontend Files (Already Built)
The frontend build already includes the TypeScript fixes, so no additional deployment needed for frontend.

## Verification
After deployment, test these endpoints:
- `/api/visits/{visitId}/receipt` - Should return 200 instead of 404
- `/api/shifts/current` - Should show correct payment breakdown
- `/api/shifts/{shiftId}/report` - Should show correct cash/other payments
