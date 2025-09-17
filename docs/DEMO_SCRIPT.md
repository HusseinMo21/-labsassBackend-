# Demo Script: Testing the Validation Workflow

## Setup for Demo

### 1. Create Test Data
First, let's create some test data to demonstrate the workflow:

```bash
# Run this in your backend directory
php artisan tinker
```

```php
// Create a test patient
$patient = \App\Models\Patient::create([
    'name' => 'John Doe',
    'gender' => 'male',
    'birth_date' => '1980-01-01',
    'phone' => '1234567890',
    'address' => '123 Main St',
    'username' => 'johndoe',
    'password' => bcrypt('password')
]);

// Create a visit
$visit = \App\Models\Visit::create([
    'patient_id' => $patient->id,
    'visit_number' => 'VIS-20241213-000001',
    'visit_date' => now()->toDateString(),
    'status' => 'pending',
    'total_amount' => 100.00,
    'final_amount' => 100.00
]);

// Create a lab test
$labTest = \App\Models\LabTest::create([
    'name' => 'Complete Blood Count',
    'code' => 'CBC',
    'price' => 50.00,
    'reference_range' => 'WBC: 4.0-11.0, RBC: 4.0-5.5, Platelets: 150-450'
]);

// Create a visit test
$visitTest = \App\Models\VisitTest::create([
    'visit_id' => $visit->id,
    'lab_test_id' => $labTest->id,
    'price' => 50.00,
    'status' => 'completed',
    'result_value' => 'WBC: 12.5, RBC: 4.2, Platelets: 300',
    'result_status' => 'abnormal'
]);

echo "Test data created successfully!";
echo "Visit Test ID: " . $visitTest->id;
```

### 2. Demo Steps

#### Step 1: Login as Doctor
1. Go to your frontend application
2. Login with a doctor account
3. Navigate to `/test-validation`

#### Step 2: Create Initial Validation
1. Click "Create Initial Validation"
2. Enter the Visit Test ID from above
3. Click "Create Validation"
4. You should see the validation created

#### Step 3: Doctor Review
1. Go to "Pending Doctor Review" tab
2. Find the test you just created
3. Click the review button
4. Add clinical correlation: "Patient shows signs of infection with elevated WBC"
5. Add validation notes: "Results are technically valid"
6. Click "Validate"

#### Step 4: Login as Admin
1. Logout and login as admin
2. Go to `/test-validation`
3. Click "Pending Admin Approval" tab
4. Find the test
5. Click the approval button
6. Add final notes: "Approved for report generation"
7. Click "Final Approval"

#### Step 5: Generate Report
1. Go to `/enhanced-reports`
2. Find the visit
3. Click "View Report Status"
4. Check that all validations are complete
5. Click "Generate Report"
6. PDF should download

## Testing Different Scenarios

### Scenario 1: Normal Results
```php
// Create normal results
$visitTest->update([
    'result_value' => 'WBC: 7.5, RBC: 4.5, Platelets: 250',
    'result_status' => 'normal'
]);
```

### Scenario 2: Critical Results
```php
// Create critical results
$visitTest->update([
    'result_value' => 'WBC: 25.0, RBC: 2.1, Platelets: 50',
    'result_status' => 'critical'
]);
```

### Scenario 3: QC Failure
```php
// Create QC failure
\App\Models\QualityControl::create([
    'visit_test_id' => $visitTest->id,
    'qc_type' => 'post_test',
    'status' => 'failed',
    'expected_value' => 7.0,
    'actual_value' => 8.5,
    'tolerance_range' => 0.5,
    'performed_by' => 1, // Staff user ID
    'performed_at' => now()
]);
```

## Expected Results

### After Step 1 (Create Validation):
- Validation record created
- Status: "pending"
- Auto-checks performed

### After Step 2 (Doctor Review):
- Validation status: "validated"
- Clinical correlation added
- Test ready for admin approval

### After Step 3 (Admin Approval):
- Test status: "completed"
- Report generation enabled
- All validations complete

### After Step 4 (Generate Report):
- PDF report generated
- Includes all validation data
- Professional format with signatures

## Troubleshooting Demo Issues

### Issue: "No tests found"
**Solution:** Make sure you created the test data correctly

### Issue: "Permission denied"
**Solution:** Check user roles and permissions

### Issue: "Validation already exists"
**Solution:** Delete existing validation or use different test

### Issue: "Cannot generate report"
**Solution:** Check if all steps are completed

## Clean Up Demo Data
```php
// Clean up test data
$visitTest->delete();
$visit->delete();
$patient->delete();
$labTest->delete();
```

This demo script will help you understand exactly how the workflow functions in practice.


