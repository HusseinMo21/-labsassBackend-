# Database Migration Deployment Guide

## Overview
This guide provides a comprehensive plan for deploying your Laravel application with a clean, properly structured database schema that matches your current workflow.

## Current Database Analysis

### ✅ Tables That Match Your Workflow (35 tables)
- **Core Tables**: users, patient, visits, visit_tests, invoices, payments
- **Lab Management**: lab_requests, samples, lab_tests, test_categories
- **Reports**: reports, enhanced_reports, templates
- **System**: shifts, expenses, inventory_items, doctors, organizations
- **Authentication**: personal_access_tokens, refresh_tokens, patient_credentials
- **Cache**: cache, cache_locks

### ⚠️ Legacy Tables That Need Attention (10 tables)
- **patholgy** (73,982 records) - Should migrate to enhanced_reports
- **income** (62,219 records) - Should migrate to payments  
- **login** (12 records) - Should migrate to users
- **images** (24 records) - Consider if needed
- **list** (6 records) - Consider if needed
- **template** (1 record) - Consider if needed
- **text** (11 records) - Consider if needed
- **patholgyf** (2,656 records) - Consider if needed
- **child_template** (0 records) - Can be dropped
- **admintools** (1 record) - Consider if needed

## Migration Files Created

### 1. Comprehensive Migration
- **File**: `2025_09_21_215951_create_complete_database_schema.php`
- **Purpose**: Creates all required tables with proper foreign key constraints
- **Status**: ✅ Ready to use

### 2. Analysis Scripts
- **File**: `analyze_database.php` - Analyzes current database structure
- **File**: `create_data_migration.php` - Analyzes data migration needs
- **File**: `deploy_migration.php` - Safe deployment script

## Deployment Strategy

### Phase 1: Preparation
1. **Backup Current Database**
   ```bash
   mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Test on Staging Environment**
   ```bash
   php artisan migrate:fresh
   ```

### Phase 2: Migration
1. **Run Comprehensive Migration**
   ```bash
   php artisan migrate:fresh
   ```

2. **Verify Table Creation**
   - All 23 required tables should be created
   - Foreign key constraints should be properly set
   - Indexes should be in place

### Phase 3: Data Migration
1. **Import Current Data**
   - Import data from backup for current tables
   - Verify data integrity

2. **Migrate Legacy Data** (if needed)
   - Create scripts to migrate patholgy → enhanced_reports
   - Create scripts to migrate income → payments
   - Create scripts to migrate login → users

### Phase 4: Testing
1. **Functional Testing**
   - Test user authentication
   - Test patient registration
   - Test lab request creation
   - Test shift management
   - Test report generation

2. **Data Integrity Testing**
   - Verify foreign key relationships
   - Check data consistency
   - Test all CRUD operations

## Foreign Key Relationships

The new schema includes these key relationships:
- `visits.patient_id` → `patient.id`
- `visits.lab_request_id` → `lab_requests.id`
- `visits.shift_id` → `shifts.id`
- `visit_tests.visit_id` → `visits.id`
- `visit_tests.lab_test_id` → `lab_tests.id`
- `invoices.lab_request_id` → `lab_requests.id`
- `payments.invoice_id` → `invoices.id`
- `samples.lab_request_id` → `lab_requests.id`
- `enhanced_reports.patient_id` → `patient.id`
- `shifts.staff_id` → `users.id`

## Rollback Plan

If issues occur:
1. **Stop Application**
2. **Restore from Backup**
   ```bash
   mysql -u username -p database_name < backup_file.sql
   ```
3. **Investigate Issues**
4. **Fix and Retry**

## Post-Deployment Tasks

1. **Update Application Code**
   - Remove references to legacy tables
   - Update any hardcoded table names
   - Test all functionality

2. **Monitor Performance**
   - Check query performance
   - Monitor foreign key constraint overhead
   - Optimize if needed

3. **Clean Up**
   - Remove legacy tables after confirming data migration
   - Archive old migration files
   - Update documentation

## Risk Mitigation

### High Risk
- **Data Loss**: Mitigated by comprehensive backup
- **Foreign Key Violations**: Mitigated by proper constraint design
- **Performance Issues**: Mitigated by proper indexing

### Medium Risk
- **Legacy Data Loss**: Mitigated by data migration scripts
- **Application Downtime**: Mitigated by staging testing

### Low Risk
- **Migration Failures**: Mitigated by rollback plan

## Success Criteria

✅ All required tables created with proper structure
✅ Foreign key constraints working correctly
✅ All current functionality working
✅ Data integrity maintained
✅ Performance acceptable
✅ No legacy table dependencies

## Support

If you encounter issues:
1. Check the migration logs
2. Verify database permissions
3. Check foreign key constraint violations
4. Review the rollback plan
5. Contact support if needed

---

**Created**: 2025-09-21
**Status**: Ready for Deployment
**Risk Level**: Medium (with proper backup and testing)
