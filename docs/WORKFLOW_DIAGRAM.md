# Laboratory Test Validation Workflow Diagram

## Complete Workflow

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Test          │    │   Quality       │    │   Doctor        │    │   Admin         │
│   Execution     │───▶│   Control       │───▶│   Validation    │───▶│   Final         │
│   (Staff)       │    │   (Staff)       │    │   (Doctor)      │    │   Approval      │
└─────────────────┘    └─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │                       │
         ▼                       ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ • Run tests     │    │ • Pre-test QC   │    │ • Review        │    │ • Final review  │
│ • Enter results │    │ • Post-test QC  │    │ • Clinical      │    │ • Approve/      │
│ • Mark complete │    │ • Batch control │    │   correlation   │    │   Reject        │
└─────────────────┘    └─────────────────┘    └─────────────────┘    └─────────────────┘
                                                         │                       │
                                                         ▼                       ▼
                                                ┌─────────────────┐    ┌─────────────────┐
                                                │   Report        │    │   Report        │
                                                │   Generation    │◀───│   Ready         │
                                                │   (All Roles)   │    │   (Status)      │
                                                └─────────────────┘    └─────────────────┘
```

## Step-by-Step Process

### Step 1: Test Execution (Staff)
```
Patient Sample → Lab Test → Results Entry → Status: "completed"
```

### Step 2: Quality Control (Staff)
```
QC Sample → Expected Value → Actual Value → Pass/Fail
```

### Step 3: Doctor Validation (Doctor)
```
Test Results → Clinical Review → Validation Decision → Clinical Notes
```

### Step 4: Admin Approval (Head of Doctors)
```
Doctor Validation → Final Review → Approval Decision → Report Authorization
```

### Step 5: Report Generation (Any Role)
```
All Validations Complete → Generate PDF → Professional Report
```

## User Interface Flow

### For Doctors:
```
Dashboard → Test Validation → Pending Doctor Review → Review Test → Validate/Reject
```

### For Admin:
```
Dashboard → Test Validation → Pending Admin Approval → Review Validation → Approve/Reject
```

### For Staff:
```
Dashboard → Enhanced Reports → Check Status → Generate Report (if ready)
```

## Status Flow

```
Test: pending → in_progress → completed
QC: pending → passed/failed
Validation: pending → validated/rejected/requires_correction
Report: not_ready → ready → generated
```

## Decision Points

### Doctor Decision:
- ✅ **Validate**: Result is correct and clinically appropriate
- ❌ **Reject**: Result is incorrect, needs correction
- ⚠️ **Require Correction**: Minor issues, needs adjustment

### Admin Decision:
- ✅ **Approve**: Final approval for report generation
- ❌ **Reject**: Send back to doctor for review

### System Decision:
- 🚫 **Block Report**: If any test not validated or QC failed
- ✅ **Allow Report**: If all validations complete and QC passed
