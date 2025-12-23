-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 23, 2025 at 08:02 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `erp_mkumbi1`
--

-- --------------------------------------------------------

--
-- Table structure for table `approval_actions`
--

CREATE TABLE `approval_actions` (
  `approval_action_id` int(11) NOT NULL,
  `approval_request_id` int(11) NOT NULL,
  `approval_level_id` int(11) NOT NULL,
  `action` enum('approved','rejected','returned','cancelled') NOT NULL,
  `action_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `acted_by` int(11) NOT NULL,
  `comments` text DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Approval actions log';

-- --------------------------------------------------------

--
-- Table structure for table `approval_levels`
--

CREATE TABLE `approval_levels` (
  `approval_level_id` int(11) NOT NULL,
  `workflow_id` int(11) NOT NULL,
  `level_number` int(11) NOT NULL,
  `level_name` varchar(100) NOT NULL,
  `approver_type` enum('role','user','any_manager') NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_required` tinyint(1) DEFAULT 1,
  `can_skip` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Approval workflow levels';

-- --------------------------------------------------------

--
-- Table structure for table `approval_requests`
--

CREATE TABLE `approval_requests` (
  `approval_request_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `workflow_id` int(11) NOT NULL,
  `request_number` varchar(50) NOT NULL,
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `reference_type` varchar(50) NOT NULL COMMENT 'payment, purchase_order, etc',
  `reference_id` int(11) NOT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `current_level` int(11) DEFAULT 1,
  `overall_status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Approval requests tracking';

-- --------------------------------------------------------

--
-- Table structure for table `approval_workflows`
--

CREATE TABLE `approval_workflows` (
  `workflow_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `workflow_name` varchar(200) NOT NULL,
  `workflow_code` varchar(50) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `applies_to` enum('payment','purchase_order','refund','contract','service_request','budget','expense','all') DEFAULT NULL,
  `min_amount` decimal(15,2) DEFAULT 0.00,
  `max_amount` decimal(15,2) DEFAULT NULL,
  `auto_approve_below` decimal(15,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Approval workflow definitions';

-- --------------------------------------------------------

--
-- Table structure for table `asset_categories`
--

CREATE TABLE `asset_categories` (
  `category_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `account_code` varchar(20) NOT NULL,
  `depreciation_account_code` varchar(20) NOT NULL,
  `depreciation_method` enum('straight_line','declining_balance','units_of_production') DEFAULT 'straight_line',
  `useful_life_years` int(11) DEFAULT 5,
  `salvage_value_percentage` decimal(5,2) DEFAULT 10.00,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_depreciation`
--

CREATE TABLE `asset_depreciation` (
  `depreciation_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `period_date` date NOT NULL,
  `depreciation_amount` decimal(15,2) NOT NULL,
  `accumulated_depreciation` decimal(15,2) NOT NULL,
  `book_value` decimal(15,2) NOT NULL,
  `journal_entry_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_maintenance`
--

CREATE TABLE `asset_maintenance` (
  `maintenance_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `maintenance_type` enum('preventive','corrective','upgrade','inspection') NOT NULL,
  `maintenance_date` date NOT NULL,
  `description` text NOT NULL,
  `cost` decimal(15,2) DEFAULT 0.00,
  `vendor_name` varchar(200) DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `performed_by` varchar(200) DEFAULT NULL,
  `downtime_hours` decimal(5,2) DEFAULT 0.00,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `total_hours` decimal(5,2) DEFAULT NULL,
  `overtime_hours` decimal(5,2) DEFAULT 0.00,
  `status` enum('present','absent','late','leave','holiday') DEFAULT 'present',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` bigint(20) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL COMMENT 'create, update, delete, view, login, logout',
  `module_name` varchar(100) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System audit trail';

-- --------------------------------------------------------

--
-- Table structure for table `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `bank_account_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `account_category` enum('bank','mobile_money') DEFAULT 'bank',
  `account_name` varchar(200) NOT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) NOT NULL,
  `mobile_provider` varchar(100) DEFAULT NULL,
  `mobile_number` varchar(50) DEFAULT NULL,
  `mobile_account_name` varchar(255) DEFAULT NULL,
  `branch_name` varchar(200) DEFAULT NULL,
  `bank_branch` varchar(100) DEFAULT NULL,
  `swift_code` varchar(50) DEFAULT NULL,
  `account_type` enum('checking','savings','business','escrow') DEFAULT 'business',
  `currency` varchar(10) DEFAULT 'TSH',
  `currency_code` varchar(3) DEFAULT 'TZS',
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `gl_account_id` int(11) DEFAULT NULL COMMENT 'Link to chart of accounts',
  `is_active` tinyint(1) DEFAULT 1,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Company bank accounts';

--
-- Dumping data for table `bank_accounts`
--

INSERT INTO `bank_accounts` (`bank_account_id`, `company_id`, `account_category`, `account_name`, `account_number`, `bank_name`, `mobile_provider`, `mobile_number`, `mobile_account_name`, `branch_name`, `bank_branch`, `swift_code`, `account_type`, `currency`, `currency_code`, `opening_balance`, `current_balance`, `gl_account_id`, `is_active`, `is_default`, `created_at`, `updated_at`, `created_by`) VALUES
(2, 3, 'bank', 'MKUMBI INVESTMENTS CO LTD', '01527TCG57200', 'CRDB Bank', '', '', '', 'Posta', NULL, '', 'business', 'TSH', 'TZS', 10000.00, 4510000.00, NULL, 1, 0, '2025-12-17 06:49:33', '2025-12-17 14:10:31', 9),
(3, 3, 'mobile_money', 'MKUMBI INVESTMENT CO LTD', '', '', 'Tigo Pesa', '0786654335', 'MKUMBI INVESTMENT CO LTD', '', NULL, '', 'business', 'TSH', 'TZS', 5000.00, 2005000.00, NULL, 1, 0, '2025-12-17 06:50:47', '2025-12-17 14:18:49', 9);

-- --------------------------------------------------------

--
-- Table structure for table `bank_statements`
--

CREATE TABLE `bank_statements` (
  `statement_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `bank_account_id` int(11) NOT NULL,
  `statement_number` varchar(50) DEFAULT NULL,
  `statement_date` date NOT NULL,
  `statement_period_start` date NOT NULL,
  `statement_period_end` date NOT NULL,
  `opening_balance` decimal(15,2) NOT NULL,
  `closing_balance` decimal(15,2) NOT NULL,
  `total_credits` decimal(15,2) DEFAULT 0.00,
  `total_debits` decimal(15,2) DEFAULT 0.00,
  `statement_file_path` varchar(255) DEFAULT NULL,
  `is_reconciled` tinyint(1) DEFAULT 0,
  `reconciliation_date` date DEFAULT NULL,
  `reconciled_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bank statements';

-- --------------------------------------------------------

--
-- Table structure for table `bank_transactions`
--

CREATE TABLE `bank_transactions` (
  `bank_transaction_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `bank_account_id` int(11) NOT NULL,
  `statement_id` int(11) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `value_date` date DEFAULT NULL,
  `transaction_type` enum('debit','credit') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `cheque_number` varchar(50) DEFAULT NULL,
  `running_balance` decimal(15,2) DEFAULT NULL,
  `is_reconciled` tinyint(1) DEFAULT 0,
  `reconciled_source_type` varchar(50) DEFAULT NULL,
  `reconciled_source_id` int(11) DEFAULT NULL,
  `reconciled_with_payment_id` int(11) DEFAULT NULL,
  `reconciliation_date` date DEFAULT NULL,
  `reconciliation_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bank transaction entries';

-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--

CREATE TABLE `budgets` (
  `budget_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `budget_name` varchar(200) NOT NULL,
  `budget_year` int(11) DEFAULT year(curdate()),
  `budget_period` enum('monthly','quarterly','annual') DEFAULT 'annual',
  `fiscal_year` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','approved','active','closed') DEFAULT 'draft',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `budget_lines`
--

CREATE TABLE `budget_lines` (
  `budget_line_id` int(11) NOT NULL,
  `budget_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `budgeted_amount` decimal(15,2) NOT NULL,
  `actual_amount` decimal(15,2) DEFAULT 0.00,
  `variance` decimal(15,2) GENERATED ALWAYS AS (`budgeted_amount` - `actual_amount`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `campaigns`
--

CREATE TABLE `campaigns` (
  `campaign_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `campaign_code` varchar(50) NOT NULL COMMENT 'Auto-generated: CMP-YYYY-XXXX',
  `campaign_name` varchar(200) NOT NULL,
  `campaign_type` enum('email','social_media','ppc','event','content','sms','print','radio','tv','other') NOT NULL DEFAULT 'email',
  `description` text DEFAULT NULL,
  `target_audience` varchar(500) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `budget` decimal(15,2) DEFAULT 0.00,
  `actual_spent` decimal(15,2) DEFAULT 0.00,
  `actual_cost` decimal(15,2) DEFAULT 0.00,
  `target_leads` int(11) DEFAULT NULL,
  `actual_leads` int(11) DEFAULT 0,
  `target_conversions` int(11) DEFAULT NULL,
  `actual_conversions` int(11) DEFAULT 0,
  `status` enum('draft','active','paused','completed','cancelled') DEFAULT 'draft',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Marketing campaigns tracking';

-- --------------------------------------------------------

--
-- Table structure for table `cash_transactions`
--

CREATE TABLE `cash_transactions` (
  `cash_transaction_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `transaction_number` varchar(50) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `transaction_type` enum('receipt','payment') DEFAULT 'receipt',
  `received_by` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chart_of_accounts`
--

CREATE TABLE `chart_of_accounts` (
  `account_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `account_code` varchar(20) NOT NULL,
  `account_name` varchar(200) NOT NULL,
  `account_type` enum('asset','liability','equity','revenue','expense') NOT NULL,
  `account_category` varchar(100) DEFAULT NULL,
  `parent_account_id` int(11) DEFAULT NULL COMMENT 'For sub-accounts',
  `account_level` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `is_control_account` tinyint(1) DEFAULT 0,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Chart of accounts';

--
-- Dumping data for table `chart_of_accounts`
--

INSERT INTO `chart_of_accounts` (`account_id`, `company_id`, `account_code`, `account_name`, `account_type`, `account_category`, `parent_account_id`, `account_level`, `is_active`, `is_control_account`, `opening_balance`, `current_balance`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 3, '1000', 'ASSETS', 'asset', 'Main Assets Account', NULL, 1, 1, 1, 0.00, 80490000.00, '2025-12-09 12:53:51', '2025-12-10 13:04:14', 6),
(2, 3, '1100', 'Current Assets', 'asset', 'Assets expected to be converted to cash within one year', 1, 2, 1, 1, 0.00, 10490000.00, '2025-12-09 12:53:51', '2025-12-09 13:08:11', 6),
(3, 3, '1110', 'Cash and Bank', 'asset', 'Cash on hand and in banks', 2, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(4, 3, '1111', 'Cash on Hand', 'asset', 'Physical cash available', 3, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(5, 3, '1112', 'Petty Cash', 'asset', 'Small cash fund for minor expenses', 3, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(6, 3, '1120', 'Bank Accounts', 'asset', 'Funds in bank accounts', 2, 3, 1, 1, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-10 10:32:42', 6),
(7, 3, '1130', 'Accounts Receivable', 'asset', 'Money owed by customers', 2, 3, 1, 1, 0.00, 10490000.00, '2025-12-09 12:53:51', '2025-12-09 13:08:11', 6),
(8, 3, '1131', 'Trade Debtors', 'asset', 'Customer accounts receivable', 7, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(9, 3, '1132', 'Plot Sales Receivable', 'asset', 'Outstanding plot payments', 7, 4, 1, 0, 0.00, 10490000.00, '2025-12-09 12:53:51', '2025-12-10 07:00:34', 6),
(10, 3, '1140', 'Inventory', 'asset', 'Stock and materials', 2, 3, 1, 1, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(11, 3, '1150', 'Prepaid Expenses', 'asset', 'Expenses paid in advance', 2, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(12, 3, '1200', 'Fixed Assets', 'asset', 'Long-term tangible assets', 1, 2, 1, 1, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(13, 3, '1210', 'Land and Buildings', 'asset', 'Property investments', 12, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(14, 3, '1220', 'Vehicles', 'asset', 'Company vehicles', 12, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(15, 3, '1230', 'Furniture and Fixtures', 'asset', 'Office furniture and equipment', 12, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(16, 3, '1240', 'Computer Equipment', 'asset', 'IT hardware and equipment', 12, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(17, 3, '1250', 'Accumulated Depreciation', 'asset', 'Depreciation of fixed assets', 12, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(18, 3, '1300', 'Development Properties', 'asset', 'Land development projects', 1, 2, 1, 1, 0.00, 70000000.00, '2025-12-09 12:53:51', '2025-12-10 13:04:14', 6),
(19, 3, '1310', 'Land Under Development', 'asset', 'Land being developed for sale', 18, 3, 1, 0, 0.00, 60000000.00, '2025-12-09 12:53:51', '2025-12-10 13:04:14', 6),
(20, 3, '1320', 'Development Costs', 'asset', 'Infrastructure and improvements', 18, 3, 1, 0, 0.00, 10000000.00, '2025-12-09 12:53:51', '2025-12-10 13:04:14', 6),
(21, 3, '2000', 'LIABILITIES', 'liability', 'Main Liabilities Account', NULL, 1, 1, 1, 0.00, 618000.00, '2025-12-09 12:53:51', '2025-12-09 13:08:11', 6),
(22, 3, '2100', 'Current Liabilities', 'liability', 'Obligations due within one year', 21, 2, 1, 1, 0.00, 108000.00, '2025-12-09 12:53:51', '2025-12-09 13:08:11', 6),
(23, 3, '2110', 'Accounts Payable', 'liability', 'Money owed to suppliers', 22, 3, 1, 1, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(24, 3, '2111', 'Trade Creditors', 'liability', 'Supplier accounts payable', 23, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(25, 3, '2112', 'Accrued Expenses', 'liability', 'Expenses incurred but not paid', 23, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(26, 3, '2120', 'Tax Payable', 'liability', 'Taxes owed to authorities', 22, 3, 1, 1, 0.00, 108000.00, '2025-12-09 12:53:51', '2025-12-09 13:08:11', 6),
(27, 3, '2121', 'VAT Payable', 'liability', 'Value Added Tax owed', 26, 4, 1, 0, 0.00, 108000.00, '2025-12-09 12:53:51', '2025-12-10 07:00:34', 6),
(28, 3, '2122', 'Income Tax Payable', 'liability', 'Corporate income tax owed', 26, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(29, 3, '2123', 'Withholding Tax Payable', 'liability', 'Withholding tax to be remitted', 26, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(30, 3, '2130', 'Salaries Payable', 'liability', 'Unpaid employee salaries', 22, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(31, 3, '2140', 'Commission Payable', 'liability', 'Unpaid sales commissions', 22, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(32, 3, '2200', 'Long-term Liabilities', 'liability', 'Obligations due after one year', 21, 2, 1, 1, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(33, 3, '2210', 'Bank Loans', 'liability', 'Long-term bank financing', 32, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(34, 3, '2220', 'Mortgages Payable', 'liability', 'Long-term property loans', 32, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(35, 3, '2300', 'Customer Deposits', 'liability', 'Advance payments from customers', 21, 2, 1, 1, 0.00, 510000.00, '2025-12-09 12:53:51', '2025-12-09 13:08:11', 6),
(36, 3, '2310', 'Plot Reservation Deposits', 'liability', 'Customer down payments', 35, 3, 1, 0, 0.00, 510000.00, '2025-12-09 12:53:51', '2025-12-10 07:00:34', 6),
(37, 3, '3000', 'EQUITY', 'equity', 'Main Equity Account', NULL, 1, 1, 1, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(38, 3, '3100', 'Owner\'s Equity', 'equity', 'Owner\'s investment and retained earnings', 37, 2, 1, 1, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(39, 3, '3110', 'Share Capital', 'equity', 'Invested capital', 38, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(40, 3, '3120', 'Retained Earnings', 'equity', 'Accumulated profits', 38, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(41, 3, '3130', 'Current Year Earnings', 'equity', 'Profit/Loss for current year', 38, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(42, 3, '3140', 'Drawings', 'equity', 'Owner withdrawals', 38, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(43, 3, '4000', 'REVENUE', 'revenue', 'Main Revenue Account', NULL, 1, 1, 1, 0.00, 1030000.00, '2025-12-09 12:53:51', '2025-12-09 13:08:11', 6),
(44, 3, '4100', 'Sales Revenue', 'revenue', 'Income from sales', 43, 2, 1, 1, 0.00, 1030000.00, '2025-12-09 12:53:51', '2025-12-09 13:08:11', 6),
(45, 3, '4110', 'Plot Sales', 'revenue', 'Revenue from plot sales', 44, 3, 1, 0, 0.00, 1030000.00, '2025-12-09 12:53:51', '2025-12-10 07:00:34', 6),
(46, 3, '4120', 'Service Revenue', 'revenue', 'Income from services', 44, 3, 1, 1, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(47, 3, '4121', 'Survey Services', 'revenue', 'Land survey income', 46, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(48, 3, '4122', 'Legal Services', 'revenue', 'Title processing income', 46, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(49, 3, '4123', 'Consultation Services', 'revenue', 'Consulting fees', 46, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(50, 3, '4130', 'Commission Income', 'revenue', 'Referral commissions earned', 44, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(51, 3, '4200', 'Other Income', 'revenue', 'Non-operating income', 43, 2, 1, 1, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(52, 3, '4210', 'Interest Income', 'revenue', 'Interest earned', 51, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(53, 3, '4220', 'Rental Income', 'revenue', 'Property rental income', 51, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(54, 3, '5000', 'EXPENSES', 'expense', 'Main Expense Account', NULL, 1, 1, 1, 0.00, 240000.00, '2025-12-09 12:53:51', '2025-12-09 13:08:11', 6),
(55, 3, '5100', 'Cost of Sales', 'expense', 'Direct costs of sales', 54, 2, 1, 1, 0.00, 240000.00, '2025-12-09 12:53:51', '2025-12-09 13:08:11', 6),
(56, 3, '5110', 'Land Purchase Costs', 'expense', 'Cost of land acquisition', 55, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(57, 3, '5120', 'Development Costs', 'expense', 'Infrastructure and improvements', 55, 3, 1, 1, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(58, 3, '5121', 'Survey and Mapping', 'expense', 'Land survey expenses', 57, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(59, 3, '5122', 'Legal and Title Processing', 'expense', 'Documentation costs', 57, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(60, 3, '5123', 'Infrastructure Development', 'expense', 'Roads, water, electricity', 57, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(61, 3, '5130', 'Commission Expenses', 'expense', 'Sales commission paid', 55, 3, 1, 0, 0.00, 240000.00, '2025-12-09 12:53:51', '2025-12-10 07:00:34', 6),
(62, 3, '6000', 'Operating Expenses', 'expense', 'General operating costs', 54, 2, 1, 1, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(63, 3, '6100', 'Administrative Expenses', 'expense', 'General admin costs', 62, 3, 1, 1, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(64, 3, '6110', 'Salaries and Wages', 'expense', 'Employee compensation', 63, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(65, 3, '6120', 'Office Rent', 'expense', 'Office space rental', 63, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(66, 3, '6130', 'Utilities', 'expense', 'Electricity, water, internet', 63, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(67, 3, '6140', 'Office Supplies', 'expense', 'Stationery and supplies', 63, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(68, 3, '6150', 'Telephone and Communication', 'expense', 'Phone and internet', 63, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(69, 3, '6200', 'Marketing Expenses', 'expense', 'Marketing and advertising', 62, 3, 1, 1, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(70, 3, '6210', 'Advertising', 'expense', 'Promotional campaigns', 69, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(71, 3, '6220', 'Marketing Materials', 'expense', 'Brochures and promotional items', 69, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(72, 3, '6230', 'Website and Digital Marketing', 'expense', 'Online marketing costs', 69, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(73, 3, '6300', 'Professional Fees', 'expense', 'External services', 62, 3, 1, 1, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(74, 3, '6310', 'Legal Fees', 'expense', 'Attorney and legal services', 73, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(75, 3, '6320', 'Accounting Fees', 'expense', 'Bookkeeping and audit fees', 73, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(76, 3, '6330', 'Consulting Fees', 'expense', 'Professional consultants', 73, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(77, 3, '6400', 'Transportation', 'expense', 'Vehicle and travel costs', 62, 3, 1, 1, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(78, 3, '6410', 'Vehicle Fuel', 'expense', 'Fuel expenses', 77, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(79, 3, '6420', 'Vehicle Maintenance', 'expense', 'Repairs and servicing', 77, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(80, 3, '6430', 'Travel Expenses', 'expense', 'Business travel', 77, 4, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(81, 3, '6500', 'Insurance', 'expense', 'Insurance premiums', 62, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(82, 3, '6600', 'Bank Charges', 'expense', 'Banking fees and charges', 62, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(83, 3, '6700', 'Depreciation', 'expense', 'Asset depreciation', 62, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(84, 3, '7000', 'Financial Expenses', 'expense', 'Interest and finance costs', 54, 2, 1, 1, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(85, 3, '7100', 'Interest Expense', 'expense', 'Interest on loans', 84, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(86, 3, '7200', 'Bank Charges', 'expense', 'Banking fees', 84, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(87, 3, '8000', 'Other Expenses', 'expense', 'Miscellaneous expenses', 54, 2, 1, 1, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(88, 3, '8100', 'Repairs and Maintenance', 'expense', 'General repairs', 87, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(89, 3, '8200', 'Donations and Contributions', 'expense', 'Charitable giving', 87, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6),
(90, 3, '8300', 'Miscellaneous Expenses', 'expense', 'Other costs', 87, 3, 1, 0, 0.00, 0.00, '2025-12-09 12:53:51', '2025-12-09 12:53:51', 6);

-- --------------------------------------------------------

--
-- Table structure for table `cheque_transactions`
--

CREATE TABLE `cheque_transactions` (
  `cheque_transaction_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `cheque_number` varchar(50) NOT NULL,
  `cheque_date` date NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `branch_name` varchar(255) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payee_name` varchar(255) DEFAULT NULL,
  `status` enum('pending','cleared','bounced','cancelled') DEFAULT 'pending',
  `cleared_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissions`
--

CREATE TABLE `commissions` (
  `commission_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `recipient_type` enum('user','external','consultant') NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'If recipient is a system user',
  `recipient_name` varchar(200) NOT NULL,
  `recipient_phone` varchar(50) DEFAULT NULL,
  `commission_type` enum('sales','referral','consultant','marketing','other') DEFAULT 'sales',
  `commission_percentage` decimal(5,2) DEFAULT NULL,
  `commission_amount` decimal(15,2) NOT NULL,
  `payment_status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `paid_date` date DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `commission_tier` varchar(50) DEFAULT NULL COMMENT 'Bronze, Silver, Gold, etc',
  `base_commission_rate` decimal(5,2) DEFAULT NULL,
  `bonus_commission_rate` decimal(5,2) DEFAULT 0.00,
  `total_commission_rate` decimal(5,2) GENERATED ALWAYS AS (`base_commission_rate` + `bonus_commission_rate`) STORED,
  `payment_method` enum('cash','bank_transfer','cheque','mobile_money') DEFAULT NULL,
  `payment_account_number` varchar(100) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Commission tracking for sales and referrals';

-- --------------------------------------------------------

--
-- Table structure for table `commission_payments`
--

CREATE TABLE `commission_payments` (
  `commission_payment_id` int(11) NOT NULL,
  `commission_id` int(11) NOT NULL,
  `payment_number` varchar(50) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `payment_reference` varchar(255) DEFAULT NULL,
  `amount_paid` decimal(15,2) NOT NULL,
  `payment_notes` text DEFAULT NULL,
  `paid_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commission_payment_requests`
--

CREATE TABLE `commission_payment_requests` (
  `commission_payment_request_id` int(11) NOT NULL,
  `commission_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `request_number` varchar(50) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `payment_reference` varchar(255) DEFAULT NULL,
  `amount_to_pay` decimal(15,2) NOT NULL,
  `payment_notes` text DEFAULT NULL,
  `request_status` enum('pending_approval','approved','rejected') DEFAULT 'pending_approval',
  `requested_by` int(11) NOT NULL,
  `requested_at` datetime NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commission_structures`
--

CREATE TABLE `commission_structures` (
  `commission_structure_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `structure_name` varchar(100) NOT NULL,
  `structure_code` varchar(20) DEFAULT NULL,
  `commission_type` enum('sales','referral','consultant','marketing','collection','performance') NOT NULL,
  `is_tiered` tinyint(1) DEFAULT 0,
  `base_rate` decimal(5,2) NOT NULL COMMENT 'Base percentage',
  `min_sales_amount` decimal(15,2) DEFAULT NULL,
  `target_amount` decimal(15,2) DEFAULT NULL,
  `payment_frequency` enum('immediate','monthly','quarterly','on_completion') DEFAULT 'monthly',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Commission rate structures';

-- --------------------------------------------------------

--
-- Table structure for table `commission_tiers`
--

CREATE TABLE `commission_tiers` (
  `commission_tier_id` int(11) NOT NULL,
  `commission_structure_id` int(11) NOT NULL,
  `tier_name` varchar(50) NOT NULL COMMENT 'Bronze, Silver, Gold, Platinum',
  `tier_level` int(11) NOT NULL,
  `min_amount` decimal(15,2) NOT NULL,
  `max_amount` decimal(15,2) DEFAULT NULL,
  `commission_rate` decimal(5,2) NOT NULL,
  `bonus_rate` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tiered commission rates';

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `company_id` int(11) NOT NULL,
  `company_code` varchar(20) NOT NULL COMMENT 'Unique company identifier',
  `company_name` varchar(200) NOT NULL,
  `registration_number` varchar(100) DEFAULT NULL,
  `tax_identification_number` varchar(100) DEFAULT NULL COMMENT 'TIN',
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `mobile` varchar(50) DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `physical_address` text DEFAULT NULL,
  `postal_address` varchar(200) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Tanzania',
  `logo_path` varchar(255) DEFAULT NULL,
  `primary_color` varchar(20) DEFAULT '#007bff',
  `secondary_color` varchar(20) DEFAULT '#6c757d',
  `fiscal_year_start` date DEFAULT NULL,
  `fiscal_year_end` date DEFAULT NULL,
  `currency_code` varchar(3) DEFAULT 'TZS',
  `date_format` varchar(20) DEFAULT 'Y-m-d',
  `timezone` varchar(50) DEFAULT 'Africa/Dar_es_Salaam',
  `subscription_plan` enum('trial','basic','professional','enterprise') DEFAULT 'trial',
  `subscription_start_date` date DEFAULT NULL,
  `subscription_end_date` date DEFAULT NULL,
  `max_users` int(11) DEFAULT 5,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Multi-tenant company registration';

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`company_id`, `company_code`, `company_name`, `registration_number`, `tax_identification_number`, `email`, `phone`, `mobile`, `website`, `physical_address`, `postal_address`, `city`, `region`, `country`, `logo_path`, `primary_color`, `secondary_color`, `fiscal_year_start`, `fiscal_year_end`, `currency_code`, `date_format`, `timezone`, `subscription_plan`, `subscription_start_date`, `subscription_end_date`, `max_users`, `is_active`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 'ACME', 'Acme Corp', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Tanzania', NULL, '#007bff', '#6c757d', NULL, NULL, 'TZS', 'Y-m-d', 'Africa/Dar_es_Salaam', 'trial', NULL, NULL, 5, 1, '2025-11-29 07:56:46', '2025-11-29 07:56:46', NULL),
(2, 'BETA', 'Beta Solutions', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Tanzania', NULL, '#007bff', '#6c757d', NULL, NULL, 'TZS', 'Y-m-d', 'Africa/Dar_es_Salaam', 'trial', NULL, NULL, 5, 1, '2025-11-29 07:56:46', '2025-11-29 07:56:46', NULL),
(3, 'GAMMA', 'Mkumbi investment company ltd', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Tanzania', NULL, '#007bff', '#6c757d', NULL, NULL, 'TZS', 'Y-m-d', 'Africa/Dar_es_Salaam', 'enterprise', '2025-12-12', '2026-12-12', 100, 1, '2025-11-29 07:56:46', '2025-12-12 08:29:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `company_loans`
--

CREATE TABLE `company_loans` (
  `loan_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `loan_number` varchar(50) NOT NULL,
  `lender_name` varchar(200) NOT NULL,
  `loan_type` enum('term_loan','overdraft','line_of_credit','mortgage','other') NOT NULL,
  `account_code` varchar(20) DEFAULT '2210',
  `loan_date` date NOT NULL,
  `loan_amount` decimal(15,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `repayment_term_months` int(11) NOT NULL,
  `monthly_payment` decimal(15,2) NOT NULL,
  `maturity_date` date NOT NULL,
  `collateral_description` text DEFAULT NULL,
  `collateral_value` decimal(15,2) DEFAULT NULL,
  `principal_outstanding` decimal(15,2) NOT NULL,
  `interest_outstanding` decimal(15,2) DEFAULT 0.00,
  `total_outstanding` decimal(15,2) NOT NULL,
  `total_paid` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','fully_paid','defaulted','restructured') DEFAULT 'active',
  `contact_person` varchar(200) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_loan_payments`
--

CREATE TABLE `company_loan_payments` (
  `payment_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `principal_paid` decimal(15,2) NOT NULL,
  `interest_paid` decimal(15,2) NOT NULL,
  `total_paid` decimal(15,2) NOT NULL,
  `payment_method` enum('bank_transfer','cheque','direct_debit') NOT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `payment_reference` varchar(100) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cost_categories`
--

CREATE TABLE `cost_categories` (
  `cost_category_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cost_categories`
--

INSERT INTO `cost_categories` (`cost_category_id`, `company_id`, `category_name`, `description`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'Survey & Mapping', 'Land surveying, topographic surveys, GPS mapping, boundary surveys', 1, NULL, '2025-11-29 10:49:46', '2025-11-29 10:49:46'),
(2, 1, 'Legal Fees', 'Legal documentation, title deed processing, attorney fees, notary services', 1, NULL, '2025-11-29 10:49:46', '2025-11-29 10:49:46'),
(3, 1, 'Infrastructure Development', 'Road construction, drainage systems, sewerage, water supply', 1, NULL, '2025-11-29 10:49:46', '2025-11-29 10:49:46'),
(4, 1, 'Marketing & Sales', 'Advertising, promotional materials, sales commissions, showroom costs', 1, NULL, '2025-11-29 10:49:46', '2025-11-29 10:49:46'),
(5, 1, 'Administrative', 'Office supplies, utilities, staff salaries, general administration', 1, NULL, '2025-11-29 10:49:46', '2025-11-29 10:49:46'),
(6, 1, 'Land Clearing', 'Vegetation removal, demolition, site preparation, grading', 1, NULL, '2025-11-29 10:49:46', '2025-11-29 10:49:46'),
(7, 1, 'Utilities Installation', 'Electricity connection, water connection, street lighting', 1, NULL, '2025-11-29 10:49:46', '2025-11-29 10:49:46'),
(8, 1, 'Security & Fencing', 'Perimeter fencing, security systems, guard services', 1, NULL, '2025-11-29 10:49:46', '2025-11-29 10:49:46'),
(9, 1, 'Environmental Compliance', 'Environmental impact assessments, permits, mitigation measures', 1, NULL, '2025-11-29 10:49:46', '2025-11-29 10:49:46'),
(10, 1, 'Professional Services', 'Consultants, architects, engineers, project managers', 1, NULL, '2025-11-29 10:49:46', '2025-11-29 10:49:46'),
(11, 1, 'Survey & Mapping', 'Land surveying, topographic surveys, GPS mapping, boundary surveys', 1, NULL, '2025-11-29 10:51:20', '2025-11-29 10:51:20'),
(12, 1, 'Legal Fees', 'Legal documentation, title deed processing, attorney fees, notary services', 1, NULL, '2025-11-29 10:51:20', '2025-11-29 10:51:20'),
(13, 1, 'Infrastructure Development', 'Road construction, drainage systems, sewerage, water supply', 1, NULL, '2025-11-29 10:51:20', '2025-11-29 10:51:20'),
(14, 1, 'Marketing & Sales', 'Advertising, promotional materials, sales commissions, showroom costs', 1, NULL, '2025-11-29 10:51:20', '2025-11-29 10:51:20'),
(15, 1, 'Administrative', 'Office supplies, utilities, staff salaries, general administration', 1, NULL, '2025-11-29 10:51:20', '2025-11-29 10:51:20'),
(16, 1, 'Land Clearing', 'Vegetation removal, demolition, site preparation, grading', 1, NULL, '2025-11-29 10:51:20', '2025-11-29 10:51:20'),
(17, 1, 'Utilities Installation', 'Electricity connection, water connection, street lighting', 1, NULL, '2025-11-29 10:51:20', '2025-11-29 10:51:20'),
(18, 1, 'Security & Fencing', 'Perimeter fencing, security systems, guard services', 1, NULL, '2025-11-29 10:51:20', '2025-11-29 10:51:20'),
(19, 1, 'Environmental Compliance', 'Environmental impact assessments, permits, mitigation measures', 1, NULL, '2025-11-29 10:51:20', '2025-11-29 10:51:20'),
(20, 1, 'Professional Services', 'Consultants, architects, engineers, project managers', 1, NULL, '2025-11-29 10:51:20', '2025-11-29 10:51:20'),
(21, 1, 'Land Purchase', 'Actual land acquisition costs, transfer fees', 1, NULL, '2025-11-29 10:51:20', '2025-11-29 10:51:20'),
(22, 1, 'Permits & Licenses', 'Government permits, approvals, license fees', 1, NULL, '2025-11-29 10:51:20', '2025-11-29 10:51:20'),
(23, 1, 'Site Development', 'Leveling, terracing, soil improvement', 1, NULL, '2025-11-29 10:51:20', '2025-11-29 10:51:20'),
(24, 1, 'Landscaping', 'Gardens, trees, recreational areas', 1, NULL, '2025-11-29 10:51:20', '2025-11-29 10:51:20'),
(25, 1, 'Documentation', 'Printing, copying, filing, archival costs', 1, NULL, '2025-11-29 10:51:20', '2025-11-29 10:51:20');

-- --------------------------------------------------------

--
-- Table structure for table `creditors`
--

CREATE TABLE `creditors` (
  `creditor_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `creditor_type` enum('supplier','contractor','consultant','employee','other') NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `creditor_name` varchar(200) NOT NULL,
  `contact_person` varchar(200) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `total_amount_owed` decimal(15,2) DEFAULT 0.00,
  `amount_paid` decimal(15,2) DEFAULT 0.00,
  `outstanding_balance` decimal(15,2) GENERATED ALWAYS AS (`total_amount_owed` - `amount_paid`) STORED,
  `credit_days` int(11) DEFAULT 30,
  `status` enum('active','settled','overdue','disputed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Creditors/Accounts Payable';

-- --------------------------------------------------------

--
-- Table structure for table `creditor_invoices`
--

CREATE TABLE `creditor_invoices` (
  `creditor_invoice_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `creditor_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `invoice_amount` decimal(15,2) NOT NULL,
  `amount_paid` decimal(15,2) DEFAULT 0.00,
  `balance_due` decimal(15,2) GENERATED ALWAYS AS (`invoice_amount` - `amount_paid`) STORED,
  `purchase_order_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','partially_paid','paid','overdue') DEFAULT 'pending',
  `payment_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Creditor invoices';

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `full_name` varchar(300) GENERATED ALWAYS AS (concat(`first_name`,' ',ifnull(concat(`middle_name`,' '),''),`last_name`)) STORED,
  `email` varchar(150) DEFAULT NULL,
  `phone1` varchar(50) DEFAULT NULL,
  `phone2` varchar(50) DEFAULT NULL,
  `national_id` varchar(50) DEFAULT NULL,
  `passport_number` varchar(50) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `ward` varchar(100) DEFAULT NULL COMMENT 'Aina ya kitambuliaho',
  `village` varchar(100) DEFAULT NULL COMMENT 'Namba ya kitambuliaho',
  `street_address` text DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `guardian1_name` varchar(200) DEFAULT NULL,
  `guardian1_relationship` varchar(100) DEFAULT NULL,
  `guardian1_phone` varchar(50) DEFAULT NULL,
  `guardian2_name` varchar(200) DEFAULT NULL,
  `guardian2_relationship` varchar(100) DEFAULT NULL,
  `guardian2_phone` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `customer_type` enum('individual','company') DEFAULT 'individual' COMMENT 'individual or company',
  `id_number` varchar(50) DEFAULT NULL,
  `tin_number` varchar(50) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT 'Tanzanian',
  `occupation` varchar(100) DEFAULT NULL,
  `next_of_kin_name` varchar(150) DEFAULT NULL,
  `next_of_kin_phone` varchar(20) DEFAULT NULL,
  `next_of_kin_relationship` varchar(50) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `alternative_phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `postal_address` text DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer/buyer information';

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `company_id`, `first_name`, `middle_name`, `last_name`, `email`, `phone1`, `phone2`, `national_id`, `passport_number`, `region`, `district`, `ward`, `village`, `street_address`, `gender`, `profile_picture`, `guardian1_name`, `guardian1_relationship`, `guardian1_phone`, `guardian2_name`, `guardian2_relationship`, `guardian2_phone`, `is_active`, `created_at`, `updated_at`, `created_by`, `customer_type`, `id_number`, `tin_number`, `nationality`, `occupation`, `next_of_kin_name`, `next_of_kin_phone`, `next_of_kin_relationship`, `phone`, `alternative_phone`, `address`, `postal_address`, `notes`) VALUES
(1, 3, 'LAZARO', 'MPUYA', 'MATALANGE', 'matalange@gmail.com', NULL, NULL, '19760218-16113-00002-20', '', 'Dar es Salaam', 'Kinondoni', 'Kawe', 'Kawe Wazo', 'mkoani', 'male', NULL, 'OLIVER SCHOLA MATALANGE', 'parent', NULL, 'SCHOLASTICA MATALANGE', 'child', NULL, 1, '2025-12-12 14:21:24', '2025-12-12 14:21:24', 9, 'individual', '', '', 'Tanzanian', '', 'OLIVER SCHOLA MATALANGE', '0767117377', 'parent', '0685767670', '0685767670', NULL, 'P.O.BOX 25423', '');

-- --------------------------------------------------------

--
-- Table structure for table `debtors`
--

CREATE TABLE `debtors` (
  `debtor_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `debtor_type` enum('customer','plot_buyer','service_client','other') NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `debtor_name` varchar(200) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `total_amount_due` decimal(15,2) DEFAULT 0.00,
  `amount_received` decimal(15,2) DEFAULT 0.00,
  `outstanding_balance` decimal(15,2) GENERATED ALWAYS AS (`total_amount_due` - `amount_received`) STORED,
  `current_due` decimal(15,2) DEFAULT 0.00,
  `days_30` decimal(15,2) DEFAULT 0.00,
  `days_60` decimal(15,2) DEFAULT 0.00,
  `days_90_plus` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','settled','overdue','legal_action') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Debtors/Accounts Receivable';

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `department_code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `manager_user_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `company_id`, `department_name`, `department_code`, `description`, `manager_user_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Human Resources', 'HR', 'Recruitment, training, and employee relations', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(2, 1, 'Finance', 'FIN', 'Financial planning, accounting, and reporting', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(3, 1, 'Accounting', 'ACC', 'Bookkeeping, payroll, and financial records', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(4, 1, 'Information Technology', 'IT', 'IT infrastructure and support', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(5, 1, 'Operations', 'OPS', 'Day-to-day business operations', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(6, 1, 'Sales', 'SLS', 'Revenue generation and sales management', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(7, 1, 'Marketing', 'MKT', 'Brand management and promotions', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(8, 1, 'Advertising', 'ADV', 'Advertising campaigns and media buying', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(9, 1, 'Customer Service', 'CS', 'Customer support and relations', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(10, 1, 'Procurement', 'PRC', 'Purchasing and supplier management', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(11, 1, 'Logistics', 'LOG', 'Transportation and distribution', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(12, 1, 'Inventory Management', 'INV', 'Stock control and warehousing', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(13, 1, 'Warehouse', 'WH', 'Warehouse operations and storage', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(14, 1, 'Administration', 'ADM', 'General office administration', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(15, 1, 'Facilities', 'FAC', 'Office maintenance and facilities', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(16, 1, 'Office Management', 'OFF', 'Office operations and coordination', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(17, 1, 'Records Management', 'REC', 'Document and records keeping', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(18, 1, 'Research & Development', 'RND', 'Product innovation and research', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(19, 1, 'Product Development', 'PRD', 'Product design and development', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(20, 1, 'Quality Assurance', 'QUA', 'Quality control and standards', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(21, 1, 'Production', 'PRO', 'Manufacturing and production processes', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(22, 1, 'Legal', 'LEG', 'Legal affairs and contracts', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(23, 1, 'Compliance', 'CMP', 'Regulatory compliance and audits', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(24, 1, 'Internal Audit', 'AUD', 'Internal audits and controls', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(25, 1, 'Training & Development', 'TRN', 'Employee training programs', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(26, 1, 'Recruitment', 'RCT', 'Talent acquisition and hiring', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(27, 1, 'Benefits Administration', 'BEN', 'Employee benefits management', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(28, 1, 'Project Management Office', 'PMO', 'Project planning and oversight', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(29, 1, 'Projects', 'PJM', 'Project execution and delivery', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(30, 1, 'Business Development', 'BD', 'New business opportunities', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(31, 1, 'Strategy', 'STR', 'Business strategy and planning', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(32, 1, 'Risk Management', 'RIS', 'Risk assessment and mitigation', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(33, 1, 'Communications', 'COM', 'Internal and external communications', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(34, 1, 'Public Relations', 'PR', 'Media relations and reputation', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(35, 1, 'Executive Office', 'CEO', 'Top-level management and strategy', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(36, 1, 'Finance Office', 'CFO', 'Financial leadership', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(37, 1, 'Operations Office', 'COO', 'Operational leadership', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(38, 1, 'Security', 'SEC', 'Physical and information security', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(39, 1, 'Corporate Social Responsibility', 'CSR', 'Sustainability and community', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23'),
(40, 1, 'Environmental Management', 'ENV', 'Environmental compliance', NULL, 1, '2025-11-30 13:32:23', '2025-11-30 13:32:23');

-- --------------------------------------------------------

--
-- Table structure for table `direct_expenses`
--

CREATE TABLE `direct_expenses` (
  `expense_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `expense_number` varchar(50) NOT NULL,
  `category_id` int(11) NOT NULL,
  `account_code` varchar(20) NOT NULL,
  `expense_date` date NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(100) DEFAULT NULL,
  `description` text NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'TSH',
  `payment_method` enum('bank_transfer','cash','cheque','mobile_money','credit') DEFAULT 'bank_transfer',
  `bank_account_id` int(11) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `status` enum('draft','pending_approval','approved','rejected','paid','cancelled') DEFAULT 'draft',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `paid_by` int(11) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `recurring` tinyint(1) DEFAULT 0,
  `recurring_frequency` enum('daily','weekly','monthly','quarterly','yearly') DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `districts`
--

CREATE TABLE `districts` (
  `district_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `region_id` int(11) NOT NULL,
  `district_name` varchar(100) NOT NULL,
  `district_code` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `districts`
--

INSERT INTO `districts` (`district_id`, `company_id`, `region_id`, `district_name`, `district_code`, `is_active`, `created_at`) VALUES
(1, 1, 1, 'ilala', 'DD01', 1, '2025-11-29 13:01:30');

-- --------------------------------------------------------

--
-- Table structure for table `document_sequences`
--

CREATE TABLE `document_sequences` (
  `sequence_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL COMMENT 'invoice, po, receipt, etc',
  `prefix` varchar(10) DEFAULT NULL,
  `next_number` int(11) DEFAULT 1,
  `padding` int(11) DEFAULT 4 COMMENT 'Number of digits',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auto-numbering for documents';

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Links to users table',
  `employee_number` varchar(50) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position_id` int(11) DEFAULT NULL,
  `hire_date` date NOT NULL,
  `confirmation_date` date DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `employment_type` enum('permanent','contract','casual','intern') DEFAULT 'permanent',
  `contract_end_date` date DEFAULT NULL,
  `basic_salary` decimal(15,2) DEFAULT NULL,
  `allowances` decimal(15,2) DEFAULT 0.00,
  `total_salary` decimal(15,2) GENERATED ALWAYS AS (`basic_salary` + `allowances`) STORED,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `bank_branch` varchar(100) DEFAULT NULL,
  `emergency_contact_name` varchar(200) DEFAULT NULL,
  `emergency_contact_phone` varchar(50) DEFAULT NULL,
  `emergency_contact_relationship` varchar(100) DEFAULT NULL,
  `employment_status` enum('active','suspended','terminated','resigned') DEFAULT 'active',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `company_id`, `user_id`, `employee_number`, `department_id`, `position_id`, `hire_date`, `confirmation_date`, `termination_date`, `employment_type`, `contract_end_date`, `basic_salary`, `allowances`, `bank_name`, `account_number`, `bank_branch`, `emergency_contact_name`, `emergency_contact_phone`, `emergency_contact_relationship`, `employment_status`, `is_active`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 3, 7, 'EMP505005', NULL, NULL, '2025-11-30', '2025-12-01', NULL, 'contract', '2026-11-30', 1200000.00, 0.00, 'EXIM BANK', 'ACC02831035', 'mbezi', 'GLORIA JOHANNES', '0745381762', 'parent', 'active', 1, '2025-11-30 13:14:02', '2025-11-30 13:14:02', 6),
(2, 3, 8, 'EMP938528', 3, NULL, '2025-11-30', '2025-11-30', NULL, 'permanent', NULL, 3000000.00, 0.00, 'NMB Bank', 'ACC02831035', 'azikiwe', 'rachel john', '+255 745 381 762', 'parent', 'active', 1, '2025-11-30 13:51:51', '2025-11-30 13:51:51', 6),
(3, 3, 10, 'MKB01', 14, NULL, '2025-01-01', '2025-12-31', NULL, 'permanent', NULL, 400000.00, 0.00, 'CRDB Bank', '0152700601800', 'Tabata', 'Ismail Khalfani Kavilinga', '+255 071 349 280', 'parent', 'active', 1, '2025-12-17 08:51:32', '2025-12-17 08:51:32', 9);

-- --------------------------------------------------------

--
-- Table structure for table `employee_loans`
--

CREATE TABLE `employee_loans` (
  `loan_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `loan_number` varchar(50) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `loan_type_id` int(11) NOT NULL,
  `application_date` date NOT NULL,
  `loan_amount` decimal(15,2) NOT NULL,
  `interest_rate` decimal(5,2) DEFAULT 0.00,
  `repayment_period_months` int(11) NOT NULL,
  `monthly_deduction` decimal(15,2) NOT NULL,
  `purpose` text NOT NULL,
  `guarantor1_id` int(11) DEFAULT NULL,
  `guarantor2_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected','disbursed','active','completed','defaulted','written_off') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approved_amount` decimal(15,2) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `disbursement_date` date DEFAULT NULL,
  `disbursement_method` enum('bank_transfer','cash','cheque') DEFAULT 'bank_transfer',
  `bank_account_id` int(11) DEFAULT NULL,
  `disbursement_reference` varchar(100) DEFAULT NULL,
  `principal_outstanding` decimal(15,2) DEFAULT 0.00,
  `interest_outstanding` decimal(15,2) DEFAULT 0.00,
  `total_outstanding` decimal(15,2) DEFAULT 0.00,
  `total_paid` decimal(15,2) DEFAULT 0.00,
  `last_payment_date` date DEFAULT NULL,
  `next_payment_date` date DEFAULT NULL,
  `loan_account_code` varchar(20) DEFAULT '1134',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `category_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `account_code` varchar(20) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_category_id` int(11) DEFAULT NULL,
  `budget_allocation` decimal(15,2) DEFAULT 0.00,
  `requires_approval` tinyint(1) DEFAULT 1,
  `approval_limit` decimal(15,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`category_id`, `company_id`, `account_code`, `category_name`, `description`, `parent_category_id`, `budget_allocation`, `requires_approval`, `approval_limit`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 3, '6430', 'Travel', '', NULL, 900000.00, 0, 0.00, 1, 9, '2025-12-17 13:34:04', '2025-12-17 13:34:04');

-- --------------------------------------------------------

--
-- Table structure for table `expense_claims`
--

CREATE TABLE `expense_claims` (
  `claim_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `claim_number` varchar(50) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `claim_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'TSH',
  `payment_method` enum('bank_transfer','cash','cheque','mobile_money') DEFAULT 'bank_transfer',
  `bank_account_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `status` enum('draft','submitted','pending_approval','approved','rejected','paid','cancelled') DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `paid_by` int(11) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `supporting_docs` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_claim_items`
--

CREATE TABLE `expense_claim_items` (
  `item_id` int(11) NOT NULL,
  `claim_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `description` text NOT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `unit_price` decimal(15,2) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `account_code` varchar(20) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `receipt_number` varchar(100) DEFAULT NULL,
  `vendor_name` varchar(200) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fixed_assets`
--

CREATE TABLE `fixed_assets` (
  `asset_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `asset_number` varchar(50) NOT NULL,
  `category_id` int(11) NOT NULL,
  `asset_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `purchase_date` date NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `purchase_order_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(100) DEFAULT NULL,
  `purchase_cost` decimal(15,2) NOT NULL,
  `installation_cost` decimal(15,2) DEFAULT 0.00,
  `total_cost` decimal(15,2) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `model_number` varchar(100) DEFAULT NULL,
  `manufacturer` varchar(200) DEFAULT NULL,
  `warranty_expiry_date` date DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `custodian_id` int(11) DEFAULT NULL,
  `account_code` varchar(20) NOT NULL,
  `depreciation_account_code` varchar(20) NOT NULL,
  `depreciation_method` enum('straight_line','declining_balance','units_of_production') DEFAULT 'straight_line',
  `useful_life_years` int(11) DEFAULT 5,
  `salvage_value` decimal(15,2) DEFAULT 0.00,
  `accumulated_depreciation` decimal(15,2) DEFAULT 0.00,
  `current_book_value` decimal(15,2) NOT NULL,
  `last_depreciation_date` date DEFAULT NULL,
  `status` enum('active','inactive','under_maintenance','disposed','stolen','damaged') DEFAULT 'active',
  `disposal_date` date DEFAULT NULL,
  `disposal_amount` decimal(15,2) DEFAULT NULL,
  `disposal_reason` text DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `purchase_doc_path` varchar(255) DEFAULT NULL,
  `asset_photo_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `quantity_on_hand` decimal(15,2) DEFAULT 0.00,
  `quantity_reserved` decimal(15,2) DEFAULT 0.00,
  `quantity_available` decimal(15,2) GENERATED ALWAYS AS (`quantity_on_hand` - `quantity_reserved`) STORED,
  `reorder_level` decimal(15,2) DEFAULT 0.00,
  `unit_cost` decimal(15,2) DEFAULT 0.00,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_audits`
--

CREATE TABLE `inventory_audits` (
  `audit_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `audit_number` varchar(50) NOT NULL,
  `store_id` int(11) NOT NULL,
  `audit_date` date NOT NULL,
  `auditor_name` varchar(255) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_audit_lines`
--

CREATE TABLE `inventory_audit_lines` (
  `audit_line_id` int(11) NOT NULL,
  `audit_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `expected_quantity` decimal(15,2) DEFAULT 0.00 COMMENT 'System quantity before audit',
  `actual_quantity` decimal(15,2) DEFAULT 0.00 COMMENT 'Physical count',
  `variance` decimal(15,2) GENERATED ALWAYS AS (`actual_quantity` - `expected_quantity`) STORED,
  `variance_value` decimal(15,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `counted_at` timestamp NULL DEFAULT NULL,
  `counted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `movement_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `movement_type` enum('in','out','transfer','adjustment','purchase','sale') NOT NULL,
  `from_store_id` int(11) DEFAULT NULL,
  `to_store_id` int(11) DEFAULT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `unit_cost` decimal(15,2) DEFAULT 0.00,
  `total_value` decimal(15,2) GENERATED ALWAYS AS (`quantity` * `unit_cost`) STORED,
  `reference_number` varchar(100) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'purchase_order, sales_order, etc',
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `movement_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `invoice_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `quotation_id` int(11) DEFAULT NULL,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `amount_paid` decimal(15,2) DEFAULT 0.00,
  `balance_due` decimal(15,2) GENERATED ALWAYS AS (`total_amount` - `amount_paid`) STORED,
  `status` enum('draft','sent','partially_paid','paid','overdue','cancelled') DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `invoice_item_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `item_description` text NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `line_total` decimal(15,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `item_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `unit_of_measure` varchar(50) DEFAULT 'pcs',
  `cost_price` decimal(15,2) DEFAULT 0.00,
  `selling_price` decimal(15,2) DEFAULT 0.00,
  `reorder_level` decimal(10,2) DEFAULT 0.00,
  `minimum_stock` decimal(10,2) DEFAULT 0.00,
  `maximum_stock` decimal(10,2) DEFAULT 0.00,
  `current_stock` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_categories`
--

CREATE TABLE `item_categories` (
  `category_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `category_code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `parent_category_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `journal_entries`
--

CREATE TABLE `journal_entries` (
  `journal_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `journal_number` varchar(50) NOT NULL,
  `journal_date` date NOT NULL,
  `journal_type` enum('general','sales','purchase','cash','bank','adjustment') DEFAULT 'general',
  `reference_number` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `total_debit` decimal(15,2) DEFAULT 0.00,
  `total_credit` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','posted','cancelled') DEFAULT 'draft',
  `posted_by` int(11) DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Journal entry headers';

-- --------------------------------------------------------

--
-- Table structure for table `journal_entry_lines`
--

CREATE TABLE `journal_entry_lines` (
  `line_id` int(11) NOT NULL,
  `journal_id` int(11) NOT NULL,
  `line_number` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `debit_amount` decimal(15,2) DEFAULT 0.00,
  `credit_amount` decimal(15,2) DEFAULT 0.00,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'invoice, payment, etc',
  `reference_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Journal entry line items';

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `lead_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `lead_number` varchar(50) NOT NULL DEFAULT '',
  `company_name` varchar(200) NOT NULL DEFAULT '',
  `industry` varchar(100) DEFAULT NULL,
  `company_size` varchar(50) DEFAULT NULL,
  `website` varchar(150) DEFAULT NULL,
  `contact_person` varchar(200) NOT NULL DEFAULT '',
  `job_title` varchar(150) DEFAULT NULL,
  `lead_source` enum('website','referral','walk_in','phone','email','social_media','advertisement','other') NOT NULL,
  `lead_status` enum('new','contacted','qualified','proposal','negotiation','won','lost') DEFAULT 'new',
  `full_name` varchar(200) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Tanzania',
  `source` enum('website','referral','social_media','email_campaign','cold_call','event','advertisement','partner','other') NOT NULL DEFAULT 'website',
  `campaign_id` int(11) DEFAULT NULL,
  `status` enum('new','contacted','qualified','proposal','negotiation','converted','lost') DEFAULT 'new',
  `alternative_phone` varchar(50) DEFAULT NULL,
  `interested_in` enum('plot_purchase','land_services','consultation','construction','other') DEFAULT NULL,
  `budget_range` varchar(100) DEFAULT NULL,
  `preferred_location` varchar(200) DEFAULT NULL,
  `preferred_plot_size` varchar(100) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL COMMENT 'Sales person',
  `estimated_value` decimal(15,2) DEFAULT NULL,
  `expected_close_date` date DEFAULT NULL,
  `lead_score` int(11) DEFAULT 5,
  `requirements` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `last_contact_date` date DEFAULT NULL,
  `next_follow_up_date` date DEFAULT NULL,
  `follow_up_notes` text DEFAULT NULL,
  `converted_to_customer_id` int(11) DEFAULT NULL,
  `conversion_date` date DEFAULT NULL,
  `lost_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales leads management';

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`lead_id`, `company_id`, `lead_number`, `company_name`, `industry`, `company_size`, `website`, `contact_person`, `job_title`, `lead_source`, `lead_status`, `full_name`, `email`, `phone`, `address`, `city`, `country`, `source`, `campaign_id`, `status`, `alternative_phone`, `interested_in`, `budget_range`, `preferred_location`, `preferred_plot_size`, `assigned_to`, `estimated_value`, `expected_close_date`, `lead_score`, `requirements`, `notes`, `last_contact_date`, `next_follow_up_date`, `follow_up_notes`, `converted_to_customer_id`, `conversion_date`, `lost_reason`, `created_at`, `updated_at`, `created_by`, `is_active`) VALUES
(1, 3, 'LEAD-2025-5246', '', NULL, NULL, NULL, '', NULL, 'website', 'new', 'GLORIA JOHANNES', 'softgridsystems@gmail.com', '0698799456', 'Dar Es Salaam, posta', 'Dar Es Salaam', 'Tanzania', 'social_media', NULL, 'contacted', '', 'plot_purchase', '10', 'kigamboni', '600', 9, 10000000.00, '2025-12-14', 5, 'town side', '', NULL, '2025-12-13', NULL, NULL, NULL, NULL, '2025-12-12 09:20:08', '2025-12-12 09:20:08', 9, 1);

-- --------------------------------------------------------

--
-- Table structure for table `leave_applications`
--

CREATE TABLE `leave_applications` (
  `leave_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `application_date` date NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `leave_type_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `leave_type_name` varchar(100) NOT NULL,
  `leave_code` varchar(20) DEFAULT NULL,
  `days_per_year` int(11) DEFAULT 0,
  `is_paid` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_payments`
--

CREATE TABLE `loan_payments` (
  `payment_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `principal_paid` decimal(15,2) NOT NULL,
  `interest_paid` decimal(15,2) NOT NULL,
  `total_paid` decimal(15,2) NOT NULL,
  `payment_method` enum('salary_deduction','bank_transfer','cash','cheque') NOT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_repayment_schedule`
--

CREATE TABLE `loan_repayment_schedule` (
  `schedule_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `installment_number` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `principal_amount` decimal(15,2) NOT NULL,
  `interest_amount` decimal(15,2) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `payment_status` enum('pending','paid','partial','overdue') DEFAULT 'pending',
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `payment_date` date DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `balance_outstanding` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_types`
--

CREATE TABLE `loan_types` (
  `loan_type_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `max_amount` decimal(15,2) DEFAULT NULL,
  `max_term_months` int(11) DEFAULT NULL,
  `interest_rate` decimal(5,2) DEFAULT 0.00,
  `requires_guarantor` tinyint(1) DEFAULT 0,
  `requires_collateral` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `attempt_id` bigint(20) NOT NULL,
  `username` varchar(150) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `is_successful` tinyint(1) NOT NULL,
  `failure_reason` varchar(200) DEFAULT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Login attempt tracking for security';

-- --------------------------------------------------------

--
-- Table structure for table `notification_templates`
--

CREATE TABLE `notification_templates` (
  `template_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `template_code` varchar(50) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `template_type` enum('email','sms','system','print') NOT NULL,
  `trigger_event` varchar(100) DEFAULT NULL COMMENT 'payment_received, contract_signed, etc',
  `subject` varchar(200) DEFAULT NULL,
  `message_body` text NOT NULL,
  `available_variables` text DEFAULT NULL COMMENT 'JSON array of available placeholders',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notification message templates';

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_number` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('cash','bank_transfer','mobile_money','cheque','card') DEFAULT 'cash',
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `depositor_name` varchar(255) DEFAULT NULL,
  `deposit_bank` varchar(255) DEFAULT NULL,
  `deposit_account` varchar(100) DEFAULT NULL,
  `transfer_from_bank` varchar(255) DEFAULT NULL,
  `transfer_from_account` varchar(100) DEFAULT NULL,
  `transfer_to_bank` varchar(255) DEFAULT NULL,
  `transfer_to_account` varchar(100) DEFAULT NULL,
  `mobile_money_provider` varchar(100) DEFAULT NULL,
  `mobile_money_number` varchar(50) DEFAULT NULL,
  `mobile_money_name` varchar(255) DEFAULT NULL,
  `to_account_id` int(11) DEFAULT NULL,
  `cash_transaction_id` int(11) DEFAULT NULL,
  `cheque_transaction_id` int(11) DEFAULT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `receipt_number` varchar(50) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('pending_approval','pending','approved','rejected','cancelled') DEFAULT 'pending_approval' COMMENT 'Payment status with approval workflow',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL COMMENT 'Reason for rejection',
  `rejected_by` int(11) DEFAULT NULL COMMENT 'User who rejected payment',
  `rejected_at` timestamp NULL DEFAULT NULL COMMENT 'When payment was rejected',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `submitted_by` int(11) DEFAULT NULL COMMENT 'User who submitted payment',
  `submitted_at` timestamp NULL DEFAULT NULL COMMENT 'When payment was submitted',
  `payment_type` enum('down_payment','installment','full_payment','service_payment','refund','other') DEFAULT 'installment',
  `voucher_number` varchar(50) DEFAULT NULL,
  `is_reconciled` tinyint(1) DEFAULT 0,
  `reconciled_at` datetime DEFAULT NULL,
  `reconciliation_date` date DEFAULT NULL,
  `reconciled_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment transactions';

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `company_id`, `reservation_id`, `payment_date`, `payment_number`, `amount`, `payment_method`, `bank_name`, `account_number`, `transaction_reference`, `depositor_name`, `deposit_bank`, `deposit_account`, `transfer_from_bank`, `transfer_from_account`, `transfer_to_bank`, `transfer_to_account`, `mobile_money_provider`, `mobile_money_number`, `mobile_money_name`, `to_account_id`, `cash_transaction_id`, `cheque_transaction_id`, `tax_amount`, `receipt_number`, `receipt_path`, `remarks`, `status`, `approved_by`, `approved_at`, `rejection_reason`, `rejected_by`, `rejected_at`, `created_at`, `created_by`, `submitted_by`, `submitted_at`, `payment_type`, `voucher_number`, `is_reconciled`, `reconciled_at`, `reconciliation_date`, `reconciled_by`) VALUES
(13, 3, 12, '2025-12-17', 'PAY-2025-0001', 4500000.00, 'bank_transfer', 'lazaro mpuya', NULL, '', NULL, NULL, NULL, 'CRDB Bank', '01526772009', NULL, NULL, NULL, NULL, NULL, 2, NULL, NULL, 0.00, NULL, NULL, 'Down payment for reservation RES-2025-0001', 'approved', 9, '2025-12-17 14:10:31', NULL, NULL, NULL, '2025-12-17 13:37:51', 9, 9, '2025-12-17 13:37:51', 'down_payment', NULL, 0, NULL, NULL, NULL),
(14, 3, 13, '2025-12-17', 'PAY-2025-0002', 2000000.00, 'mobile_money', NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Tigo Pesa', '0766477444', 'jane john', 3, NULL, NULL, 0.00, NULL, NULL, 'Down payment for reservation RES-2025-0002', 'approved', 9, '2025-12-17 14:18:48', NULL, NULL, NULL, '2025-12-17 14:18:01', 9, 9, '2025-12-17 14:18:01', 'down_payment', NULL, 0, NULL, NULL, NULL);

--
-- Triggers `payments`
--
DELIMITER $$
CREATE TRIGGER `after_payment_status_change` AFTER UPDATE ON `payments` FOR EACH ROW BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO `payment_approvals` (
            `payment_id`,
            `company_id`,
            `action`,
            `action_by`,
            `previous_status`,
            `new_status`,
            `comments`
        ) VALUES (
            NEW.payment_id,
            NEW.company_id,
            CASE 
                WHEN NEW.status = 'approved' THEN 'approved'
                WHEN NEW.status = 'rejected' THEN 'rejected'
                WHEN NEW.status = 'cancelled' THEN 'cancelled'
                ELSE 'submitted'
            END,
            COALESCE(NEW.approved_by, NEW.rejected_by, NEW.created_by),
            OLD.status,
            NEW.status,
            CASE 
                WHEN NEW.status = 'rejected' THEN NEW.rejection_reason
                ELSE NULL
            END
        );
        
        IF NEW.status = 'approved' THEN
            UPDATE `payment_schedules`
            SET `payment_status` = 'paid',
                `is_paid` = 1,
                `paid_amount` = NEW.amount,
                `paid_date` = NEW.payment_date
            WHERE `payment_id` = NEW.payment_id
              AND `company_id` = NEW.company_id;
        ELSEIF NEW.status = 'rejected' THEN
            UPDATE `payment_schedules`
            SET `payment_status` = 'unpaid',
                `payment_id` = NULL
            WHERE `payment_id` = NEW.payment_id
              AND `company_id` = NEW.company_id;
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `payment_approvals`
--

CREATE TABLE `payment_approvals` (
  `approval_id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `action` enum('submitted','approved','rejected','cancelled') NOT NULL,
  `action_by` int(11) NOT NULL,
  `action_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `comments` text DEFAULT NULL,
  `previous_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_approvals`
--

INSERT INTO `payment_approvals` (`approval_id`, `payment_id`, `company_id`, `action`, `action_by`, `action_at`, `comments`, `previous_status`, `new_status`) VALUES
(20, 13, 3, 'approved', 9, '2025-12-17 14:10:31', NULL, 'pending_approval', 'approved'),
(21, 14, 3, 'approved', 9, '2025-12-17 14:18:48', NULL, 'pending_approval', 'approved');

-- --------------------------------------------------------

--
-- Table structure for table `payment_recovery`
--

CREATE TABLE `payment_recovery` (
  `recovery_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `recovery_number` varchar(50) NOT NULL,
  `recovery_date` date NOT NULL,
  `customer_id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `total_debt` decimal(15,2) NOT NULL,
  `amount_recovered` decimal(15,2) DEFAULT 0.00,
  `outstanding_balance` decimal(15,2) GENERATED ALWAYS AS (`total_debt` - `amount_recovered`) STORED,
  `recovery_method` enum('legal_action','negotiation','payment_plan','asset_seizure','write_off') NOT NULL,
  `status` enum('initiated','in_progress','partially_recovered','fully_recovered','written_off') DEFAULT 'initiated',
  `assigned_to` int(11) DEFAULT NULL COMMENT 'Recovery officer',
  `follow_up_date` date DEFAULT NULL,
  `resolution_date` date DEFAULT NULL,
  `legal_notice_path` varchar(255) DEFAULT NULL,
  `agreement_path` varchar(255) DEFAULT NULL,
  `recovery_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment recovery tracking';

-- --------------------------------------------------------

--
-- Table structure for table `payment_schedules`
--

CREATE TABLE `payment_schedules` (
  `schedule_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `installment_number` int(11) NOT NULL COMMENT '1, 2, 3... up to total periods',
  `due_date` date NOT NULL,
  `installment_amount` decimal(15,2) NOT NULL,
  `is_paid` tinyint(1) DEFAULT 0,
  `payment_status` enum('unpaid','pending_approval','paid','rejected','overdue') DEFAULT 'unpaid' COMMENT 'Payment status for this installment',
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `payment_id` int(11) DEFAULT NULL COMMENT 'Links to actual payment',
  `paid_date` date DEFAULT NULL,
  `is_overdue` tinyint(1) DEFAULT 0,
  `days_overdue` int(11) DEFAULT 0,
  `late_fee` decimal(15,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auto-generated payment schedules';

-- --------------------------------------------------------

--
-- Table structure for table `payment_vouchers`
--

CREATE TABLE `payment_vouchers` (
  `voucher_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `voucher_number` varchar(50) NOT NULL,
  `voucher_type` enum('payment','receipt','refund','adjustment') NOT NULL,
  `voucher_date` date NOT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `cheque_number` varchar(50) DEFAULT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `voucher_pdf_path` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment vouchers and receipts';

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `payroll_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `payroll_month` int(11) NOT NULL,
  `payroll_year` int(11) NOT NULL,
  `payment_date` date DEFAULT NULL,
  `status` enum('draft','processed','paid','cancelled') DEFAULT 'draft',
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_details`
--

CREATE TABLE `payroll_details` (
  `payroll_detail_id` int(11) NOT NULL,
  `payroll_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `basic_salary` decimal(15,2) NOT NULL,
  `allowances` decimal(15,2) DEFAULT 0.00,
  `overtime_pay` decimal(15,2) DEFAULT 0.00,
  `bonus` decimal(15,2) DEFAULT 0.00,
  `gross_salary` decimal(15,2) GENERATED ALWAYS AS (`basic_salary` + `allowances` + `overtime_pay` + `bonus`) STORED,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `nssf_amount` decimal(15,2) DEFAULT 0.00,
  `nhif_amount` decimal(15,2) DEFAULT 0.00,
  `loan_deduction` decimal(15,2) DEFAULT 0.00,
  `other_deductions` decimal(15,2) DEFAULT 0.00,
  `total_deductions` decimal(15,2) GENERATED ALWAYS AS (`tax_amount` + `nssf_amount` + `nhif_amount` + `loan_deduction` + `other_deductions`) STORED,
  `net_salary` decimal(15,2) GENERATED ALWAYS AS (`gross_salary` - `total_deductions`) STORED,
  `payment_status` enum('pending','paid') DEFAULT 'pending',
  `payment_date` date DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payroll_details`
--

INSERT INTO `payroll_details` (`payroll_detail_id`, `payroll_id`, `employee_id`, `basic_salary`, `allowances`, `overtime_pay`, `bonus`, `tax_amount`, `nssf_amount`, `nhif_amount`, `loan_deduction`, `other_deductions`, `payment_status`, `payment_date`, `payment_reference`, `created_at`) VALUES
(1, 1, 1, 1200000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', NULL, NULL, '2025-12-12 15:32:56'),
(2, 1, 2, 3000000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', NULL, NULL, '2025-12-12 15:32:56');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `permission_id` int(11) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `permission_code` varchar(100) NOT NULL,
  `permission_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System permissions';

-- --------------------------------------------------------

--
-- Table structure for table `petty_cash_accounts`
--

CREATE TABLE `petty_cash_accounts` (
  `petty_cash_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `account_code` varchar(20) DEFAULT '1112',
  `account_name` varchar(100) NOT NULL,
  `custodian_id` int(11) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `maximum_limit` decimal(15,2) DEFAULT 50000.00,
  `minimum_balance` decimal(15,2) DEFAULT 5000.00,
  `transaction_limit` decimal(15,2) DEFAULT 10000.00,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `last_replenishment_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `petty_cash_replenishments`
--

CREATE TABLE `petty_cash_replenishments` (
  `replenishment_id` int(11) NOT NULL,
  `petty_cash_id` int(11) NOT NULL,
  `request_number` varchar(50) NOT NULL,
  `request_date` date NOT NULL,
  `requested_amount` decimal(15,2) NOT NULL,
  `current_balance` decimal(15,2) NOT NULL,
  `justification` text NOT NULL,
  `status` enum('pending','approved','rejected','disbursed') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approved_amount` decimal(15,2) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `disbursed_by` int(11) DEFAULT NULL,
  `disbursed_at` datetime DEFAULT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `petty_cash_transactions`
--

CREATE TABLE `petty_cash_transactions` (
  `transaction_id` int(11) NOT NULL,
  `petty_cash_id` int(11) NOT NULL,
  `transaction_number` varchar(50) NOT NULL,
  `transaction_type` enum('disbursement','replenishment','adjustment','return') NOT NULL,
  `transaction_date` date NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `account_code` varchar(20) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text NOT NULL,
  `recipient_name` varchar(200) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `requires_approval` tinyint(1) DEFAULT 0,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'completed',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `receipt_number` varchar(100) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `balance_before` decimal(15,2) NOT NULL,
  `balance_after` decimal(15,2) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plots`
--

CREATE TABLE `plots` (
  `plot_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `plot_number` varchar(50) NOT NULL,
  `block_number` varchar(50) DEFAULT NULL,
  `area_sqm` decimal(10,2) NOT NULL DEFAULT 0.00,
  `area` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_per_sqm` decimal(15,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `final_price` decimal(15,2) GENERATED ALWAYS AS (`selling_price` - `discount_amount`) STORED,
  `plot_size` decimal(10,2) NOT NULL COMMENT 'Size in square meters',
  `price_per_unit` decimal(15,2) NOT NULL COMMENT 'Price per square meter',
  `total_price` decimal(15,2) GENERATED ALWAYS AS (`plot_size` * `price_per_unit`) STORED,
  `survey_plan_number` varchar(100) DEFAULT NULL,
  `town_plan_number` varchar(100) DEFAULT NULL,
  `gps_coordinates` varchar(200) DEFAULT NULL,
  `status` enum('available','reserved','sold','blocked') DEFAULT 'available',
  `corner_plot` tinyint(1) DEFAULT 0,
  `coordinates` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Individual plots/land parcels';

--
-- Dumping data for table `plots`
--

INSERT INTO `plots` (`plot_id`, `company_id`, `project_id`, `plot_number`, `block_number`, `area_sqm`, `area`, `price_per_sqm`, `selling_price`, `discount_amount`, `plot_size`, `price_per_unit`, `survey_plan_number`, `town_plan_number`, `gps_coordinates`, `status`, `corner_plot`, `coordinates`, `notes`, `is_active`, `created_at`, `updated_at`, `created_by`) VALUES
(10, 3, 4, '1', 'v', 0.00, 375.00, 30000.00, 11250000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 1, '', '', 1, '2025-12-10 12:06:23', '2025-12-17 09:45:18', 6),
(11, 3, 4, '2', 'v', 0.00, 460.00, 30000.00, 13800000.00, 0.00, 0.00, 0.00, '', '', '', 'reserved', 1, '', '', 1, '2025-12-10 12:08:59', '2025-12-17 14:18:49', 6),
(12, 3, 4, '3', 'v', 0.00, 520.00, 30000.00, 15600000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-10 12:12:24', '2025-12-17 09:49:15', 6),
(13, 3, 4, '4', 'v', 0.00, 516.00, 30000.00, 15480000.00, 0.00, 0.00, 0.00, '', '', '', 'reserved', 0, '', '', 1, '2025-12-10 12:13:02', '2025-12-17 14:10:31', 6),
(14, 3, 4, '5', 'v', 0.00, 568.00, 30000.00, 17040000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-10 12:13:54', '2025-12-17 09:50:51', 6),
(15, 3, 4, '6', 'v', 0.00, 520.00, 30000.00, 15600000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-10 12:14:23', '2025-12-17 09:51:34', 6),
(16, 3, 4, '7', 'v', 0.00, 616.00, 30000.00, 18480000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-10 12:14:56', '2025-12-17 09:52:06', 6),
(17, 3, 4, '8', 'v', 0.00, 525.00, 30000.00, 15750000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-10 12:15:19', '2025-12-17 09:53:07', 6),
(18, 3, 4, '9', 'v', 0.00, 567.00, 30000.00, 17010000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-10 12:15:47', '2025-12-17 09:55:45', 6),
(19, 3, 4, '10', 'v', 0.00, 464.00, 30000.00, 13920000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-10 12:16:12', '2025-12-17 09:56:13', 6),
(25, 3, 4, '11', 'v', 0.00, 604.00, 35000.00, 21140000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-10 13:50:55', '2025-12-17 10:02:35', 6),
(26, 3, 4, '12', 'v', 0.00, 375.00, 30000.00, 11250000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-10 13:52:20', '2025-12-17 10:04:34', 6),
(27, 3, 5, '35', 'E', 0.00, 465.00, 10000.00, 4650000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 1, '', '', 1, '2025-12-17 12:00:09', '2025-12-17 12:00:09', 9),
(28, 3, 5, '36', 'E', 0.00, 544.00, 9000.00, 4896000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 1, '', '', 1, '2025-12-17 12:05:22', '2025-12-17 12:05:22', 9),
(29, 3, 5, '37', 'E', 0.00, 570.00, 9000.00, 5130000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-17 12:06:59', '2025-12-17 12:07:43', 9),
(30, 3, 5, '38', 'E', 0.00, 530.00, 10000.00, 5300000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-17 12:09:23', '2025-12-17 12:09:23', 9),
(31, 3, 5, '39', 'E', 0.00, 545.00, 10000.00, 5450000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-17 12:13:08', '2025-12-17 12:13:08', 9),
(32, 3, 5, '40', 'E', 0.00, 520.00, 8000.00, 4160000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-17 12:27:12', '2025-12-17 12:27:12', 9),
(33, 3, 5, '41', 'E', 0.00, 544.00, 10000.00, 5440000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-17 12:28:07', '2025-12-17 12:28:07', 9),
(34, 3, 5, '42', 'E', 0.00, 490.00, 8000.00, 3920000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-17 12:31:42', '2025-12-17 12:32:16', 9),
(35, 3, 5, '43', 'E', 0.00, 523.00, 10000.00, 5230000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 1, '', '', 1, '2025-12-17 12:33:29', '2025-12-17 12:33:29', 9),
(36, 3, 5, '44', 'E', 0.00, 430.00, 8000.00, 3440000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 1, '', '', 1, '2025-12-17 12:34:11', '2025-12-17 12:34:11', 9),
(37, 3, 5, '45', 'E', 0.00, 475.00, 10000.00, 4750000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 1, '', '', 1, '2025-12-17 12:36:23', '2025-12-17 12:36:23', 9),
(38, 3, 5, '46', 'E', 0.00, 480.00, 8500.00, 4080000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-17 12:37:18', '2025-12-17 12:37:18', 9),
(39, 3, 5, '47', 'E', 0.00, 470.00, 8500.00, 3995000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-17 12:38:07', '2025-12-17 12:38:07', 9),
(40, 3, 5, '48', 'E', 0.00, 410.00, 8500.00, 3485000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-17 12:39:06', '2025-12-17 12:39:06', 9),
(41, 3, 5, '49', 'E', 0.00, 320.00, 10000.00, 3200000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 0, '', '', 1, '2025-12-17 12:40:46', '2025-12-17 12:40:46', 9),
(42, 3, 6, '1', 'H', 0.00, 519.00, 8000.00, 4152000.00, 0.00, 0.00, 0.00, '', '', '', 'available', 1, '', '', 1, '2025-12-17 13:59:08', '2025-12-17 13:59:33', 9),
(43, 3, 6, '2', 'H', 0.00, 367.00, 8000.00, 2936000.00, 0.00, 0.00, 0.00, 'E\'370/53', '19/KSW/421/042023', '', 'available', 0, '', '', 1, '2025-12-17 14:01:55', '2025-12-17 14:01:55', 9);

-- --------------------------------------------------------

--
-- Table structure for table `plot_contracts`
--

CREATE TABLE `plot_contracts` (
  `contract_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `contract_number` varchar(50) NOT NULL,
  `contract_date` date NOT NULL,
  `contract_type` enum('sale','lease','installment') DEFAULT 'installment',
  `contract_duration_months` int(11) DEFAULT NULL COMMENT 'For installment contracts',
  `contract_terms` text DEFAULT NULL COMMENT 'Full terms and conditions',
  `special_conditions` text DEFAULT NULL,
  `seller_name` varchar(200) NOT NULL,
  `seller_id_number` varchar(50) DEFAULT NULL,
  `buyer_name` varchar(200) NOT NULL,
  `buyer_id_number` varchar(50) DEFAULT NULL,
  `witness1_name` varchar(200) DEFAULT NULL,
  `witness1_id_number` varchar(50) DEFAULT NULL,
  `witness1_signature_path` varchar(255) DEFAULT NULL,
  `witness2_name` varchar(200) DEFAULT NULL,
  `witness2_id_number` varchar(50) DEFAULT NULL,
  `witness2_signature_path` varchar(255) DEFAULT NULL,
  `lawyer_name` varchar(200) DEFAULT NULL,
  `notary_name` varchar(200) DEFAULT NULL,
  `notary_stamp_number` varchar(100) DEFAULT NULL,
  `contract_template_path` varchar(255) DEFAULT NULL,
  `signed_contract_path` varchar(255) DEFAULT NULL,
  `status` enum('draft','pending_signature','signed','completed','cancelled') DEFAULT 'draft',
  `signed_date` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `cancelled_date` date DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sale contracts and agreements';

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `position_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position_title` varchar(100) NOT NULL,
  `position_code` varchar(20) DEFAULT NULL,
  `job_description` text DEFAULT NULL,
  `min_salary` decimal(15,2) DEFAULT NULL,
  `max_salary` decimal(15,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `project_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `project_name` varchar(200) NOT NULL,
  `project_code` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `region_id` int(11) DEFAULT NULL,
  `district_id` int(11) DEFAULT NULL,
  `ward_id` int(11) DEFAULT NULL,
  `village_id` int(11) DEFAULT NULL,
  `physical_location` text DEFAULT NULL,
  `total_area` decimal(15,2) DEFAULT NULL COMMENT 'Total area in square meters',
  `total_plots` int(11) DEFAULT 0,
  `available_plots` int(11) DEFAULT 0,
  `reserved_plots` int(11) DEFAULT 0,
  `sold_plots` int(11) DEFAULT 0,
  `acquisition_date` date DEFAULT NULL,
  `closing_date` date DEFAULT NULL,
  `title_deed_path` varchar(255) DEFAULT NULL,
  `survey_plan_path` varchar(255) DEFAULT NULL,
  `contract_attachment_path` varchar(255) DEFAULT NULL,
  `coordinates_path` varchar(255) DEFAULT NULL,
  `status` enum('planning','active','completed','suspended') DEFAULT 'planning',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `land_purchase_price` decimal(15,2) DEFAULT NULL COMMENT 'Total cost to acquire land',
  `total_operational_costs` decimal(15,2) DEFAULT 0.00 COMMENT 'Survey, legal, development costs',
  `total_investment` decimal(15,2) GENERATED ALWAYS AS (`land_purchase_price` + `total_operational_costs`) STORED,
  `cost_per_sqm` decimal(10,2) DEFAULT NULL COMMENT 'Buying cost per square meter',
  `selling_price_per_sqm` decimal(10,2) DEFAULT NULL COMMENT 'Selling price per square meter',
  `profit_margin_percentage` decimal(5,2) DEFAULT 0.00,
  `total_expected_revenue` decimal(15,2) DEFAULT 0.00,
  `total_actual_revenue` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Plot development projects';

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`project_id`, `company_id`, `project_name`, `project_code`, `description`, `region_id`, `district_id`, `ward_id`, `village_id`, `physical_location`, `total_area`, `total_plots`, `available_plots`, `reserved_plots`, `sold_plots`, `acquisition_date`, `closing_date`, `title_deed_path`, `survey_plan_path`, `contract_attachment_path`, `coordinates_path`, `status`, `is_active`, `created_at`, `updated_at`, `created_by`, `land_purchase_price`, `total_operational_costs`, `cost_per_sqm`, `selling_price_per_sqm`, `profit_margin_percentage`, `total_expected_revenue`, `total_actual_revenue`) VALUES
(4, 3, 'VUMILIA UKOONI', 'PRJ-2025-9344', 'VUMILIA UKOONI', 1, 1, 1, 1, '', 6110.00, 12, 10, 2, 0, '2025-10-01', '2025-12-31', NULL, NULL, NULL, NULL, 'active', 1, '2025-12-10 12:03:19', '2025-12-17 14:18:49', 6, 60000000.00, 5000000.00, 10638.30, 30000.00, 182.00, 183300000.00, 0.00),
(5, 3, 'KIBAHA BOKOMNEMELA-2', 'PRJ-2025-7743', '', 1, NULL, NULL, NULL, '', 7316.00, 15, 15, 0, 0, '2025-12-15', '2026-02-15', NULL, NULL, NULL, NULL, 'active', 1, '2025-12-17 11:56:55', '2025-12-17 12:40:46', 9, 14300000.00, 0.00, 1954.62, 8000.00, 309.29, 58528000.00, 0.00),
(6, 3, 'CHANIKA HOMBOZA', 'PRJ-2025-5911', '', 1, 1, 1, 1, '', 12539.00, 28, 2, 0, 0, '2022-11-09', '2022-11-09', NULL, 'uploads/projects/survey_plan_1765979235_6942b46368584.pdf', NULL, NULL, 'active', 1, '2025-12-17 13:47:15', '2025-12-17 14:01:55', 9, 20000000.00, 5000000.00, 1993.78, 7000.00, 251.09, 87773000.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `project_costs`
--

CREATE TABLE `project_costs` (
  `cost_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `cost_category` enum('land_purchase','survey','legal_fees','title_processing','development','marketing','consultation','other') NOT NULL,
  `cost_description` text NOT NULL,
  `cost_amount` decimal(15,2) NOT NULL,
  `cost_date` date NOT NULL,
  `receipt_number` varchar(100) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Project operational costs tracking';

--
-- Dumping data for table `project_costs`
--

INSERT INTO `project_costs` (`cost_id`, `company_id`, `project_id`, `cost_category`, `cost_description`, `cost_amount`, `cost_date`, `receipt_number`, `attachment_path`, `approved_by`, `approved_at`, `remarks`, `created_at`, `created_by`) VALUES
(1, 3, 4, 'marketing', 'marketing costs', 300000.00, '2025-12-12', '', NULL, NULL, NULL, '', '2025-12-12 08:53:22', 9);

-- --------------------------------------------------------

--
-- Table structure for table `project_sellers`
--

CREATE TABLE `project_sellers` (
  `seller_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `seller_name` varchar(200) NOT NULL COMMENT 'Project owner/seller name',
  `seller_phone` varchar(50) DEFAULT NULL,
  `seller_nida` varchar(50) DEFAULT NULL COMMENT 'National ID number',
  `seller_tin` varchar(50) DEFAULT NULL COMMENT 'TIN number',
  `seller_address` text DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_amount` decimal(15,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Simplified land sellers information';

--
-- Dumping data for table `project_sellers`
--

INSERT INTO `project_sellers` (`seller_id`, `project_id`, `company_id`, `seller_name`, `seller_phone`, `seller_nida`, `seller_tin`, `seller_address`, `purchase_date`, `purchase_amount`, `notes`, `created_at`, `created_by`) VALUES
(3, 4, 3, 'Deogratius Gaston Luvakule', '767571432', '198111223471620000229', '', '', '2025-10-10', 60000000.00, '', '2025-12-10 12:03:19', 6),
(4, 5, 3, 'STIVE NATHAN MIFWA', '0788199866', '', '', '', '0000-00-00', 14300000.00, '', '2025-12-17 11:56:55', 9),
(5, 6, 3, 'James Wiliam Mgoya', '+255 712 540 188', '', '', '', '0000-00-00', 20000000.00, '', '2025-12-17 13:47:15', 9);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `purchase_order_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `po_date` date NOT NULL,
  `delivery_date` date DEFAULT NULL,
  `supplier_id` int(11) NOT NULL,
  `requisition_id` int(11) DEFAULT NULL,
  `payment_terms` varchar(200) DEFAULT NULL,
  `delivery_terms` varchar(200) DEFAULT NULL,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','submitted','approved','received','closed','cancelled') DEFAULT 'draft',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `po_item_id` int(11) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `item_description` text NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_of_measure` varchar(50) DEFAULT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `line_total` decimal(15,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `quantity_received` decimal(10,2) DEFAULT 0.00,
  `quantity_remaining` decimal(10,2) GENERATED ALWAYS AS (`quantity` - `quantity_received`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requisitions`
--

CREATE TABLE `purchase_requisitions` (
  `requisition_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `requisition_number` varchar(50) NOT NULL,
  `requisition_date` date NOT NULL,
  `required_date` date DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `purpose` text DEFAULT NULL,
  `status` enum('draft','submitted','approved','rejected','ordered','cancelled') DEFAULT 'draft',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quotations`
--

CREATE TABLE `quotations` (
  `quotation_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `quotation_number` varchar(50) NOT NULL,
  `quotation_date` date NOT NULL,
  `valid_until_date` date DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `quote_date` date NOT NULL DEFAULT curdate(),
  `valid_until` date NOT NULL DEFAULT (curdate() + interval 30 day),
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `payment_terms` text DEFAULT NULL,
  `delivery_terms` text DEFAULT NULL,
  `status` enum('draft','sent','accepted','rejected','expired') DEFAULT 'draft',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `terms_conditions` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quotation_items`
--

CREATE TABLE `quotation_items` (
  `quotation_item_id` int(11) NOT NULL,
  `quotation_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `item_description` text NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(50) DEFAULT 'unit',
  `unit_price` decimal(15,2) NOT NULL,
  `total_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `details` text DEFAULT NULL,
  `line_total` decimal(15,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
  `refund_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `refund_number` varchar(50) NOT NULL,
  `refund_date` date NOT NULL,
  `refund_reason` enum('cancellation','overpayment','plot_unavailable','customer_request','dispute','other') NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `plot_id` int(11) DEFAULT NULL,
  `original_payment_id` int(11) DEFAULT NULL,
  `original_amount` decimal(15,2) NOT NULL,
  `refund_amount` decimal(15,2) NOT NULL,
  `penalty_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'Deduction if any',
  `net_refund_amount` decimal(15,2) GENERATED ALWAYS AS (`refund_amount` - `penalty_amount`) STORED,
  `refund_method` enum('bank_transfer','cheque','cash','mobile_money') NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `cheque_number` varchar(50) DEFAULT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `status` enum('pending','approved','processed','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `refund_voucher_path` varchar(255) DEFAULT NULL,
  `supporting_documents_path` varchar(255) DEFAULT NULL,
  `detailed_reason` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment refunds management';

-- --------------------------------------------------------

--
-- Table structure for table `regions`
--

CREATE TABLE `regions` (
  `region_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `region_name` varchar(100) NOT NULL,
  `region_code` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `regions`
--

INSERT INTO `regions` (`region_id`, `company_id`, `region_name`, `region_code`, `is_active`, `created_at`) VALUES
(1, 1, 'Dar es salaam', 'DR01', 1, '2025-11-29 13:00:57');

-- --------------------------------------------------------

--
-- Table structure for table `requisition_items`
--

CREATE TABLE `requisition_items` (
  `requisition_item_id` int(11) NOT NULL,
  `requisition_id` int(11) NOT NULL,
  `item_description` text NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_of_measure` varchar(50) DEFAULT NULL,
  `estimated_unit_price` decimal(15,2) DEFAULT NULL,
  `estimated_total_price` decimal(15,2) GENERATED ALWAYS AS (`quantity` * `estimated_unit_price`) STORED,
  `specifications` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `reservation_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `plot_id` int(11) NOT NULL,
  `reservation_date` date NOT NULL,
  `reservation_number` varchar(50) DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `down_payment` decimal(15,2) DEFAULT 0.00,
  `remaining_balance` decimal(15,2) GENERATED ALWAYS AS (`total_amount` - `down_payment`) STORED,
  `payment_periods` int(11) DEFAULT 20 COMMENT 'Number of installment periods',
  `installment_amount` decimal(15,2) DEFAULT NULL,
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `title_holder_name` varchar(200) DEFAULT NULL,
  `title_deed_path` varchar(255) DEFAULT NULL,
  `status` enum('draft','active','completed','cancelled') DEFAULT 'draft',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Plot reservations and sales';

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`reservation_id`, `company_id`, `customer_id`, `plot_id`, `reservation_date`, `reservation_number`, `total_amount`, `down_payment`, `payment_periods`, `installment_amount`, `discount_percentage`, `discount_amount`, `title_holder_name`, `title_deed_path`, `status`, `is_active`, `created_at`, `updated_at`, `created_by`, `approved_by`, `approved_at`, `rejected_by`, `rejected_at`, `rejection_reason`, `completed_at`) VALUES
(12, 3, 1, 13, '2025-12-17', 'RES-2025-0001', 15480000.00, 4500000.00, 20, 549000.00, 0.00, 0.00, '', NULL, 'active', 1, '2025-12-17 13:37:51', '2025-12-17 14:10:31', 9, NULL, NULL, NULL, NULL, NULL, NULL),
(13, 3, 1, 11, '2025-12-17', 'RES-2025-0002', 13800000.00, 2000000.00, 20, 590000.00, 0.00, 0.00, '', NULL, 'active', 1, '2025-12-17 14:18:01', '2025-12-17 14:18:49', 9, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reservation_cancellations`
--

CREATE TABLE `reservation_cancellations` (
  `cancellation_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `cancellation_number` varchar(50) NOT NULL,
  `cancellation_date` date NOT NULL,
  `cancellation_reason` enum('customer_request','payment_default','mutual_agreement','breach_of_contract','plot_unavailable','other') NOT NULL,
  `detailed_reason` text DEFAULT NULL,
  `total_amount_paid` decimal(15,2) DEFAULT 0.00,
  `refund_amount` decimal(15,2) DEFAULT 0.00,
  `penalty_amount` decimal(15,2) DEFAULT 0.00,
  `amount_forfeited` decimal(15,2) DEFAULT 0.00,
  `plot_id` int(11) NOT NULL,
  `plot_return_status` enum('returned_to_market','reserved_for_other','blocked') DEFAULT 'returned_to_market',
  `plot_returned_date` date DEFAULT NULL,
  `contract_id` int(11) DEFAULT NULL,
  `contract_termination_date` date DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `cancellation_letter_path` varchar(255) DEFAULT NULL,
  `termination_agreement_path` varchar(255) DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reservation cancellations tracking';

--
-- Triggers `reservation_cancellations`
--
DELIMITER $$
CREATE TRIGGER `after_cancellation_insert` AFTER INSERT ON `reservation_cancellations` FOR EACH ROW BEGIN
    IF NEW.plot_return_status = 'returned_to_market' THEN
        UPDATE plots 
        SET status = 'available',
            updated_at = NOW()
        WHERE plot_id = NEW.plot_id 
        AND company_id = NEW.company_id;
        
        UPDATE reservations
        SET status = 'cancelled',
            updated_at = NOW()
        WHERE reservation_id = NEW.reservation_id
        AND company_id = NEW.company_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_permission_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Role-permission mappings';

-- --------------------------------------------------------

--
-- Table structure for table `sales_quotations`
--

CREATE TABLE `sales_quotations` (
  `quotation_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `quotation_number` varchar(50) NOT NULL,
  `quotation_date` date NOT NULL,
  `valid_until_date` date NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `quotation_type` enum('plot_sale','service','mixed') DEFAULT 'plot_sale',
  `plot_id` int(11) DEFAULT NULL,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `payment_terms` text DEFAULT NULL,
  `down_payment_required` decimal(15,2) DEFAULT NULL,
  `installment_months` int(11) DEFAULT NULL,
  `status` enum('draft','sent','viewed','accepted','rejected','expired','revised') DEFAULT 'draft',
  `accepted_date` date DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `converted_to_reservation_id` int(11) DEFAULT NULL,
  `quotation_pdf_path` varchar(255) DEFAULT NULL,
  `terms_conditions` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales quotations';

-- --------------------------------------------------------

--
-- Table structure for table `service_requests`
--

CREATE TABLE `service_requests` (
  `service_request_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `request_number` varchar(50) NOT NULL,
  `request_date` date NOT NULL,
  `service_type_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `plot_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `service_description` text NOT NULL,
  `plot_size` decimal(10,2) DEFAULT NULL COMMENT 'If applicable',
  `location_details` text DEFAULT NULL,
  `quoted_price` decimal(15,2) DEFAULT NULL,
  `final_price` decimal(15,2) DEFAULT NULL,
  `requested_start_date` date DEFAULT NULL,
  `actual_start_date` date DEFAULT NULL,
  `expected_completion_date` date DEFAULT NULL,
  `actual_completion_date` date DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL COMMENT 'User/consultant assigned',
  `status` enum('pending','quoted','approved','in_progress','completed','cancelled','on_hold') DEFAULT 'pending',
  `quotation_path` varchar(255) DEFAULT NULL,
  `completion_report_path` varchar(255) DEFAULT NULL,
  `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `amount_paid` decimal(15,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Service requests and orders';

-- --------------------------------------------------------

--
-- Table structure for table `service_types`
--

CREATE TABLE `service_types` (
  `service_type_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `service_code` varchar(20) DEFAULT NULL,
  `service_name` varchar(200) NOT NULL,
  `service_category` enum('land_evaluation','title_processing','consultation','construction','survey','legal','other') NOT NULL,
  `description` text DEFAULT NULL,
  `base_price` decimal(15,2) DEFAULT NULL,
  `price_unit` varchar(50) DEFAULT NULL COMMENT 'per sqm, per plot, flat fee',
  `estimated_duration_days` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Service offerings catalog';

-- --------------------------------------------------------

--
-- Table structure for table `stock_alerts`
--

CREATE TABLE `stock_alerts` (
  `alert_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `alert_type` enum('low_stock','out_of_stock','overstock') NOT NULL,
  `quantity_on_hand` decimal(15,2) DEFAULT 0.00,
  `reorder_level` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','resolved','ignored') DEFAULT 'active',
  `notified_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `movement_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `movement_number` varchar(50) NOT NULL,
  `movement_date` date NOT NULL,
  `movement_type` enum('purchase','sale','transfer','adjustment','return') NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(15,2) DEFAULT NULL,
  `total_cost` decimal(15,2) GENERATED ALWAYS AS (`quantity` * `unit_cost`) STORED,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stores`
--

CREATE TABLE `stores` (
  `store_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `store_code` varchar(50) NOT NULL,
  `store_name` varchar(255) NOT NULL,
  `store_type` enum('warehouse','retail','distribution','transit') DEFAULT 'warehouse',
  `location` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `manager_name` varchar(255) DEFAULT NULL,
  `capacity` decimal(15,2) DEFAULT NULL COMMENT 'Maximum items capacity',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_locations`
--

CREATE TABLE `store_locations` (
  `store_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `store_code` varchar(20) NOT NULL,
  `store_name` varchar(100) NOT NULL,
  `store_type` enum('main','branch','warehouse','site') DEFAULT 'main',
  `physical_location` text DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `store_manager_id` int(11) DEFAULT NULL,
  `storage_capacity` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Store/warehouse locations';

-- --------------------------------------------------------

--
-- Table structure for table `store_stock`
--

CREATE TABLE `store_stock` (
  `store_stock_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity_on_hand` decimal(10,2) DEFAULT 0.00,
  `quantity_reserved` decimal(10,2) DEFAULT 0.00,
  `quantity_available` decimal(10,2) GENERATED ALWAYS AS (`quantity_on_hand` - `quantity_reserved`) STORED,
  `reorder_level` decimal(10,2) DEFAULT NULL,
  `reorder_quantity` decimal(10,2) DEFAULT NULL,
  `bin_location` varchar(50) DEFAULT NULL,
  `shelf_number` varchar(50) DEFAULT NULL,
  `last_movement_date` date DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stock levels per store location';

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `supplier_name` varchar(200) NOT NULL,
  `supplier_code` varchar(50) DEFAULT NULL,
  `supplier_type` varchar(50) DEFAULT 'other',
  `category` varchar(100) DEFAULT NULL,
  `registration_number` varchar(100) DEFAULT NULL,
  `tin_number` varchar(100) DEFAULT NULL,
  `contact_person` varchar(200) DEFAULT NULL,
  `contact_title` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `alternative_phone` varchar(20) DEFAULT NULL,
  `mobile` varchar(50) DEFAULT NULL,
  `physical_address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Tanzania',
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
  `account_name` varchar(100) DEFAULT NULL,
  `swift_code` varchar(50) DEFAULT NULL,
  `payment_terms` varchar(50) DEFAULT 'net_30',
  `account_number` varchar(50) DEFAULT NULL,
  `credit_days` int(11) DEFAULT 30,
  `credit_limit` decimal(15,2) DEFAULT 0.00,
  `lead_time_days` int(11) DEFAULT 0,
  `rating` tinyint(1) DEFAULT 3,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `log_id` bigint(20) NOT NULL,
  `log_level` enum('info','warning','error','critical') NOT NULL,
  `log_message` text NOT NULL,
  `module_name` varchar(100) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `line_number` int(11) DEFAULT NULL,
  `context_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context_data`)),
  `stack_trace` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System error and debug logs';

-- --------------------------------------------------------

--
-- Table structure for table `system_roles`
--

CREATE TABLE `system_roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `is_system_role` tinyint(1) DEFAULT 0 COMMENT 'Cannot be deleted if TRUE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System-wide role definitions';

--
-- Dumping data for table `system_roles`
--

INSERT INTO `system_roles` (`role_id`, `role_name`, `role_code`, `description`, `is_system_role`, `created_at`) VALUES
(1, 'Super Admin', 'SUPER_ADMIN', 'Platform super administrator', 1, '2025-11-29 07:19:26'),
(2, 'Company Admin', 'COMPANY_ADMIN', 'Company administrator with full access', 1, '2025-11-29 07:19:26'),
(3, 'Manager', 'MANAGER', 'Department manager', 1, '2025-11-29 07:19:26'),
(4, 'Accountant', 'ACCOUNTANT', 'Finance and accounting staff', 1, '2025-11-29 07:19:26'),
(5, 'Finance Officer', 'FINANCE_OFFICER', 'Finance department staff', 1, '2025-11-29 07:19:26'),
(6, 'HR Officer', 'HR_OFFICER', 'Human resources staff', 1, '2025-11-29 07:19:26'),
(7, 'Procurement Officer', 'PROCUREMENT', 'Procurement and purchasing staff', 1, '2025-11-29 07:19:26'),
(8, 'Sales Officer', 'SALES', 'Sales and marketing staff', 1, '2025-11-29 07:19:26'),
(9, 'Inventory Clerk', 'INVENTORY', 'Inventory management staff', 1, '2025-11-29 07:19:26'),
(10, 'Receptionist', 'RECEPTIONIST', 'Front office staff', 1, '2025-11-29 07:19:26'),
(11, 'Auditor', 'AUDITOR', 'Internal/external auditor (read-only)', 1, '2025-11-29 07:19:26'),
(12, 'User', 'USER', 'Regular user with limited access', 1, '2025-11-29 07:19:26');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `setting_category` varchar(100) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `data_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configurable system settings';

-- --------------------------------------------------------

--
-- Table structure for table `tax_transactions`
--

CREATE TABLE `tax_transactions` (
  `tax_transaction_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `transaction_number` varchar(50) NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` enum('sales','purchase','payroll','withholding','other') NOT NULL,
  `tax_type_id` int(11) NOT NULL,
  `taxable_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `customer_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','filed','paid','cancelled') NOT NULL DEFAULT 'pending',
  `payment_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tax transactions and collections';

-- --------------------------------------------------------

--
-- Table structure for table `tax_types`
--

CREATE TABLE `tax_types` (
  `tax_type_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `tax_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `tax_name` varchar(100) NOT NULL,
  `tax_description` text DEFAULT NULL,
  `tax_rate` decimal(5,2) NOT NULL COMMENT 'Percentage',
  `applies_to` enum('sales','purchases','services','payroll','all') DEFAULT 'all',
  `tax_authority` varchar(200) DEFAULT NULL COMMENT 'e.g., TRA - Tanzania Revenue Authority',
  `tax_account_number` varchar(100) DEFAULT NULL,
  `tax_payable_account_id` int(11) DEFAULT NULL,
  `tax_expense_account_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tax types and rates';

--
-- Dumping data for table `tax_types`
--

INSERT INTO `tax_types` (`tax_type_id`, `company_id`, `tax_code`, `description`, `tax_name`, `tax_description`, `tax_rate`, `applies_to`, `tax_authority`, `tax_account_number`, `tax_payable_account_id`, `tax_expense_account_id`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 3, 'WHT', 'Tax of services', 'Withholding Tax', NULL, 5.00, 'all', NULL, NULL, NULL, NULL, 1, 9, '2025-12-17 09:04:20', '2025-12-17 09:04:20'),
(2, 3, 'CORP-TAX', 'Porfit', 'Corporate Tax', NULL, 30.00, 'all', NULL, NULL, NULL, NULL, 1, 9, '2025-12-17 09:13:19', '2025-12-17 09:13:19');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL COMMENT 'Multi-tenant link',
  `username` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `full_name` varchar(300) GENERATED ALWAYS AS (concat(`first_name`,' ',ifnull(concat(`middle_name`,' '),''),`last_name`)) STORED,
  `phone1` varchar(50) DEFAULT NULL,
  `phone2` varchar(50) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `ward` varchar(100) DEFAULT NULL,
  `village` varchar(100) DEFAULT NULL,
  `street_address` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `national_id` varchar(50) DEFAULT NULL,
  `guardian1_name` varchar(200) DEFAULT NULL,
  `guardian1_relationship` varchar(100) DEFAULT NULL,
  `guardian2_name` varchar(200) DEFAULT NULL,
  `guardian2_relationship` varchar(100) DEFAULT NULL,
  `can_get_commission` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `is_email_verified` tinyint(1) DEFAULT 0,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `is_super_admin` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `password_reset_token` varchar(100) DEFAULT NULL,
  `password_reset_expires` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User accounts (multi-tenant)';

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `company_id`, `username`, `email`, `password_hash`, `first_name`, `middle_name`, `last_name`, `phone1`, `phone2`, `region`, `district`, `ward`, `village`, `street_address`, `profile_picture`, `gender`, `date_of_birth`, `national_id`, `guardian1_name`, `guardian1_relationship`, `guardian2_name`, `guardian2_relationship`, `can_get_commission`, `is_active`, `is_email_verified`, `is_admin`, `is_super_admin`, `last_login_at`, `last_login_ip`, `password_reset_token`, `password_reset_expires`, `created_at`, `updated_at`, `created_by`) VALUES
(7, 3, 'grayson', 'grayson@gmail.com', '123456', 'GLORIA', 'JOHANNES', 'JOHANNES', '0745381762', '0745381762', '', '', '', '', 'Dar Es Salaam, Kinyerez', NULL, 'female', '1999-06-23', '6578383909200384', NULL, NULL, NULL, NULL, 0, 1, 0, 0, 0, NULL, NULL, NULL, NULL, '2025-11-30 13:14:02', '2025-11-30 13:14:02', 6),
(8, 3, 'jack', 'jack@gmail.com', '$2y$10$r3ZkpNhvdEnzxO0HlNfJEuzKeR0KVDLOJP7iC2/BLkEi4YSbP1LBy', 'jackson', 'john', 'joachim', '+255 745 381 762', '+255 745 381 762', 'Dar es salaam', 'TEMEKE', 'MBAGALA', 'Dar Es Salaam, Kinyerez', 'Dar Es Salaam, Kinyerez', NULL, 'male', '1997-02-04', '578420090438020022', NULL, NULL, NULL, NULL, 0, 1, 0, 0, 0, NULL, NULL, NULL, NULL, '2025-11-30 13:51:51', '2025-11-30 13:51:51', 6),
(9, 3, 'admin', 'admin@mkumbiinvestment.co.tz', '123456', 'admin', '', 'mkumbi', '0745381762', '', '', '', '', '', 'Dar Es Salaam, Kinyerez', NULL, 'female', '0000-00-00', '', NULL, NULL, NULL, NULL, 0, 1, 0, 0, 1, NULL, NULL, NULL, NULL, '2025-12-11 20:03:40', '2025-12-12 10:31:45', NULL),
(10, 3, 'hamisiismail69.hi', 'hamisiismail69.hi@gmail.com', '$2y$10$FL9f79K3akAe.liEI4YzpeeXLE.TJ6Lx99omuDrOCP/VIE0LfnXS6', 'Hamisi', 'Ismail', 'Khalfani', '+255 786 133 399', '+255 716 133 39', '', '', '', 'Ilala 25423', 'Ilala 25423', NULL, 'male', '1992-11-05', '19921105121050000225', NULL, NULL, NULL, NULL, 0, 1, 0, 0, 0, NULL, NULL, NULL, NULL, '2025-12-17 08:51:32', '2025-12-17 08:51:32', 9);

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `user_role_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User-role assignments';

-- --------------------------------------------------------

--
-- Table structure for table `villages`
--

CREATE TABLE `villages` (
  `village_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `ward_id` int(11) NOT NULL,
  `village_name` varchar(100) NOT NULL,
  `village_code` varchar(20) DEFAULT NULL,
  `chairman_name` varchar(200) DEFAULT NULL,
  `chairman_phone` varchar(50) DEFAULT NULL,
  `mtendaji_name` varchar(200) DEFAULT NULL COMMENT 'Village Executive Officer',
  `mtendaji_phone` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Village information with leadership';

--
-- Dumping data for table `villages`
--

INSERT INTO `villages` (`village_id`, `company_id`, `ward_id`, `village_name`, `village_code`, `chairman_name`, `chairman_phone`, `mtendaji_name`, `mtendaji_phone`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'kariakoo', 'VD001', 'gloria john', '07363636737', 'juma jackson', '06748849493', 1, '2025-11-29 13:03:24', '2025-11-29 13:03:24');

-- --------------------------------------------------------

--
-- Table structure for table `wards`
--

CREATE TABLE `wards` (
  `ward_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `district_id` int(11) NOT NULL,
  `ward_name` varchar(100) NOT NULL,
  `ward_code` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wards`
--

INSERT INTO `wards` (`ward_id`, `company_id`, `district_id`, `ward_name`, `ward_code`, `is_active`, `created_at`) VALUES
(1, 1, 1, 'iala', 'WD001', 1, '2025-11-29 13:02:26');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `approval_actions`
--
ALTER TABLE `approval_actions`
  ADD PRIMARY KEY (`approval_action_id`);

--
-- Indexes for table `approval_levels`
--
ALTER TABLE `approval_levels`
  ADD PRIMARY KEY (`approval_level_id`);

--
-- Indexes for table `approval_requests`
--
ALTER TABLE `approval_requests`
  ADD PRIMARY KEY (`approval_request_id`);

--
-- Indexes for table `approval_workflows`
--
ALTER TABLE `approval_workflows`
  ADD PRIMARY KEY (`workflow_id`);

--
-- Indexes for table `asset_categories`
--
ALTER TABLE `asset_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `asset_depreciation`
--
ALTER TABLE `asset_depreciation`
  ADD PRIMARY KEY (`depreciation_id`);

--
-- Indexes for table `asset_maintenance`
--
ALTER TABLE `asset_maintenance`
  ADD PRIMARY KEY (`maintenance_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`bank_account_id`);

--
-- Indexes for table `bank_statements`
--
ALTER TABLE `bank_statements`
  ADD PRIMARY KEY (`statement_id`);

--
-- Indexes for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  ADD PRIMARY KEY (`bank_transaction_id`);

--
-- Indexes for table `budgets`
--
ALTER TABLE `budgets`
  ADD PRIMARY KEY (`budget_id`);

--
-- Indexes for table `budget_lines`
--
ALTER TABLE `budget_lines`
  ADD PRIMARY KEY (`budget_line_id`);

--
-- Indexes for table `campaigns`
--
ALTER TABLE `campaigns`
  ADD PRIMARY KEY (`campaign_id`);

--
-- Indexes for table `cash_transactions`
--
ALTER TABLE `cash_transactions`
  ADD PRIMARY KEY (`cash_transaction_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_company` (`company_id`),
  ADD KEY `idx_payment` (`payment_id`),
  ADD KEY `idx_date` (`transaction_date`);

--
-- Indexes for table `chart_of_accounts`
--
ALTER TABLE `chart_of_accounts`
  ADD PRIMARY KEY (`account_id`);

--
-- Indexes for table `cheque_transactions`
--
ALTER TABLE `cheque_transactions`
  ADD PRIMARY KEY (`cheque_transaction_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_company` (`company_id`),
  ADD KEY `idx_payment` (`payment_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `commissions`
--
ALTER TABLE `commissions`
  ADD PRIMARY KEY (`commission_id`);

--
-- Indexes for table `commission_payments`
--
ALTER TABLE `commission_payments`
  ADD PRIMARY KEY (`commission_payment_id`);

--
-- Indexes for table `commission_payment_requests`
--
ALTER TABLE `commission_payment_requests`
  ADD PRIMARY KEY (`commission_payment_request_id`);

--
-- Indexes for table `commission_structures`
--
ALTER TABLE `commission_structures`
  ADD PRIMARY KEY (`commission_structure_id`);

--
-- Indexes for table `commission_tiers`
--
ALTER TABLE `commission_tiers`
  ADD PRIMARY KEY (`commission_tier_id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`company_id`);

--
-- Indexes for table `company_loans`
--
ALTER TABLE `company_loans`
  ADD PRIMARY KEY (`loan_id`);

--
-- Indexes for table `company_loan_payments`
--
ALTER TABLE `company_loan_payments`
  ADD PRIMARY KEY (`payment_id`);

--
-- Indexes for table `cost_categories`
--
ALTER TABLE `cost_categories`
  ADD PRIMARY KEY (`cost_category_id`);

--
-- Indexes for table `creditors`
--
ALTER TABLE `creditors`
  ADD PRIMARY KEY (`creditor_id`);

--
-- Indexes for table `creditor_invoices`
--
ALTER TABLE `creditor_invoices`
  ADD PRIMARY KEY (`creditor_invoice_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`);

--
-- Indexes for table `debtors`
--
ALTER TABLE `debtors`
  ADD PRIMARY KEY (`debtor_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`);

--
-- Indexes for table `direct_expenses`
--
ALTER TABLE `direct_expenses`
  ADD PRIMARY KEY (`expense_id`);

--
-- Indexes for table `districts`
--
ALTER TABLE `districts`
  ADD PRIMARY KEY (`district_id`);

--
-- Indexes for table `document_sequences`
--
ALTER TABLE `document_sequences`
  ADD PRIMARY KEY (`sequence_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`);

--
-- Indexes for table `employee_loans`
--
ALTER TABLE `employee_loans`
  ADD PRIMARY KEY (`loan_id`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `expense_claims`
--
ALTER TABLE `expense_claims`
  ADD PRIMARY KEY (`claim_id`);

--
-- Indexes for table `expense_claim_items`
--
ALTER TABLE `expense_claim_items`
  ADD PRIMARY KEY (`item_id`);

--
-- Indexes for table `fixed_assets`
--
ALTER TABLE `fixed_assets`
  ADD PRIMARY KEY (`asset_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`);

--
-- Indexes for table `inventory_audits`
--
ALTER TABLE `inventory_audits`
  ADD PRIMARY KEY (`audit_id`);

--
-- Indexes for table `inventory_audit_lines`
--
ALTER TABLE `inventory_audit_lines`
  ADD PRIMARY KEY (`audit_line_id`);

--
-- Indexes for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD PRIMARY KEY (`movement_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`invoice_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`invoice_item_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`item_id`);

--
-- Indexes for table `item_categories`
--
ALTER TABLE `item_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD PRIMARY KEY (`journal_id`);

--
-- Indexes for table `journal_entry_lines`
--
ALTER TABLE `journal_entry_lines`
  ADD PRIMARY KEY (`line_id`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`lead_id`);

--
-- Indexes for table `leave_applications`
--
ALTER TABLE `leave_applications`
  ADD PRIMARY KEY (`leave_id`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`leave_type_id`);

--
-- Indexes for table `loan_payments`
--
ALTER TABLE `loan_payments`
  ADD PRIMARY KEY (`payment_id`);

--
-- Indexes for table `loan_repayment_schedule`
--
ALTER TABLE `loan_repayment_schedule`
  ADD PRIMARY KEY (`schedule_id`);

--
-- Indexes for table `loan_types`
--
ALTER TABLE `loan_types`
  ADD PRIMARY KEY (`loan_type_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`attempt_id`);

--
-- Indexes for table `notification_templates`
--
ALTER TABLE `notification_templates`
  ADD PRIMARY KEY (`template_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `idx_to_account` (`to_account_id`);

--
-- Indexes for table `payment_approvals`
--
ALTER TABLE `payment_approvals`
  ADD PRIMARY KEY (`approval_id`);

--
-- Indexes for table `payment_recovery`
--
ALTER TABLE `payment_recovery`
  ADD PRIMARY KEY (`recovery_id`);

--
-- Indexes for table `payment_schedules`
--
ALTER TABLE `payment_schedules`
  ADD PRIMARY KEY (`schedule_id`);

--
-- Indexes for table `payment_vouchers`
--
ALTER TABLE `payment_vouchers`
  ADD PRIMARY KEY (`voucher_id`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`payroll_id`);

--
-- Indexes for table `payroll_details`
--
ALTER TABLE `payroll_details`
  ADD PRIMARY KEY (`payroll_detail_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`permission_id`);

--
-- Indexes for table `petty_cash_accounts`
--
ALTER TABLE `petty_cash_accounts`
  ADD PRIMARY KEY (`petty_cash_id`);

--
-- Indexes for table `petty_cash_replenishments`
--
ALTER TABLE `petty_cash_replenishments`
  ADD PRIMARY KEY (`replenishment_id`);

--
-- Indexes for table `petty_cash_transactions`
--
ALTER TABLE `petty_cash_transactions`
  ADD PRIMARY KEY (`transaction_id`);

--
-- Indexes for table `plots`
--
ALTER TABLE `plots`
  ADD PRIMARY KEY (`plot_id`);

--
-- Indexes for table `plot_contracts`
--
ALTER TABLE `plot_contracts`
  ADD PRIMARY KEY (`contract_id`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`position_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`project_id`);

--
-- Indexes for table `project_costs`
--
ALTER TABLE `project_costs`
  ADD PRIMARY KEY (`cost_id`);

--
-- Indexes for table `project_sellers`
--
ALTER TABLE `project_sellers`
  ADD PRIMARY KEY (`seller_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`purchase_order_id`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`po_item_id`);

--
-- Indexes for table `purchase_requisitions`
--
ALTER TABLE `purchase_requisitions`
  ADD PRIMARY KEY (`requisition_id`);

--
-- Indexes for table `quotations`
--
ALTER TABLE `quotations`
  ADD PRIMARY KEY (`quotation_id`);

--
-- Indexes for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD PRIMARY KEY (`quotation_item_id`);

--
-- Indexes for table `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`refund_id`);

--
-- Indexes for table `regions`
--
ALTER TABLE `regions`
  ADD PRIMARY KEY (`region_id`);

--
-- Indexes for table `requisition_items`
--
ALTER TABLE `requisition_items`
  ADD PRIMARY KEY (`requisition_item_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`reservation_id`);

--
-- Indexes for table `reservation_cancellations`
--
ALTER TABLE `reservation_cancellations`
  ADD PRIMARY KEY (`cancellation_id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_permission_id`);

--
-- Indexes for table `sales_quotations`
--
ALTER TABLE `sales_quotations`
  ADD PRIMARY KEY (`quotation_id`);

--
-- Indexes for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD PRIMARY KEY (`service_request_id`);

--
-- Indexes for table `service_types`
--
ALTER TABLE `service_types`
  ADD PRIMARY KEY (`service_type_id`);

--
-- Indexes for table `stock_alerts`
--
ALTER TABLE `stock_alerts`
  ADD PRIMARY KEY (`alert_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`movement_id`);

--
-- Indexes for table `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`store_id`);

--
-- Indexes for table `store_locations`
--
ALTER TABLE `store_locations`
  ADD PRIMARY KEY (`store_id`);

--
-- Indexes for table `store_stock`
--
ALTER TABLE `store_stock`
  ADD PRIMARY KEY (`store_stock_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `system_roles`
--
ALTER TABLE `system_roles`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`);

--
-- Indexes for table `tax_transactions`
--
ALTER TABLE `tax_transactions`
  ADD PRIMARY KEY (`tax_transaction_id`);

--
-- Indexes for table `tax_types`
--
ALTER TABLE `tax_types`
  ADD PRIMARY KEY (`tax_type_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_role_id`);

--
-- Indexes for table `villages`
--
ALTER TABLE `villages`
  ADD PRIMARY KEY (`village_id`);

--
-- Indexes for table `wards`
--
ALTER TABLE `wards`
  ADD PRIMARY KEY (`ward_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `approval_actions`
--
ALTER TABLE `approval_actions`
  MODIFY `approval_action_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `approval_levels`
--
ALTER TABLE `approval_levels`
  MODIFY `approval_level_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `approval_requests`
--
ALTER TABLE `approval_requests`
  MODIFY `approval_request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `approval_workflows`
--
ALTER TABLE `approval_workflows`
  MODIFY `workflow_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_categories`
--
ALTER TABLE `asset_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_depreciation`
--
ALTER TABLE `asset_depreciation`
  MODIFY `depreciation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_maintenance`
--
ALTER TABLE `asset_maintenance`
  MODIFY `maintenance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `bank_account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `bank_statements`
--
ALTER TABLE `bank_statements`
  MODIFY `statement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  MODIFY `bank_transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `budgets`
--
ALTER TABLE `budgets`
  MODIFY `budget_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `budget_lines`
--
ALTER TABLE `budget_lines`
  MODIFY `budget_line_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `campaigns`
--
ALTER TABLE `campaigns`
  MODIFY `campaign_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cash_transactions`
--
ALTER TABLE `cash_transactions`
  MODIFY `cash_transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chart_of_accounts`
--
ALTER TABLE `chart_of_accounts`
  MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `cheque_transactions`
--
ALTER TABLE `cheque_transactions`
  MODIFY `cheque_transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commissions`
--
ALTER TABLE `commissions`
  MODIFY `commission_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commission_payments`
--
ALTER TABLE `commission_payments`
  MODIFY `commission_payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commission_payment_requests`
--
ALTER TABLE `commission_payment_requests`
  MODIFY `commission_payment_request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commission_structures`
--
ALTER TABLE `commission_structures`
  MODIFY `commission_structure_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commission_tiers`
--
ALTER TABLE `commission_tiers`
  MODIFY `commission_tier_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `company_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `company_loans`
--
ALTER TABLE `company_loans`
  MODIFY `loan_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `company_loan_payments`
--
ALTER TABLE `company_loan_payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cost_categories`
--
ALTER TABLE `cost_categories`
  MODIFY `cost_category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `creditors`
--
ALTER TABLE `creditors`
  MODIFY `creditor_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `creditor_invoices`
--
ALTER TABLE `creditor_invoices`
  MODIFY `creditor_invoice_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `debtors`
--
ALTER TABLE `debtors`
  MODIFY `debtor_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `direct_expenses`
--
ALTER TABLE `direct_expenses`
  MODIFY `expense_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `districts`
--
ALTER TABLE `districts`
  MODIFY `district_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `document_sequences`
--
ALTER TABLE `document_sequences`
  MODIFY `sequence_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `employee_loans`
--
ALTER TABLE `employee_loans`
  MODIFY `loan_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `expense_claims`
--
ALTER TABLE `expense_claims`
  MODIFY `claim_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expense_claim_items`
--
ALTER TABLE `expense_claim_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fixed_assets`
--
ALTER TABLE `fixed_assets`
  MODIFY `asset_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_audits`
--
ALTER TABLE `inventory_audits`
  MODIFY `audit_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_audit_lines`
--
ALTER TABLE `inventory_audit_lines`
  MODIFY `audit_line_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `movement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `invoice_item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_categories`
--
ALTER TABLE `item_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `journal_entries`
--
ALTER TABLE `journal_entries`
  MODIFY `journal_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `journal_entry_lines`
--
ALTER TABLE `journal_entry_lines`
  MODIFY `line_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `lead_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `leave_applications`
--
ALTER TABLE `leave_applications`
  MODIFY `leave_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `leave_type_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_payments`
--
ALTER TABLE `loan_payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_repayment_schedule`
--
ALTER TABLE `loan_repayment_schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_types`
--
ALTER TABLE `loan_types`
  MODIFY `loan_type_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `attempt_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_templates`
--
ALTER TABLE `notification_templates`
  MODIFY `template_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `payment_approvals`
--
ALTER TABLE `payment_approvals`
  MODIFY `approval_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `payment_recovery`
--
ALTER TABLE `payment_recovery`
  MODIFY `recovery_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_schedules`
--
ALTER TABLE `payment_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_vouchers`
--
ALTER TABLE `payment_vouchers`
  MODIFY `voucher_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `payroll_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payroll_details`
--
ALTER TABLE `payroll_details`
  MODIFY `payroll_detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `permission_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `petty_cash_accounts`
--
ALTER TABLE `petty_cash_accounts`
  MODIFY `petty_cash_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `petty_cash_replenishments`
--
ALTER TABLE `petty_cash_replenishments`
  MODIFY `replenishment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `petty_cash_transactions`
--
ALTER TABLE `petty_cash_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plots`
--
ALTER TABLE `plots`
  MODIFY `plot_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `plot_contracts`
--
ALTER TABLE `plot_contracts`
  MODIFY `contract_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `position_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `project_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `project_costs`
--
ALTER TABLE `project_costs`
  MODIFY `cost_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `project_sellers`
--
ALTER TABLE `project_sellers`
  MODIFY `seller_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `purchase_order_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `po_item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_requisitions`
--
ALTER TABLE `purchase_requisitions`
  MODIFY `requisition_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quotations`
--
ALTER TABLE `quotations`
  MODIFY `quotation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quotation_items`
--
ALTER TABLE `quotation_items`
  MODIFY `quotation_item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refunds`
--
ALTER TABLE `refunds`
  MODIFY `refund_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `regions`
--
ALTER TABLE `regions`
  MODIFY `region_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `requisition_items`
--
ALTER TABLE `requisition_items`
  MODIFY `requisition_item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `reservation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `reservation_cancellations`
--
ALTER TABLE `reservation_cancellations`
  MODIFY `cancellation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `role_permission_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_quotations`
--
ALTER TABLE `sales_quotations`
  MODIFY `quotation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_requests`
--
ALTER TABLE `service_requests`
  MODIFY `service_request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_types`
--
ALTER TABLE `service_types`
  MODIFY `service_type_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_alerts`
--
ALTER TABLE `stock_alerts`
  MODIFY `alert_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `movement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stores`
--
ALTER TABLE `stores`
  MODIFY `store_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_locations`
--
ALTER TABLE `store_locations`
  MODIFY `store_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_stock`
--
ALTER TABLE `store_stock`
  MODIFY `store_stock_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `log_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_roles`
--
ALTER TABLE `system_roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tax_transactions`
--
ALTER TABLE `tax_transactions`
  MODIFY `tax_transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tax_types`
--
ALTER TABLE `tax_types`
  MODIFY `tax_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `user_role_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `villages`
--
ALTER TABLE `villages`
  MODIFY `village_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `wards`
--
ALTER TABLE `wards`
  MODIFY `ward_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cash_transactions`
--
ALTER TABLE `cash_transactions`
  ADD CONSTRAINT `cash_transactions_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`),
  ADD CONSTRAINT `cash_transactions_ibfk_2` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`),
  ADD CONSTRAINT `cash_transactions_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `cheque_transactions`
--
ALTER TABLE `cheque_transactions`
  ADD CONSTRAINT `cheque_transactions_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`),
  ADD CONSTRAINT `cheque_transactions_ibfk_2` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`),
  ADD CONSTRAINT `cheque_transactions_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
