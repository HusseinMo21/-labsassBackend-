# Lab Request System Migration Plan

## Overview

This document outlines the migration plan for implementing the legacy-compatible lab request and samples support system. The implementation adds structured tables to support legacy semantics while maintaining backward compatibility.

## Database Changes

### New Tables

#### 1. `lab_requests` Table
- **Purpose**: Main table for lab requests with legacy-compatible structure
- **Key Features**:
  - Auto-generated lab numbers (format: `YYYY-NNNN`)
  - Staff suffix support (`m` for morning, `h` for afternoon)
  - Status tracking (pending → received → in_progress → under_review → completed → delivered)
  - JSON metadata field for legacy field archiving
  - Foreign key to patients table (nullable)

#### 2. `samples` Table
- **Purpose**: Stores sample information for each lab request
- **Key Features**:
  - Legacy field support (`tsample`, `nsample`, `isample`)
  - Notes field for additional information
  - Cascade delete with lab requests

#### 3. `lab_sequences` Table
- **Purpose**: Manages lab number sequence generation with concurrency safety
- **Key Features**:
  - Year-based sequence tracking
  - Row-level locking for concurrent access
  - Configurable starting sequence (default: 7000)

### Modified Tables

#### 1. `reports` Table
- **Added**: `lab_request_id` (nullable foreign key)
- **Purpose**: Link reports to lab requests

#### 2. `invoices` Table
- **Added**: `lab_request_id` (nullable foreign key)
- **Purpose**: Link invoices to lab requests

## Migration Steps

### 1. Pre-Migration Backup
```bash
# Create database backup
mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql

# Or for SQLite
cp database/database.sqlite database/database_backup_$(date +%Y%m%d_%H%M%S).sqlite
```

### 2. Run Migrations
```bash
cd backend
php artisan migrate
```

### 3. Generate Sample Data (Optional)
```bash
# Generate 10 sample lab requests for testing
php artisan lab:generate-sample 10
```

### 4. Verify Installation
```bash
# Check if tables were created
php artisan tinker
>>> Schema::hasTable('lab_requests')
>>> Schema::hasTable('samples')
>>> Schema::hasTable('lab_sequences')
```

## API Endpoints

### Lab Requests
- `GET /api/lab-requests` - List lab requests with pagination
- `POST /api/lab-requests` - Create new lab request
- `GET /api/lab-requests/{id}` - Show specific lab request
- `PUT /api/lab-requests/{id}` - Update lab request
- `DELETE /api/lab-requests/{id}` - Delete lab request

### Staff Operations
- `PUT /api/lab-requests/{id}/suffix` - Update suffix (staff/admin only)

### Search & Statistics
- `GET /api/lab-requests-search` - Search lab requests
- `GET /api/lab-requests-stats` - Get statistics

## Key Features

### 1. Lab Number Generation
- **Format**: `YYYY-NNNN` (e.g., `2025-7001`)
- **Suffix Support**: `m` (morning) or `h` (afternoon)
- **Full Lab No**: `2025-7001m` or `2025-7001h`
- **Concurrency Safe**: Uses database transactions with row-level locking

### 2. Barcode & QR Code Generation
- **Auto-generation**: Created on lab request creation
- **Storage**: `storage/app/public/barcodes/` and `storage/app/public/qrcodes/`
- **Format**: PNG files with lab number as filename
- **Access**: Via model accessors `barcode_url` and `qr_code_url`

### 3. Legacy Compatibility
- **Sample Fields**: `tsample`, `nsample`, `isample` preserved
- **Metadata Archiving**: Unmapped legacy fields stored in JSON
- **Migration Support**: Artisan commands for legacy data migration

### 4. Role-Based Access
- **Suffix Updates**: Only staff and admin can modify suffixes
- **API Protection**: All endpoints require authentication
- **Validation**: Strict input validation for all operations

## Configuration

### Lab Configuration (`config/lab.php`)
```php
'start_sequence' => env('LAB_START_SEQUENCE', 7000),
'barcode' => [
    'format' => env('LAB_BARCODE_FORMAT', 'CODE128'),
    'width' => env('LAB_BARCODE_WIDTH', 2),
    'height' => env('LAB_BARCODE_HEIGHT', 50),
],
'qr_code' => [
    'size' => env('LAB_QR_SIZE', 200),
    'margin' => env('LAB_QR_MARGIN', 1),
],
```

