# Deployment Instructions for Legacy Data Seeding

## Overview

All seed files have been moved to `backend/seedes/` directory for easy server deployment. All scripts have been updated to use the correct paths.

## Files to Upload to Server

### Required Files:
1. **backend/seedes/patient.json** (22.84 MB)
2. **backend/seedes/patholgy.json** (93.05 MB)
3. **backend/premium_fix_reports.php**
4. **backend/validate_all_data.php**
5. **backend/database/seeders/LegacyDataSeeder.php**

### Optional Files (for reference):
- `backend/PREMIUM_FIX_GUIDE.md`
- `backend/PRODUCTION_SEEDING_CHECKLIST.md`
- `backend/DEPLOYMENT_INSTRUCTIONS.md`

## Server Setup

### 1. Upload Files

Upload the entire `backend/` directory to your server, ensuring:
- `backend/seedes/patient.json` exists
- `backend/seedes/patholgy.json` exists
- All PHP scripts are in `backend/` directory

### 2. Verify File Permissions

```bash
cd /path/to/your/backend
chmod 644 seedes/*.json
chmod 755 premium_fix_reports.php
chmod 755 validate_all_data.php
```

### 3. Test File Paths

```bash
php test_paths.php
```

Expected output:
```
Testing file paths:

Patient file: /path/to/backend/seedes/patient.json
  Exists: YES ✓
  Size: 22.84 MB

Pathology file: /path/to/backend/seedes/patholgy.json
  Exists: YES ✓
  Size: 93.05 MB

✓ All files found! Ready for seeding.
```

## Seeding Process

### Step 1: Run Seeder

```bash
cd /path/to/your/backend
php artisan db:seed --class=LegacyDataSeeder
```

**Expected time:** 30-60 minutes (depending on server performance)

**What it does:**
- Creates all patients from `seedes/patient.json`
- Creates all lab requests
- Creates all visits
- Creates reports (some may be empty - will be fixed in step 2)

### Step 2: Fix Empty Reports

```bash
php premium_fix_reports.php
```

**Expected time:** 10-20 minutes

**What it does:**
- Loads pathology data from `seedes/patholgy.json`
- Finds and fixes all empty/null reports
- Handles oversized content automatically
- Validates relationships

**Expected output:**
```
========================================
PREMIUM FIX SCRIPT FOR REPORTS
========================================

Step 1: Loading pathology data...
Loaded 73982 records

Step 2: Creating pathology lookup map...
Created lookup map with 73873 entries

Step 3: Fixing reports with empty/null content...
  Processed 100 reports... (Fixed: 99, Not Found: 0)
  ...

Step 5: Validating relationships...
  ✓ All relationships are valid

========================================
FINAL SUMMARY
========================================
Reports checked: 1228
Reports fixed: 1227
Reports not found in pathology data: 1
Reports truncated (content too long): 1
Errors encountered: 0
Relationship issues: 0

✓ SUCCESS: All reports fixed successfully!
```

### Step 3: Validate All Data

```bash
php validate_all_data.php
```

**Expected time:** 1-2 minutes

**What it does:**
- Validates all relationships
- Checks for orphaned records
- Verifies data integrity

**Expected output:**
```
========================================
COMPREHENSIVE DATA VALIDATION
========================================

...

✓ SUCCESS: All data is valid and relationships are correct!
```

## Troubleshooting

### Files Not Found

**Error:** `ERROR: Pathology file not found at: ...`

**Solution:**
1. Verify files are uploaded: `ls -lh backend/seedes/`
2. Check file permissions: `chmod 644 backend/seedes/*.json`
3. Verify path in script matches your server structure

### Memory Errors

**Error:** `Allowed memory size exhausted`

**Solution:**
1. Increase PHP memory limit in `php.ini`:
   ```ini
   memory_limit = 512M
   ```
2. Or run with increased memory:
   ```bash
   php -d memory_limit=512M artisan db:seed --class=LegacyDataSeeder
   ```

### Database Connection Errors

**Error:** `SQLSTATE[HY000] [2002] No connection could be made`

**Solution:**
1. Check `.env` file database configuration
2. Verify database server is running
3. Check firewall rules allow database connections

### Path Issues

**Error:** `base_path() returns wrong path`

**Solution:**
- Ensure you're running commands from `backend/` directory
- Verify Laravel is properly installed
- Check `bootstrap/app.php` exists

## Post-Deployment Verification

After seeding, verify everything works:

1. **Check Reports Page:**
   - Visit `/reports` in your application
   - Verify reports are displaying
   - Check that pathology details are populated

2. **Check Patients Page:**
   - Visit `/patients` in your application
   - Verify patients are listed
   - Check that lab requests are linked

3. **Check Database:**
   ```sql
   SELECT COUNT(*) FROM patient;
   SELECT COUNT(*) FROM lab_requests;
   SELECT COUNT(*) FROM reports;
   SELECT COUNT(*) FROM visits;
   ```

## Notes

- All scripts use relative paths from `backend/` directory
- Files are located in `backend/seedes/` for easy deployment
- Scripts are safe to run multiple times
- Batch processing prevents memory issues
- Comprehensive error handling ensures reliability

## Support

If you encounter any issues:
1. Check the error messages carefully
2. Review the troubleshooting section above
3. Verify all files are uploaded correctly
4. Check file permissions
5. Review server logs: `storage/logs/laravel.log`

