# Lab Request System Implementation

## Quick Start Guide

This guide will help you quickly set up and test the new lab request system.

### Prerequisites

- Laravel 11 application
- PHP 8.1+
- Database (MySQL/SQLite/PostgreSQL)
- Composer dependencies installed

### Installation Steps

#### 1. Install Dependencies
```bash
cd backend
composer require simplesoftwareio/simple-qrcode
composer require picqer/php-barcode-generator
```

#### 2. Create Database Backup
```bash
# For SQLite
cp database/database.sqlite database/database_backup_$(date +%Y%m%d_%H%M%S).sqlite

# For MySQL
mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql
```

#### 3. Run Migrations
```bash
php artisan migrate
```

#### 4. Generate Sample Data
```bash
# Generate 10 sample lab requests for testing
php artisan lab:generate-sample 10
```

#### 5. Test the System
```bash
# Run tests to verify everything works
php artisan test tests/Unit/LabNoGeneratorTest.php
php artisan test tests/Feature/LabRequestTest.php
php artisan test tests/Unit/BarcodeGeneratorTest.php
```

### API Testing

#### Create a Lab Request
```bash
curl -X POST http://localhost:8000/api/lab-requests \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "patient_id": 1,
    "samples": [
      {
        "tsample": "Blood Sample",
        "nsample": "Sample 1",
        "isample": "ID001",
        "notes": "Test sample"
      }
    ]
  }'
```

#### List Lab Requests
```bash
curl -X GET http://localhost:8000/api/lab-requests \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Update Suffix (Staff Only)
```bash
curl -X PUT http://localhost:8000/api/lab-requests/1/suffix \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"suffix": "m"}'
```

### Key Features

#### 1. Lab Number Generation
- **Format**: `YYYY-NNNN` (e.g., `2025-7001`)
- **Suffix Support**: `m` (morning) or `h` (afternoon)
- **Full Lab No**: `2025-7001m` or `2025-7001h`

#### 2. Barcode & QR Code
- Auto-generated on lab request creation
- Stored in `storage/app/public/barcodes/` and `storage/app/public/qrcodes/`
- Accessible via model accessors

#### 3. Legacy Compatibility
- Supports legacy sample fields (`tsample`, `nsample`, `isample`)
- Metadata archiving for unmapped fields
- Migration commands for legacy data

### Configuration

#### Environment Variables
```env
LAB_START_SEQUENCE=7000
LAB_BARCODE_FORMAT=CODE128
LAB_BARCODE_WIDTH=2
LAB_BARCODE_HEIGHT=50
LAB_QR_SIZE=200
LAB_QR_MARGIN=1
LAB_STORAGE_DISK=public
```

### Artisan Commands

```bash
# Generate sample data
php artisan lab:generate-sample 10

# Migrate legacy data (dry run)
php artisan lab:migrate-legacy-samples --dry-run

# Migrate legacy data
php artisan lab:migrate-legacy-samples --limit=100
```

### Database Schema

#### New Tables
- `lab_requests` - Main lab request table
- `samples` - Sample information
- `lab_sequences` - Lab number sequence management

#### Modified Tables
- `reports` - Added `lab_request_id` column
- `invoices` - Added `lab_request_id` column

### Testing

```bash
# Run all tests
php artisan test

# Run specific test suites
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

### Troubleshooting

#### Common Issues

1. **Migration Fails**
   ```bash
   # Check database connection
   php artisan migrate:status
   
   # Rollback if needed
   php artisan migrate:rollback --step=5
   ```

2. **Barcode Generation Fails**
   ```bash
   # Check storage permissions
   ls -la storage/app/public/
   
   # Create directories if missing
   mkdir -p storage/app/public/barcodes
   mkdir -p storage/app/public/qrcodes
   ```

3. **Lab Number Conflicts**
   ```bash
   # Check lab sequences
   php artisan tinker
   >>> App\Models\LabSequence::all()
   ```

### Security

- All API endpoints require authentication
- Suffix updates restricted to staff/admin roles
- Input validation on all endpoints
- CSRF protection enabled

### Performance

- Database indexes on frequently queried columns
- Eager loading for relationships
- Pagination for large datasets
- Efficient barcode/QR generation

### Support

For issues or questions:
1. Check the test suite for examples
2. Review the migration plan document
3. Check Laravel logs in `storage/logs/`
4. Contact the development team

### Next Steps

1. **Frontend Integration**: Update React components to use new API
2. **PDF Templates**: Update report/invoice templates to include barcodes
3. **Legacy Migration**: Run legacy data migration when ready
4. **Production Deployment**: Follow deployment checklist

### Files Created/Modified

#### New Files
- `app/Models/LabRequest.php`
- `app/Models/Sample.php`
- `app/Models/LabSequence.php`
- `app/Models/Report.php`
- `app/Services/LabNoGenerator.php`
- `app/Services/BarcodeGenerator.php`
- `app/Http/Controllers/Api/LabRequestController.php`
- `app/Console/Commands/GenerateSampleLabRequests.php`
- `app/Console/Commands/MigrateLegacySamples.php`
- `config/lab.php`
- Database migrations (5 files)
- Tests (3 files)

#### Modified Files
- `app/Models/Patient.php` - Added lab requests relationship
- `app/Models/Invoice.php` - Added lab request relationship
- `routes/api.php` - Added lab request routes

### Rollback Instructions

If you need to rollback the changes:

```bash
# Rollback migrations
php artisan migrate:rollback --step=5

# Remove generated files
rm -rf storage/app/public/barcodes
rm -rf storage/app/public/qrcodes

# Remove new files (optional)
rm -rf app/Models/LabRequest.php
rm -rf app/Models/Sample.php
rm -rf app/Models/LabSequence.php
rm -rf app/Models/Report.php
rm -rf app/Services/LabNoGenerator.php
rm -rf app/Services/BarcodeGenerator.php
rm -rf app/Http/Controllers/Api/LabRequestController.php
rm -rf app/Console/Commands/GenerateSampleLabRequests.php
rm -rf app/Console/Commands/MigrateLegacySamples.php
rm -rf config/lab.php
```

---

**Note**: This implementation is designed to be additive and non-breaking. All existing functionality should continue to work as before.


