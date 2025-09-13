# Test Validation Workflow Guide

## Overview
The Test Validation workflow ensures that all laboratory test results are properly validated by doctors before reports can be generated. This maintains quality and accuracy in your pathology laboratory.

## Workflow Steps

### 1. **Test Execution** (Staff/Technician)
- Laboratory staff perform the actual tests
- Results are entered into the system
- Tests are marked as "completed" or "in_progress"

### 2. **Initial Validation Creation** (Doctor)
- **WHO**: Doctors (role: 'doctor')
- **WHAT**: Create initial validation records for completed tests
- **HOW**: 
  - Go to `/test-validation` page
  - Click "Create Initial Validation"
  - Enter the Visit Test ID
  - System automatically performs validation checks

### 3. **Doctor Review & Validation** (Doctor)
- **WHO**: Doctors (role: 'doctor')
- **WHAT**: Review test results and provide clinical correlation
- **HOW**:
  - Go to `/test-validation` page
  - Click on "Pending Doctor Review" tab
  - Review each test result
  - Provide clinical correlation and validation notes
  - Choose action:
    - **Validate**: Approve the result
    - **Reject**: Reject with reason
    - **Require Correction**: Send back for correction

### 4. **Admin Final Approval** (Head of Doctors)
- **WHO**: Admin (role: 'admin') - Head of Doctors
- **WHAT**: Final approval before report generation
- **HOW**:
  - Go to `/test-validation` page
  - Click on "Pending Admin Approval" tab
  - Review all doctor validations
  - Choose action:
    - **Approve**: Final approval for report generation
    - **Reject**: Send back to doctor for review

### 5. **Report Generation** (Admin/Staff/Doctor)
- **WHO**: Admin, Staff, or Doctor
- **WHAT**: Generate final pathology report
- **HOW**:
  - Go to `/enhanced-reports` page
  - Check report status
  - Generate PDF report (only if all validations are complete)

## User Roles & Permissions

### **Admin (Head of Doctors)**
- ✅ Can provide final approval for all tests
- ✅ Can generate reports
- ✅ Can view all validation records
- ✅ Can override validation decisions

### **Doctor**
- ✅ Can create initial validations
- ✅ Can review and validate test results
- ✅ Can provide clinical correlation
- ✅ Can reject or require corrections
- ❌ Cannot provide final approval (Admin only)
- ❌ Cannot generate reports until admin approval

### **Staff**
- ✅ Can view validation status
- ✅ Can assist with workflow
- ❌ Cannot validate test results
- ❌ Cannot generate reports

## Step-by-Step Example

### Example: Complete Blood Count (CBC) Test

1. **Staff performs CBC test**
   - Test completed, results entered
   - Status: "completed"

2. **Doctor creates initial validation**
   - Doctor goes to Test Validation page
   - Creates initial validation for CBC test
   - System checks: reference range, critical values, etc.

3. **Doctor reviews results**
   - Doctor sees: WBC: 12.5 (High), RBC: 4.2 (Normal), etc.
   - Doctor adds clinical correlation: "Patient shows signs of infection"
   - Doctor validates the results

4. **Admin provides final approval**
   - Admin reviews doctor's validation
   - Admin approves the validation
   - Test is now ready for report generation

5. **Report generation**
   - Staff/Doctor generates final report
   - Report includes all validated results with clinical correlation

## Quality Control Integration

### QC Workflow
1. **Pre-test QC**: Before running patient samples
2. **Post-test QC**: After completing tests
3. **Batch Control**: For multiple samples

### QC Requirements
- QC must pass before test validation
- Failed QC blocks report generation
- QC records are included in final reports

## Common Scenarios

### Scenario 1: Normal Test Results
1. Staff completes test → Results normal
2. Doctor creates validation → Auto-checks pass
3. Doctor validates → Adds clinical correlation
4. Admin approves → Report ready

### Scenario 2: Abnormal Results
1. Staff completes test → Results abnormal
2. Doctor creates validation → Critical value alerts
3. Doctor validates → Adds clinical correlation and recommendations
4. Admin approves → Report ready with flags

### Scenario 3: QC Failure
1. Staff completes test → QC fails
2. QC must be repeated and pass
3. Only then can validation proceed
4. Report generation blocked until QC passes

### Scenario 4: Doctor Rejection
1. Doctor reviews results → Finds issues
2. Doctor rejects → Provides correction notes
3. Staff corrects results → Test goes back to doctor
4. Doctor validates → Admin approves → Report ready

## Navigation Guide

### For Doctors:
1. **Dashboard** → **Test Validation** → **Pending Doctor Review**
2. Review each test result
3. Add clinical correlation
4. Validate, reject, or require correction

### For Admin (Head of Doctors):
1. **Dashboard** → **Test Validation** → **Pending Admin Approval**
2. Review doctor validations
3. Provide final approval or rejection

### For Staff:
1. **Dashboard** → **Enhanced Reports** → Check report status
2. Generate reports when ready

## Troubleshooting

### "Cannot generate report" error:
- Check if all tests are validated by doctors
- Check if admin has provided final approval
- Check if QC has passed

### "No pending validations" message:
- All tests may already be validated
- Check the "All Validations" tab
- Create new validations if needed

### "QC failed" blocking report:
- Go to Quality Control page
- Review failed QC records
- Repeat QC until it passes

## Best Practices

1. **Always validate test results** before report generation
2. **Provide meaningful clinical correlation** for abnormal results
3. **Document rejection reasons** clearly
4. **Ensure QC passes** before validation
5. **Review all validations** before final approval
6. **Generate reports promptly** after approval

## Contact & Support

If you have questions about the workflow:
1. Check this guide first
2. Review the system logs
3. Contact system administrator
4. Check user permissions and roles
