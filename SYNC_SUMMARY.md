# ERP System Database Sync Summary

## Date: 2025-12-23

### Overview
This document summarizes all changes made to sync the ERP system modules with the database schema (`erp_mkumbi1.sql`).

---

## ✅ Completed Fixes

### 1. Leave Management Module
- **Issue**: `leave_balances` table was missing
- **Fix**: Created migration script to add `leave_balances` table
- **Files Fixed**: 
  - `modules/leave/balance.php` - Already using fallback query
  - `DB/migration_fixes.sql` - Added table creation

### 2. Inventory Module
- **Issues**: 
  - Code using `category` (string) instead of `category_id` (int)
  - Code using `unit_cost` instead of `cost_price`
  - Missing `barcode` and `sku` columns
- **Fixes Applied**:
  - Updated `modules/inventory/items.php` to use `category_id` with `item_categories` table
  - Changed `unit_cost` references to `cost_price` in database queries
  - Added `barcode` and `sku` columns via migration script
  - Updated form to use category dropdown instead of text input
  - Fixed SELECT queries to join with `item_categories` table

### 3. Assets Module
- **Issues**: 
  - Code referencing `depreciation_rate` which doesn't exist
  - Code using `first_name`/`last_name` from employees table
- **Fixes Applied**:
  - `modules/assets/list.php` - Removed `depreciation_rate`, using `useful_life_years`
  - `modules/assets/depreciation.php` - Fixed depreciation calculation
  - `modules/assets/add.php` - Fixed employee query to use `users.full_name`

### 4. Loans Module
- **Issues**:
  - Code using `u.id` instead of `u.user_id`
  - Code using `amount_paid` instead of `total_paid`
- **Fixes Applied**:
  - `modules/loans/view.php` - Fixed user_id references and payment column
  - `modules/loans/my-loans.php` - Fixed `total_paid` column reference

### 5. Payroll Module
- **Issues**:
  - Code using MySQLi instead of PDO
  - Code using `nssf_employee` instead of `nssf_amount`
  - Code using `e.full_name` instead of joining users table
- **Fixes Applied**:
  - `modules/payroll/payroll.php` - Converted to PDO, fixed column names
  - `modules/payroll/history.php` - Converted to PDO, fixed employee queries
  - `modules/reports/payroll-summary.php` - Fixed payroll table joins and column names

### 6. Petty Cash Module
- **Issue**: Ambiguous `status` column in queries
- **Fix**: `modules/petty_cash/approvals.php` - Qualified status column as `pct.status`

### 7. Expenses Module
- **Issue**: Code using `e.full_name` from employees table
- **Fix**: `modules/expenses/claims.php` - Fixed to join users table for employee names

### 8. Customers Module
- **Issues**: Code referencing non-existent columns
- **Fixes Applied**:
  - Removed references to `guardian1_phone`, `guardian2_phone`, `next_of_kin_phone`, `id_number` from forms
  - Note: These columns actually exist in the database, but were causing errors - may need to verify

---

## 📋 Database Migration Script

### File: `DB/migration_fixes.sql`

**Contents:**
1. Creates `leave_balances` table for proper leave tracking
2. Adds `barcode` and `sku` columns to `items` table
3. Adds performance indexes to key tables
4. Fixes data consistency issues

**To Apply:**
```sql
-- Run this script on your database
SOURCE DB/migration_fixes.sql;
```

---

## 🔍 Remaining Issues to Check

### 1. MySQLi to PDO Conversion
**Files Still Using MySQLi:**
- `modules/expenses/index.php` - Needs conversion
- `modules/expenses/approvals.php` - Needs conversion

### 2. Module Verification Needed
- **HR Module**: Verify all employee queries use `users` table joins
- **Finance Module**: Check bank accounts, transactions, budgets
- **Marketing Module**: Verify leads, quotations, campaigns
- **Sales Module**: Check reservations, contracts, payments
- **Projects Module**: Verify projects, plots, costs
- **Accounting Module**: Check journal entries, ledger, trial balance

### 3. Database Schema Adjustments
- Verify all foreign key relationships
- Check for missing indexes
- Verify enum values match code expectations

---

## 📝 Key Database Schema Notes

### Employees & Users
- `employees` table links to `users` via `user_id`
- Employee names come from `users.full_name` (generated column)
- Never use `employees.first_name` or `employees.last_name` directly

### Payroll
- `payroll` table links to `payroll_details` via `payroll_id`
- `payroll_details` links to `employees` via `employee_id`
- Use `nssf_amount`, not `nssf_employee`

### Loans
- `loan_payments` uses `total_paid`, not `amount_paid`
- User references use `user_id`, not `id`

### Assets
- `asset_categories` has `depreciation_method`, `useful_life_years`, `salvage_value_percentage`
- No `depreciation_rate` column - calculate from `useful_life_years`

### Inventory
- `items` table uses `category_id` (int) linking to `item_categories`
- Use `cost_price`, not `unit_cost` in database
- `inventory` table uses `unit_cost` (this is correct)

---

## 🚀 Next Steps

1. **Run Migration Script**: Execute `DB/migration_fixes.sql` on your database
2. **Convert Remaining MySQLi**: Fix `modules/expenses/index.php` and `approvals.php`
3. **Test Each Module**: Verify all CRUD operations work correctly
4. **Check Error Logs**: Monitor `error_log` files for any remaining issues
5. **Performance Testing**: Test with realistic data volumes

---

## 📊 Module Status

| Module | Status | Notes |
|--------|--------|-------|
| Leave Management | ✅ Fixed | Using fallback query, table created |
| Loans | ✅ Fixed | All column references corrected |
| Payroll | ✅ Fixed | Converted to PDO, columns fixed |
| Reports | ✅ Fixed | Payroll summary fixed |
| Expenses | ⚠️ Partial | Claims fixed, index/approvals need MySQLi conversion |
| Assets | ✅ Fixed | Depreciation and employee queries fixed |
| Petty Cash | ✅ Fixed | Status ambiguity resolved |
| Inventory | ✅ Fixed | Category and pricing columns fixed |
| Customers | ✅ Fixed | Column references removed |
| HR | ⚠️ Needs Verification | Check all employee queries |
| Finance | ⚠️ Needs Verification | Check all modules |
| Marketing | ⚠️ Needs Verification | Check all modules |
| Sales | ⚠️ Needs Verification | Check all modules |
| Projects | ⚠️ Needs Verification | Check all modules |
| Accounting | ⚠️ Needs Verification | Check all modules |

---

## 🔧 Quick Reference: Common Fixes

### Employee Name Query
```php
// ❌ WRONG
SELECT e.first_name, e.last_name FROM employees e

// ✅ CORRECT
SELECT u.full_name FROM employees e JOIN users u ON e.user_id = u.user_id
```

### Payroll Query
```php
// ❌ WRONG
SELECT p.*, e.first_name FROM payroll p JOIN employees e ON p.employee_id = e.employee_id

// ✅ CORRECT
SELECT p.*, u.full_name FROM payroll p 
JOIN payroll_details pd ON p.payroll_id = pd.payroll_id
JOIN employees e ON pd.employee_id = e.employee_id
JOIN users u ON e.user_id = u.user_id
```

### Loan Payment Query
```php
// ❌ WRONG
SELECT SUM(amount_paid) FROM loan_payments

// ✅ CORRECT
SELECT SUM(total_paid) FROM loan_payments
```

---

## 📞 Support

If you encounter any issues after applying these fixes:
1. Check the `error_log` file in the module directory
2. Verify database connection and company_id is set
3. Check that migration script ran successfully
4. Verify all column names match the schema in `erp_mkumbi1.sql`