### Environment Variables
```env
LAB_START_SEQUENCE=7000
LAB_BARCODE_FORMAT=CODE128
LAB_BARCODE_WIDTH=2
LAB_BARCODE_HEIGHT=50
LAB_QR_SIZE=200
LAB_QR_MARGIN=1
LAB_STORAGE_DISK=public
```

## Artisan Commands

### Sample Generation
```bash
# Generate sample lab requests for testing
php artisan lab:generate-sample 10
```

### Legacy Migration
```bash
# Migrate legacy sample data (dry run)
php artisan lab:migrate-legacy-samples --dry-run

# Migrate legacy sample data (actual)
php artisan lab:migrate-legacy-samples --limit=100
```

## Testing

### Run Tests
```bash
# Run all tests
php artisan test

# Run specific test suites
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Run specific tests
php artisan test tests/Unit/LabNoGeneratorTest.php
php artisan test tests/Feature/LabRequestTest.php
php artisan test tests/Unit/BarcodeGeneratorTest.php
```

### Test Coverage
- **Unit Tests**: LabNoGenerator, BarcodeGenerator
- **Feature Tests**: LabRequest API endpoints
- **Integration Tests**: Full workflow testing

## Rollback Plan

### If Migration Fails
```bash
# Rollback specific migrations
php artisan migrate:rollback --step=5

# Or rollback all migrations
php artisan migrate:reset
```

### Manual Rollback SQL
```sql
-- Drop new tables
DROP TABLE IF EXISTS samples;
DROP TABLE IF EXISTS lab_requests;
DROP TABLE IF EXISTS lab_sequences;

-- Remove added columns
ALTER TABLE reports DROP COLUMN IF EXISTS lab_request_id;
ALTER TABLE invoices DROP COLUMN IF EXISTS lab_request_id;
```

## Dependencies

### Required Packages
- `simplesoftwareio/simple-qrcode` - QR code generation
- `picqer/php-barcode-generator` - Barcode generation

### Installation
```bash
composer require simplesoftwareio/simple-qrcode
composer require picqer/php-barcode-generator
```

## Security Considerations

1. **Input Validation**: All inputs are strictly validated
2. **Role-Based Access**: Suffix updates restricted to staff/admin
3. **File Storage**: Barcode/QR files stored in public disk with proper access controls
4. **SQL Injection**: All queries use Eloquent ORM or parameterized queries
5. **CSRF Protection**: All API endpoints protected with CSRF tokens

## Performance Considerations

1. **Database Indexes**: Added on frequently queried columns
2. **Eager Loading**: Relationships loaded efficiently
3. **Pagination**: Large datasets paginated
4. **Caching**: Consider implementing Redis for high-traffic scenarios
5. **File Storage**: Consider CDN for barcode/QR file delivery

## Monitoring & Logging

### Log Channels
- **Lab Request Creation**: Logged with full context
- **Suffix Updates**: Logged with user information
- **Errors**: Comprehensive error logging
- **Performance**: Consider adding performance monitoring

### Key Metrics to Monitor
- Lab request creation rate
- Average processing time
- Error rates
- Storage usage for barcode/QR files

## Future Enhancements

1. **Bulk Operations**: Support for bulk lab request creation
2. **Advanced Search**: Full-text search capabilities
3. **Reporting**: Advanced reporting and analytics
4. **Integration**: API integration with external systems
5. **Mobile Support**: Mobile-optimized interfaces
6. **Notifications**: Real-time status updates
7. **Workflow**: Customizable lab request workflows

## Support & Maintenance

### Regular Maintenance Tasks
1. **Storage Cleanup**: Remove old barcode/QR files
2. **Database Optimization**: Regular index maintenance
3. **Log Rotation**: Manage log file sizes
4. **Backup Verification**: Ensure backups are working

### Troubleshooting

#### Common Issues
1. **Lab Number Conflicts**: Check lab_sequences table
2. **File Generation Failures**: Verify storage permissions
3. **Performance Issues**: Check database indexes
4. **Permission Errors**: Verify user roles and permissions

#### Debug Commands
```bash
# Check lab sequences
php artisan tinker
>>> App\Models\LabSequence::all()

# Check storage permissions
ls -la storage/app/public/

# Check database indexes
php artisan tinker
>>> Schema::getIndexes('lab_requests')
```

## Conclusion

This migration plan provides a comprehensive approach to implementing the lab request system while maintaining backward compatibility and ensuring data integrity. The system is designed to be scalable, secure, and maintainable.

For questions or issues, please refer to the test suite or contact the development team.




