# Category-Based Test System Guide

## Overview
Your laboratory now uses a **category-based test system** instead of predefined tests. This allows for flexible test naming and pricing during check-in.

## Main Categories
- **PATH** - Pathology tests (tissue examination and diagnosis)
- **CYTHO** - Cytology tests (cell examination and analysis)  
- **IHC** - Immunohistochemistry tests (protein detection in tissues)
- **REV** - Review tests (second opinion and consultation)
- **OTHER** - Other specialized tests not covered by main categories
- **PATH+IHC** - Combined Pathology and Immunohistochemistry tests

## How It Works

### 1. **Staff Check-In Process**
When a patient comes for testing:

1. **Select Category**: Choose from PATH, CYTHO, IHC, REV, OTHER, or PATH+IHC
2. **Enter Test Name**: Type the specific test name (e.g., "Breast Biopsy", "Pap Smear", "Ki-67 Staining")
3. **Set Price**: Enter the test price
4. **Apply Discount**: Optionally apply a percentage discount (e.g., 50% off)
5. **Calculate Total**: System calculates final price after discount

### 2. **Flexible Payment System**
- **Upfront Payment**: Patient can pay any amount upfront (minimum 50% of total)
- **Remaining Balance**: System calculates how much is left to pay
- **Payment Tracking**: Full payment history is maintained

### 3. **Example Workflow**

#### Example 1: Breast Biopsy
- **Category**: PATH
- **Test Name**: "Breast Biopsy - Right Breast"
- **Price**: $200.00
- **Discount**: 25% (for insurance)
- **Final Price**: $150.00
- **Upfront Payment**: $75.00
- **Remaining Balance**: $75.00

#### Example 2: Pap Smear
- **Category**: CYTHO
- **Test Name**: "Pap Smear with HPV Testing"
- **Price**: $80.00
- **Discount**: 0%
- **Final Price**: $80.00
- **Upfront Payment**: $80.00
- **Remaining Balance**: $0.00

#### Example 3: Combined Test
- **Category**: PATH+IHC
- **Test Name**: "Lung Biopsy with PD-L1 Staining"
- **Price**: $350.00
- **Discount**: 30% (for insurance)
- **Final Price**: $245.00
- **Upfront Payment**: $150.00
- **Remaining Balance**: $95.00

## API Endpoints

### Get Test Categories
```
GET /api/check-in/test-categories
```
Returns all available test categories.

### Create Visit with Custom Tests
```
POST /api/check-in/create-visit
```
Body:
```json
{
  "patient_id": 1,
  "tests": [
    {
      "test_category_id": 1,
      "custom_test_name": "Breast Biopsy",
      "custom_price": 200.00,
      "discount_percentage": 25
    }
  ],
  "upfront_payment": 75.00,
  "payment_method": "cash"
}
```

## Database Changes

### New Fields in `visit_tests` Table:
- `test_category_id` - Links to test category
- `custom_test_name` - The specific test name entered by staff
- `custom_price` - The price set by staff
- `discount_percentage` - Discount applied to the test
- `final_price` - Calculated price after discount

### New `test_categories` Table:
- `id` - Primary key
- `name` - Category name (PATH, CYTHO, etc.)
- `code` - Category code (path, cytho, etc.)
- `description` - Category description
- `is_active` - Whether category is active

## Benefits

1. **Flexibility**: No need to predefine every possible test
2. **Custom Pricing**: Staff can set prices based on complexity
3. **Discount Management**: Easy to apply insurance or other discounts
4. **Payment Flexibility**: Patients can pay as much as they want upfront
5. **Better Tracking**: Clear record of what was charged and paid

## Frontend Integration

The frontend check-in form now needs to:
1. Fetch test categories from `/api/check-in/test-categories`
2. Allow staff to select category and enter custom test name/price
3. Calculate discounts and final prices
4. Handle flexible upfront payments
5. Display remaining balance clearly

## Testing the System

You can test the new system by:
1. Going to the check-in page
2. Selecting a test category
3. Entering a custom test name and price
4. Applying a discount
5. Making a partial payment
6. Checking the receipt and remaining balance

This system gives you complete flexibility in how you handle tests and payments in your laboratory!
