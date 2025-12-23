-- ============================================================================
-- ERP SYSTEM DATABASE MIGRATION & FIXES
-- Date: 2025-12-23
-- Purpose: Sync all modules with database schema and add missing tables/columns
-- ============================================================================

USE erp_mkumbi1;

-- ============================================================================
-- 1. LEAVE MANAGEMENT - Add missing leave_balances table
-- ============================================================================

CREATE TABLE IF NOT EXISTS `leave_balances` (
  `balance_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `fiscal_year` int(11) NOT NULL,
  `entitled_days` int(11) NOT NULL DEFAULT 0,
  `used_days` int(11) NOT NULL DEFAULT 0,
  `balance_days` int(11) GENERATED ALWAYS AS (`entitled_days` - `used_days`) STORED,
  `carried_forward` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`balance_id`),
  UNIQUE KEY `unique_balance` (`company_id`, `employee_id`, `leave_type_id`, `fiscal_year`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_leave_type` (`leave_type_id`),
  KEY `idx_fiscal_year` (`fiscal_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. INVENTORY - Add missing columns to items table
-- ============================================================================

-- Check and add barcode column
SET @dbname = DATABASE();
SET @tablename = 'items';
SET @columnname = 'barcode';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' varchar(100) DEFAULT NULL AFTER item_code')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check and add sku column
SET @columnname = 'sku';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' varchar(100) DEFAULT NULL AFTER barcode')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Note: category_id already exists, but if code uses 'category' as string, 
-- we'll keep category_id and update code to use item_categories table

-- ============================================================================
-- 3. INVENTORY - Ensure item_categories table exists and is properly linked
-- ============================================================================

-- The item_categories table already exists in schema, ensure it's used correctly

-- ============================================================================
-- 4. PAYROLL - Ensure all payroll columns match
-- ============================================================================

-- payroll_details table already has correct structure:
-- basic_salary, allowances, overtime_pay, bonus, gross_salary (generated)
-- tax_amount, nssf_amount, nhif_amount, loan_deduction, other_deductions
-- total_deductions (generated), net_salary (generated)

-- ============================================================================
-- 5. ASSETS - Verify asset tables structure
-- ============================================================================

-- asset_categories: depreciation_method, useful_life_years, salvage_value_percentage (correct)
-- fixed_assets: depreciation_method, useful_life_years, salvage_value (correct)

-- ============================================================================
-- 6. LOANS - Verify loan_payments structure
-- ============================================================================

-- loan_payments: principal_paid, interest_paid, total_paid (correct)

-- ============================================================================
-- 7. CUSTOMERS - Verify customers table structure
-- ============================================================================

-- customers table has: guardian1_name, guardian1_relationship, guardian1_phone
-- guardian2_name, guardian2_relationship, guardian2_phone
-- next_of_kin_name, next_of_kin_phone, next_of_kin_relationship
-- id_number (correct - these columns exist)

-- ============================================================================
-- 8. EMPLOYEES - Verify employees table structure
-- ============================================================================

-- employees table links to users via user_id (correct)
-- users table has: first_name, middle_name, last_name, full_name (generated)

-- ============================================================================
-- 9. PETTY CASH - Verify status column ambiguity is resolved
-- ============================================================================

-- Both petty_cash_transactions and petty_cash_accounts have status column
-- Code should use table aliases (already fixed in approvals.php)

-- ============================================================================
-- 10. ADD INDEXES FOR PERFORMANCE
-- ============================================================================

-- Add indexes if they don't exist (using CREATE INDEX IF NOT EXISTS syntax)
-- Note: MySQL doesn't support IF NOT EXISTS for ALTER TABLE ADD INDEX, so we check first

-- leave_applications indexes
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_applications' AND INDEX_NAME = 'idx_employee_leave') > 0,
  'SELECT 1',
  'CREATE INDEX idx_employee_leave ON leave_applications (employee_id, leave_type_id)'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_applications' AND INDEX_NAME = 'idx_status') > 0,
  'SELECT 1',
  'CREATE INDEX idx_status ON leave_applications (status)'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- employees indexes
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND INDEX_NAME = 'idx_user_id') > 0,
  'SELECT 1',
  'CREATE INDEX idx_user_id ON employees (user_id)'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND INDEX_NAME = 'idx_department') > 0,
  'SELECT 1',
  'CREATE INDEX idx_department ON employees (department_id)'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND INDEX_NAME = 'idx_status') > 0,
  'SELECT 1',
  'CREATE INDEX idx_status ON employees (employment_status)'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- items indexes
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND INDEX_NAME = 'idx_category') > 0,
  'SELECT 1',
  'CREATE INDEX idx_category ON items (category_id)'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND INDEX_NAME = 'idx_code') > 0,
  'SELECT 1',
  'CREATE INDEX idx_code ON items (item_code)'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- payroll_details indexes
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_details' AND INDEX_NAME = 'idx_payroll_employee') > 0,
  'SELECT 1',
  'CREATE INDEX idx_payroll_employee ON payroll_details (payroll_id, employee_id)'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- loan_payments indexes
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loan_payments' AND INDEX_NAME = 'idx_loan') > 0,
  'SELECT 1',
  'CREATE INDEX idx_loan ON loan_payments (loan_id)'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loan_payments' AND INDEX_NAME = 'idx_date') > 0,
  'SELECT 1',
  'CREATE INDEX idx_date ON loan_payments (payment_date)'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 11. FIX DATA CONSISTENCY ISSUES
-- ============================================================================

-- Update any NULL values in critical fields
UPDATE `employees` SET `employment_status` = 'active' WHERE `employment_status` IS NULL;
UPDATE `items` SET `is_active` = 1 WHERE `is_active` IS NULL;
UPDATE `leave_applications` SET `status` = 'pending' WHERE `status` IS NULL;

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================

