-- phpMyAdmin SQL Dump
-- version 4.0.4
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Dec 30, 2025 at 05:44 AM
-- Server version: 5.6.12-log
-- PHP Version: 5.4.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `anugrah_accounting`
--
CREATE DATABASE IF NOT EXISTS `anugrah_accounting` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `anugrah_accounting`;

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `cleanup_expired_otps`()
BEGIN
    DELETE FROM otp_verifications 
    WHERE expires_at < NOW() 
    AND is_verified = 0;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `accounting_services`
--

CREATE TABLE IF NOT EXISTS `accounting_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(255) DEFAULT NULL,
  `user_email` varchar(255) DEFAULT NULL,
  `user_phone` varchar(20) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `service_type` varchar(100) NOT NULL,
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `frequency` varchar(50) DEFAULT NULL,
  `business_type` varchar(100) DEFAULT NULL,
  `number_of_transactions` int(11) DEFAULT NULL,
  `software_used` varchar(100) DEFAULT NULL,
  `payment_status` varchar(50) DEFAULT 'Pending',
  `documents` text,
  `notes` text,
  `urgency` varchar(20) DEFAULT 'Normal',
  `status` varchar(20) DEFAULT 'Pending',
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_service_type` (`service_type`),
  KEY `idx_status` (`status`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_user_email` (`user_email`(191)),
  KEY `idx_user_phone` (`user_phone`),
  KEY `idx_payment_status` (`payment_status`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=15 ;

--
-- Dumping data for table `accounting_services`
--

INSERT INTO `accounting_services` (`id`, `user_name`, `user_email`, `user_phone`, `company_name`, `user_id`, `service_type`, `period_from`, `period_to`, `frequency`, `business_type`, `number_of_transactions`, `software_used`, `payment_status`, `documents`, `notes`, `urgency`, `status`, `assigned_to`, `created_at`, `updated_at`, `submitted_at`) VALUES
(1, NULL, NULL, NULL, NULL, 3, 'Audit Support', '2025-11-30', '2026-11-30', 'Quarterly', 'Partnership', 100000, 'Tally', 'Paid', NULL, '', 'Normal', 'Completed', NULL, '2025-11-30 11:35:41', '2025-12-14 14:02:06', '2025-12-13 12:15:09'),
(2, 'meet modi', 'modi.j@email.com', '9428325639', NULL, NULL, 'Financial Statements', '2025-12-13', '2026-12-13', 'Monthly', '0', 0, 'Tally', 'Paid', NULL, 'ad', 'Normal', 'Completed', NULL, '2025-12-13 12:15:27', '2025-12-14 14:02:06', '2025-12-13 12:15:27'),
(3, 'meet modi', 'modi.j@email.com', '9428325639', NULL, NULL, 'Financial Statements', '2025-12-13', '2026-12-13', 'Monthly', '0', 0, 'Tally', 'Paid', NULL, 'ad', 'Normal', 'Completed', NULL, '2025-12-13 12:17:36', '2025-12-14 14:02:06', '2025-12-13 12:17:36'),
(4, 'meet modi', 'modi.j@email.com', '9428325639', NULL, NULL, 'Financial Statements', '2025-12-13', '2026-12-13', 'Monthly', '0', 0, 'Tally', 'Paid', NULL, 'ad', 'Normal', 'Completed', NULL, '2025-12-13 12:17:40', '2025-12-14 14:02:06', '2025-12-13 12:17:40'),
(5, 'meet modi', 'modi.j@email.com', '9428325639', NULL, NULL, '', '2025-12-13', '2026-12-13', 'Monthly', '0', 0, 'Tally', 'Paid', NULL, 'ad', 'Normal', 'Completed', NULL, '2025-12-13 12:18:02', '2025-12-14 14:02:06', '2025-12-13 12:18:02'),
(6, 'neel', 'neel@gmail.com', '1234567890', NULL, NULL, 'Payroll', '2025-12-13', '2026-02-13', 'Daily', '0', 100000, 'Tally', 'Paid', NULL, 'need proper work', 'Normal', 'Completed', NULL, '2025-12-13 12:19:26', '2025-12-14 14:02:06', '2025-12-13 12:19:26'),
(7, 'neel', 'neel@gmail.com', '1234567890', NULL, NULL, 'Payroll', '2025-12-13', '2026-02-13', 'Daily', 'Partnership', 100000, 'Tally', 'Paid', NULL, 'need proper work', 'Normal', 'Completed', NULL, '2025-12-13 12:22:15', '2025-12-14 14:02:06', '2025-12-13 12:22:15'),
(8, 'neel', 'neel@gmail.com', '1234567890', NULL, NULL, 'Payroll', '2025-12-13', '2026-02-13', 'Daily', 'Partnership', 100000, 'Tally', 'Paid', NULL, 'need proper work', 'Normal', 'Completed', NULL, '2025-12-13 12:22:20', '2025-12-14 14:02:06', '2025-12-13 12:22:20'),
(9, 'satyam bavishi', 'satyambavishi@gmali.com', '8200849299', 'kp enterprice', NULL, 'Audit Support', '2025-12-14', '2025-12-15', 'Weekly', 'Partnership', 1, 'QuickBooks', 'Paid', NULL, 'i need this should be in proper way', 'Urgent', 'Completed', 1, '2025-12-14 12:07:55', '2025-12-14 14:01:47', '2025-12-14 12:07:55'),
(10, 'kavya modi', 'kavyamodi746@gmail.com', '+917041116223', 'km enterprice', NULL, 'Financial Statements', '2025-12-26', '2026-12-26', 'Yearly', 'Private Limited', 0, 'QuickBooks', 'Pending', NULL, '', 'Critical', 'Pending', NULL, '2025-12-26 06:47:28', '2025-12-26 06:47:28', '2025-12-26 06:47:28'),
(11, 'meet', 'kavyamodi889@gmail.com', '8200849299', 'kp enterprice', 1, 'Payroll', '2025-12-27', '2026-01-26', 'Daily', 'Sole Proprietorship', 0, 'Tally', 'Pending', NULL, 'sx', 'Critical', 'Pending', NULL, '2025-12-27 06:19:33', '2025-12-27 06:19:33', '2025-12-27 06:19:33'),
(12, 'meet', 'kavyamodi889@gmail.com', '8200849299', 'kp enterprice', 1, 'Payroll', '2025-12-27', '2026-01-26', 'Daily', 'Sole Proprietorship', 0, 'Tally', 'Pending', NULL, 'sx', 'Critical', 'Pending', NULL, '2025-12-27 06:22:30', '2025-12-27 06:22:30', '2025-12-27 06:22:30'),
(13, 'meet', 'kavyamodi889@gmail.com', '8200849299', 'kp enterprice', 1, 'Financial Statements', '2025-12-27', '2026-12-27', 'Yearly', 'LLP', 1000000, 'Tally', 'Pending', NULL, 'i want in urgent', 'Urgent', 'Pending', NULL, '2025-12-27 06:23:29', '2025-12-27 06:23:29', '2025-12-27 06:23:29'),
(14, 'meet', 'kavyamodi889@gmail.com', '8200849299', 'mm tech', 1, 'Bookkeeping', '2025-12-27', '2026-12-27', 'Monthly', 'Sole Proprietorship', 3999999, 'QuickBooks', 'Pending', NULL, '', 'Normal', 'Pending', NULL, '2025-12-27 06:28:17', '2025-12-27 06:28:17', '2025-12-27 06:28:17');

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `user_email` varchar(255) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_action` (`action`(191)),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_email` (`user_email`(191))
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=111 ;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `user_email`, `admin_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 3, NULL, NULL, 'ACCOUNTING_SERVICE', 'accounting_services', 1, 'Accounting service requested', NULL, NULL, '2025-11-30 11:35:41'),
(2, 4, NULL, NULL, 'Feedback Submission', NULL, NULL, 'Service: GST Returns, Rating: 5 stars', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-30 11:38:44'),
(3, 4, NULL, NULL, 'Feedback Submission', NULL, NULL, 'Service: MSME Registration, Rating: 5 stars', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-30 11:39:20'),
(4, 4, NULL, NULL, 'Contact Message Sent', NULL, NULL, 'Sent contact message for Accounting', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-12-02 08:28:34'),
(5, 4, NULL, NULL, 'Feedback Submitted', NULL, NULL, 'Submitted feedback for FSSAI Licence with rating 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-12-02 08:29:35'),
(6, 4, NULL, NULL, 'GST_REGISTRATION', 'gst_registrations', 1, 'GST Registration applied', NULL, NULL, '2025-12-02 08:51:55'),
(7, 4, NULL, NULL, 'Contact Message Sent', NULL, NULL, 'Sent contact message for Income Tax Return', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-12-02 08:53:33'),
(8, 4, NULL, NULL, 'ITR_SUBMISSION', 'income_tax_returns', 1, 'Income Tax Return submitted', NULL, NULL, '2025-12-02 08:54:48'),
(9, 2, NULL, NULL, 'MSME_REGISTRATION', 'msme_registrations', 1, 'MSME Registration applied', NULL, NULL, '2025-12-02 09:20:53'),
(10, NULL, NULL, 1, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-12-02 10:06:59'),
(11, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-12-02 11:34:00'),
(12, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-12-02 11:34:22'),
(13, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-12-02 11:37:08'),
(14, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-12-02 11:41:57'),
(15, 3, NULL, NULL, 'TAX_PLANNING', 'tax_planning', 1, 'Tax planning consultation requested', NULL, NULL, '2025-12-02 17:07:43'),
(16, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-12-02 17:08:40'),
(17, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-12-03 08:19:33'),
(18, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-12-03 16:25:04'),
(19, NULL, NULL, NULL, 'USER_STATUS_UPDATE', NULL, NULL, 'Updated user status for user ID: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-12-03 16:26:55'),
(20, NULL, NULL, NULL, 'USER_STATUS_UPDATE', NULL, NULL, 'Updated user status for user ID: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-12-03 16:27:19'),
(21, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-07 08:38:37'),
(22, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-07 08:38:47'),
(23, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-08 05:17:37'),
(24, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-08 05:35:24'),
(25, NULL, NULL, NULL, 'MESSAGE_STATUS_UPDATE', NULL, NULL, 'Updated contact message status for ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-08 05:38:06'),
(26, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-08 05:39:02'),
(27, NULL, NULL, NULL, 'USER_STATUS_UPDATE', NULL, NULL, 'Updated user status for user ID: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-08 05:39:15'),
(28, NULL, NULL, NULL, 'USER_STATUS_UPDATE', NULL, NULL, 'Updated user status for user ID: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-08 05:39:18'),
(29, NULL, NULL, NULL, 'LOGOUT', NULL, NULL, 'Admin logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-08 05:50:43'),
(30, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-12 06:00:36'),
(31, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-12 06:01:19'),
(32, 5, NULL, NULL, 'Feedback Submitted', NULL, NULL, 'Submitted feedback for MSME Registration with rating 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-12 06:20:38'),
(33, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-12 06:20:44'),
(34, NULL, NULL, NULL, 'LOGOUT', NULL, NULL, 'Admin logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-12 06:21:44'),
(35, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-12 06:21:59'),
(36, NULL, NULL, NULL, 'USER_STATUS_UPDATE', NULL, NULL, 'Updated user status for user ID: 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-12 06:22:58'),
(37, NULL, NULL, NULL, 'USER_STATUS_UPDATE', NULL, NULL, 'Updated user status for user ID: 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-12 06:23:16'),
(38, NULL, NULL, NULL, 'BULK_USER_UPDATE', NULL, NULL, 'Bulk action:  on 5 users', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-12 06:40:03'),
(39, NULL, NULL, NULL, 'BULK_EMAIL_SENT', NULL, NULL, 'Sent bulk email to 5 recipients', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-12 06:40:46'),
(40, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-12 09:54:54'),
(41, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-12 09:59:24'),
(42, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-12 10:38:02'),
(43, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-13 11:14:22'),
(44, 6, NULL, NULL, 'Feedback Submitted', NULL, NULL, 'Submitted feedback for Accounting with rating 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-13 11:50:15'),
(45, NULL, 'modi.j@email.com', NULL, 'ACCOUNTING_SERVICE', 'accounting_services', 2, 'Accounting service requested', NULL, NULL, '2025-12-13 12:15:27'),
(46, NULL, 'modi.j@email.com', NULL, 'ACCOUNTING_SERVICE', 'accounting_services', 3, 'Accounting service requested', NULL, NULL, '2025-12-13 12:17:36'),
(47, NULL, 'modi.j@email.com', NULL, 'ACCOUNTING_SERVICE', 'accounting_services', 4, 'Accounting service requested', NULL, NULL, '2025-12-13 12:17:40'),
(48, NULL, 'modi.j@email.com', NULL, 'ACCOUNTING_SERVICE', 'accounting_services', 5, 'Accounting service requested', NULL, NULL, '2025-12-13 12:18:02'),
(49, NULL, 'neel@gmail.com', NULL, 'ACCOUNTING_SERVICE', 'accounting_services', 6, 'Accounting service requested', NULL, NULL, '2025-12-13 12:19:26'),
(50, NULL, 'neel@gmail.com', NULL, 'ACCOUNTING_SERVICE', 'accounting_services', 7, 'Accounting service requested', NULL, NULL, '2025-12-13 12:22:15'),
(51, NULL, 'neel@gmail.com', NULL, 'ACCOUNTING_SERVICE', 'accounting_services', 8, 'Accounting service requested', NULL, NULL, '2025-12-13 12:22:20'),
(52, NULL, 'satyambavishi@gmali.com', NULL, 'ACCOUNTING_SERVICE', 'accounting_services', 9, 'Accounting service requested', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-14 12:07:55'),
(53, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-14 12:17:24'),
(54, NULL, NULL, NULL, 'BULK_USER_UPDATE', NULL, NULL, 'Bulk action:  on 6 users', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-14 12:17:48'),
(55, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-14 13:05:12'),
(56, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-18 08:24:15'),
(57, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-18 09:09:52'),
(58, NULL, NULL, NULL, 'USER_STATUS_UPDATE', NULL, NULL, 'Updated user status for user ID: 6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-18 09:10:12'),
(59, NULL, NULL, NULL, 'USER_STATUS_UPDATE', NULL, NULL, 'Updated user status for user ID: 6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-18 09:10:19'),
(60, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-18 09:25:26'),
(61, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-19 14:40:09'),
(62, NULL, NULL, NULL, 'LOGOUT', NULL, NULL, 'Admin logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-19 15:02:02'),
(63, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-19 15:02:29'),
(64, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-19 15:31:03'),
(65, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-21 05:30:08'),
(66, 12, NULL, NULL, 'ITR_SUBMISSION', 'income_tax_returns', 1, 'Income Tax Return submitted', NULL, NULL, '2025-12-23 05:29:11'),
(67, 4, NULL, NULL, 'ITR_SUBMISSION', 'income_tax_returns', 2, 'Income Tax Return submitted', NULL, NULL, '2025-12-23 05:31:27'),
(68, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 10:54:46'),
(69, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-23 11:14:57'),
(70, 5, NULL, NULL, 'FSSAI_APPLICATION', 'fssai_licences', 1, 'FSSAI Licence applied', NULL, NULL, '2025-12-24 05:52:09'),
(71, 5, NULL, NULL, 'CMA_SUBMISSION', 'cma_data', 1, 'CMA Data submitted', NULL, NULL, '2025-12-24 06:16:59'),
(72, 4, NULL, NULL, 'TAX_PLANNING', 'tax_planning', 2, 'Tax planning consultation requested', NULL, NULL, '2025-12-24 11:19:39'),
(73, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-24 11:19:54'),
(74, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-25 05:43:22'),
(75, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-25 06:06:41'),
(76, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-25 06:15:03'),
(77, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-25 11:19:34'),
(78, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-25 11:26:45'),
(79, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-26 05:51:39'),
(80, NULL, NULL, NULL, 'LOGOUT', NULL, NULL, 'Admin logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-26 05:53:19'),
(81, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-26 05:56:00'),
(82, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-26 06:27:02'),
(83, NULL, 'kavyamodi746@gmail.com', NULL, 'ACCOUNTING_SERVICE', 'accounting_services', 10, 'Accounting service requested', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-26 06:47:28'),
(84, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-26 07:12:20'),
(85, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-26 16:41:53'),
(86, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-26 16:42:08'),
(87, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-26 16:51:09'),
(88, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-26 17:03:18'),
(89, 1, 'kavyamodi889@gmail.com', NULL, 'ACCOUNTING_SERVICE', 'accounting_services', 11, 'Accounting service requested', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-27 06:19:33'),
(90, 1, 'kavyamodi889@gmail.com', NULL, 'ACCOUNTING_SERVICE', 'accounting_services', 12, 'Accounting service requested', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-27 06:22:30'),
(91, 1, 'kavyamodi889@gmail.com', NULL, 'ACCOUNTING_SERVICE', 'accounting_services', 13, 'Accounting service requested', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-27 06:23:29'),
(92, 1, 'kavyamodi889@gmail.com', NULL, 'ACCOUNTING_SERVICE', 'accounting_services', 14, 'Accounting service requested', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-27 06:28:17'),
(93, 2, NULL, NULL, 'GST_RETURN_FILED', 'gst_returns', 1, 'GST Return filed', NULL, NULL, '2025-12-27 08:12:55'),
(94, 2, 'kavyamodi746@gmail.com', NULL, 'FSSAI_APPLICATION', 'fssai_licences', 2, 'FSSAI Licence applied', NULL, NULL, '2025-12-27 08:56:55'),
(95, 2, NULL, NULL, 'CMA_SUBMISSION', 'cma_data', 2, 'CMA Data submitted', NULL, NULL, '2025-12-27 09:19:23'),
(96, 2, NULL, NULL, 'MSME_REGISTRATION', 'msme_registrations', 2, 'MSME Registration submitted', NULL, NULL, '2025-12-27 09:39:10'),
(97, 1, NULL, NULL, 'MSME_REGISTRATION', 'msme_registrations', 3, 'MSME Registration submitted', NULL, NULL, '2025-12-27 09:50:52'),
(98, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-27 10:05:12'),
(99, NULL, NULL, NULL, 'LOGOUT', NULL, NULL, 'Admin logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-27 10:06:04'),
(100, 1, NULL, NULL, 'ITR_SUBMISSION', 'income_tax_returns', 3, 'Income Tax Return submitted', NULL, NULL, '2025-12-27 17:54:36'),
(101, 1, NULL, NULL, 'TAX_PLANNING', 'tax_planning', 3, 'Tax planning consultation requested', NULL, NULL, '2025-12-27 18:00:28'),
(102, 1, NULL, NULL, 'TAX_PLANNING', 'tax_planning', 4, 'Tax planning consultation requested', NULL, NULL, '2025-12-29 03:25:22'),
(103, 1, NULL, NULL, 'TAX_PLANNING', 'tax_planning', 5, 'Tax planning consultation requested', NULL, NULL, '2025-12-29 03:29:47'),
(104, 1, NULL, NULL, 'TAX_PLANNING', 'tax_planning', 6, 'Tax planning consultation requested', NULL, NULL, '2025-12-29 03:29:55'),
(105, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-29 03:31:04'),
(106, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-29 12:33:45'),
(107, NULL, NULL, NULL, 'LOGOUT', NULL, NULL, 'Admin logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-29 13:30:33'),
(108, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-29 18:28:20'),
(109, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-30 05:31:22'),
(110, NULL, NULL, NULL, 'LOGIN', NULL, NULL, 'Admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-30 05:41:49');

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE IF NOT EXISTS `admin_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info' COMMENT 'info, success, warning, danger, form_submission, user_activity, payment, document, status_update',
  `action_url` varchar(500) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'Related user if any',
  `is_read` tinyint(1) DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_type` (`type`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'Staff',
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_username` (`username`),
  UNIQUE KEY `unique_admin_email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `full_name`, `email`, `phone`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin123', 'System Administrator', 'anugrah0369@gmail.com', '8000687342', 'Super Admin', 1, '2025-12-30 05:41:49', '2025-11-30 11:27:26', '2025-12-30 05:41:49'),
(2, 'staff', '12345', 'Staff Member', 'staff@anugrah.com', '9876543210', 'Staff', 1, NULL, '2025-11-30 11:27:26', '2025-12-02 09:55:41');

-- --------------------------------------------------------

--
-- Table structure for table `bulk_emails`
--

CREATE TABLE IF NOT EXISTS `bulk_emails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `recipient_type` varchar(50) NOT NULL,
  `recipient_count` int(11) NOT NULL,
  `sent_by` int(11) NOT NULL,
  `sent_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sent_by` (`sent_by`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;

--
-- Dumping data for table `bulk_emails`
--

INSERT INTO `bulk_emails` (`id`, `subject`, `message`, `recipient_type`, `recipient_count`, `sent_by`, `sent_at`) VALUES
(1, 'new feature', 'Dear {name},\r\n\r\nWe''re excited to announce a new feature on Anugrah Accounting Services!\r\n\r\n???? What''s New:\r\n[Describe your new feature here]\r\n\r\nThis enhancement will help you [explain benefits]...\r\n\r\nVisit our website to learn more and start using this feature today!\r\n\r\nBest regards,\r\nAnugrah Accounting Team', 'all_users', 5, 1, '2025-12-12 12:10:46');

-- --------------------------------------------------------

--
-- Table structure for table `bulk_messages`
--

CREATE TABLE IF NOT EXISTS `bulk_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `send_via` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_count` int(11) DEFAULT '0',
  `whatsapp_count` int(11) DEFAULT '0',
  `sent_by` int(11) NOT NULL,
  `sent_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sent_by` (`sent_by`),
  KEY `sent_at` (`sent_at`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=5 ;

--
-- Dumping data for table `bulk_messages`
--

INSERT INTO `bulk_messages` (`id`, `subject`, `message`, `recipient_type`, `send_via`, `email_count`, `whatsapp_count`, `sent_by`, `sent_at`) VALUES
(1, 'Leave Intimation - Office Closure', 'Dear {name},\\r\\n\\r\\n(LEAVE INTIMATION)\\r\\n\\r\\nI am on leave from {start_date} to {end_date} for {reason}.\\r\\n\\r\\nI AM AVAILABLE FROM {return_date}\\r\\n\\r\\nANY ASSISTANCE YOU CAN WHATSAPP ME OR YOU CAN CONTACT MY OFFICE NUMBER:\\r\\n???? 02642-227258\\r\\n???? 6352788126\\r\\n???? 6351894607\\r\\n\\r\\n???? 10:00 AM TO 6:00 PM\\r\\n\\r\\nSORRY FOR INCONVENIENCE\\r\\n\\r\\nREGARDS ????\\r\\nAnugrah Accounting\\r\\nA HOPE FOR EVERY FAMILY TO BE SECURED AND WEALTHY', 'all_users', 'email,whatsapp', 0, 0, 1, '2025-12-21 11:15:31'),
(2, 'Service Update - Anugrah Accounting', 'Dear {name},\\r\\n\\r\\nWe are pleased to inform you about our latest service updates at Anugrah Accounting Services.\\r\\n\\r\\n???? {update_details}\\r\\n\\r\\nThese enhancements are designed to serve you better and provide more efficient solutions for your financial needs.\\r\\n\\r\\nFor any queries or assistance, please feel free to contact us:\\r\\n???? Phone Number\\r\\n???? Email Address\\r\\n???? Website\\r\\n\\r\\nThank you for your continued trust.\\r\\n\\r\\nREGARDS ????\\r\\nAnugrah Accounting\\r\\nA HOPE FOR EVERY FAMILY TO BE SECURED AND WEALTHY', 'all_users', 'whatsapp', 0, 0, 1, '2025-12-21 11:15:58'),
(3, 'Leave Intimation - Office Closure', 'Dear {name},\\r\\n\\r\\n(LEAVE INTIMATION)\\r\\n\\r\\nI am on leave from {start_date} to {end_date} for {reason}.\\r\\n\\r\\nI AM AVAILABLE FROM {return_date}\\r\\n\\r\\nANY ASSISTANCE YOU CAN WHATSAPP ME OR YOU CAN CONTACT MY OFFICE NUMBER:\\r\\n???? 02642-227258\\r\\n???? 6352788126\\r\\n???? 6351894607\\r\\n\\r\\n???? 10:00 AM TO 6:00 PM\\r\\n\\r\\nSORRY FOR INCONVENIENCE\\r\\n\\r\\nREGARDS ????\\r\\nAnugrah Accounting\\r\\nA HOPE FOR EVERY FAMILY TO BE SECURED AND WEALTHY', 'all_users', 'whatsapp', 0, 0, 1, '2025-12-23 16:25:10'),
(4, 'Following Up on Your Inquiry', 'Dear {name},\\r\\n\\r\\nWe wanted to follow up on your recent inquiry with Anugrah Accounting Services.\\r\\n\\r\\nWe\\''re here to help you with any questions or concerns you may have about our services. Please let us know if you\\''d like to schedule a consultation or if you need any additional information.\\r\\n\\r\\nWe value your interest and look forward to assisting you.\\r\\n\\r\\nBest Regards,\\r\\nAnugrah Accounting Services', 'all_users', 'email', 0, 0, 1, '2025-12-23 16:43:55');

-- --------------------------------------------------------

--
-- Table structure for table `cma_data`
--

CREATE TABLE IF NOT EXISTS `cma_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(255) DEFAULT NULL,
  `user_email` varchar(255) DEFAULT NULL,
  `user_phone` varchar(20) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `business_name` varchar(255) NOT NULL,
  `pan_number` varchar(10) NOT NULL,
  `financial_year` varchar(10) NOT NULL,
  `loan_amount` decimal(15,2) DEFAULT NULL,
  `loan_purpose` varchar(255) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `projected_sales` decimal(15,2) DEFAULT NULL,
  `projected_profit` decimal(15,2) DEFAULT NULL,
  `current_assets` decimal(15,2) DEFAULT NULL,
  `current_liabilities` decimal(15,2) DEFAULT NULL,
  `fixed_assets` decimal(15,2) DEFAULT NULL,
  `working_capital` decimal(15,2) DEFAULT NULL,
  `debt_equity_ratio` decimal(10,4) DEFAULT NULL,
  `report_path` varchar(255) DEFAULT NULL,
  `documents` text,
  `notes` text,
  `itr_year1_file` varchar(255) DEFAULT NULL,
  `itr_year2_file` varchar(255) DEFAULT NULL,
  `itr_year3_file` varchar(255) DEFAULT NULL,
  `loan_statement_file` varchar(255) DEFAULT NULL,
  `request_type` varchar(50) DEFAULT 'document_submission',
  `detail_request_sent` tinyint(1) DEFAULT '0',
  `detail_request_date` timestamp NULL DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_pan` (`pan_number`),
  KEY `idx_financial_year` (`financial_year`),
  KEY `idx_status` (`status`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_user_email` (`user_email`(191)),
  KEY `idx_user_phone` (`user_phone`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `cma_data`
--

INSERT INTO `cma_data` (`id`, `user_name`, `user_email`, `user_phone`, `user_id`, `business_name`, `pan_number`, `financial_year`, `loan_amount`, `loan_purpose`, `bank_name`, `projected_sales`, `projected_profit`, `current_assets`, `current_liabilities`, `fixed_assets`, `working_capital`, `debt_equity_ratio`, `report_path`, `documents`, `notes`, `itr_year1_file`, `itr_year2_file`, `itr_year3_file`, `loan_statement_file`, `request_type`, `detail_request_sent`, `detail_request_date`, `status`, `assigned_to`, `created_at`, `updated_at`, `submitted_at`) VALUES
(1, 'meet', 'kavyamodi660@gmail.com', '7041116223', 5, 'km enterprice', 'LQDTD5444T', '2023-24', '600000.00', 'business extension', 'sbi', '10000000.00', '4000000.00', '200000.00', '100000.00', '200000.00', '100000.00', '1.0000', NULL, NULL, '', NULL, NULL, NULL, NULL, 'document_submission', 0, NULL, 'Pending', NULL, '2025-12-24 06:16:59', '2025-12-24 06:16:59', '2025-12-24 06:16:59'),
(2, 'priyani patel', 'kavyamodi746@gmail.com', '7041116223', 2, 'kp enterprice', 'DAJPC4150P', '2023-24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', 'DAJPC4150P_ITR_Year1_1766827163.pdf', 'DAJPC4150P_ITR_Year2_1766827163.pdf', 'DAJPC4150P_ITR_Year3_1766827163.png', 'DAJPC4150P_LoanStatement_1766827163.png', 'document_submission', 0, NULL, 'Pending', NULL, '2025-12-27 09:19:23', '2025-12-27 09:19:23', '2025-12-27 09:19:23');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `service_interest` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `status` varchar(20) DEFAULT 'New',
  `priority` varchar(20) DEFAULT 'Normal',
  `assigned_to` int(11) DEFAULT NULL,
  `admin_notes` text,
  `response_sent` tinyint(1) DEFAULT '0',
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_service_interest` (`service_interest`(100))
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=4 ;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `user_id`, `name`, `email`, `phone`, `subject`, `service_interest`, `message`, `status`, `priority`, `assigned_to`, `admin_notes`, `response_sent`, `responded_at`, `created_at`, `updated_at`) VALUES
(1, 4, 'kavya modi', 'kavyamodi746@gmail.com', '7041116223', NULL, 'Accounting', 'sdx', 'New', 'Normal', 1, 'sure, you can came to our office with required document', 0, NULL, '2025-12-02 08:28:34', '2025-12-08 05:38:06'),
(2, 4, 'priyani patel', 'kavyamodi746@gmail.com', '7041116223', NULL, 'Income Tax Return', 'xk,', 'New', 'Normal', NULL, NULL, 0, NULL, '2025-12-02 08:53:33', '2025-12-02 08:53:33'),
(3, NULL, 'meet', 'kavyamodi889@gmail.com', '8200849299', 'GST Returns Inquiry', 'GST Returns', 'jkk', 'New', 'Normal', NULL, NULL, 0, NULL, '2025-12-27 06:51:05', '2025-12-27 06:51:05');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE IF NOT EXISTS `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `service_type` varchar(100) DEFAULT NULL COMMENT 'GST, ITR, MSME, etc.',
  `service_id` int(11) DEFAULT NULL COMMENT 'Related service application ID',
  `document_type` varchar(100) NOT NULL COMMENT 'PAN, Aadhaar, Certificate, etc.',
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL COMMENT 'Size in bytes',
  `file_extension` varchar(10) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL COMMENT 'Admin who uploaded',
  `description` text,
  `status` varchar(20) DEFAULT 'Active' COMMENT 'Active, Archived, Deleted',
  `verified` tinyint(1) DEFAULT '0',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_service_type` (`service_type`),
  KEY `idx_service_id` (`service_id`),
  KEY `idx_document_type` (`document_type`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `fk_doc_uploaded_by` (`uploaded_by`),
  KEY `fk_doc_verified_by` (`verified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `document_categories`
--

CREATE TABLE IF NOT EXISTS `document_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `icon` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_category_name` (`name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=7 ;

--
-- Dumping data for table `document_categories`
--

INSERT INTO `document_categories` (`id`, `name`, `description`, `icon`, `is_active`, `created_at`) VALUES
(1, 'Identity Proof', 'PAN Card, Aadhaar, Passport, etc.', 'id-card', 1, '2025-12-08 05:24:23'),
(2, 'Address Proof', 'Utility bills, Bank statements, etc.', 'home', 1, '2025-12-08 05:24:23'),
(3, 'Registration Certificates', 'GST, MSME, FSSAI certificates', 'certificate', 1, '2025-12-08 05:24:23'),
(4, 'Financial Documents', 'ITR, Balance sheets, P&L statements', 'file-invoice', 1, '2025-12-08 05:24:23'),
(5, 'Bank Documents', 'Bank statements, Cheques', 'university', 1, '2025-12-08 05:24:23'),
(6, 'Other Documents', 'Miscellaneous documents', 'folder', 1, '2025-12-08 05:24:23');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE IF NOT EXISTS `feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `service_used` varchar(255) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL,
  `feedback_text` text NOT NULL,
  `is_published` tinyint(1) DEFAULT '0',
  `admin_response` text,
  `responded_by` int(11) DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_rating` (`rating`),
  KEY `idx_service_used` (`service_used`(191)),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_responded_by` (`responded_by`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=6 ;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `user_id`, `service_used`, `rating`, `feedback_text`, `is_published`, `admin_response`, `responded_by`, `responded_at`, `created_at`) VALUES
(1, 4, 'GST Returns', 5, 'xz', 1, NULL, NULL, NULL, '2025-11-30 11:38:44'),
(3, 4, 'FSSAI Licence', 5, 'ssswds', 1, 'we are happy to help you', 1, '2025-12-12 06:00:54', '2025-12-02 08:29:35'),
(4, 5, 'MSME Registration', 4, 'nice work and very friendly', 1, NULL, NULL, NULL, '2025-12-12 06:20:38'),
(5, 6, 'Accounting', 5, '''Excellent service! They helped me file my ITR on time and saved me a lot of tax. Very professional and knowledgeable team.'',', 1, 'Thank you so much for your wonderful feedback! We''re thrilled to hear that you had a positive experience with our Accounting. Your satisfaction is our top priority, and we look forward to serving you again!', 1, '2025-12-19 15:20:34', '2025-12-13 11:50:15');

-- --------------------------------------------------------

--
-- Stand-in structure for view `fssai_applications_view`
--
CREATE TABLE IF NOT EXISTS `fssai_applications_view` (
`id` int(11)
,`user_name` varchar(255)
,`user_email` varchar(255)
,`user_phone` varchar(20)
,`business_name` varchar(255)
,`pan_number` varchar(10)
,`business_type` varchar(100)
,`licence_type` varchar(50)
,`food_category` varchar(255)
,`state` varchar(100)
,`pincode` varchar(10)
,`annual_turnover` decimal(15,2)
,`status` varchar(20)
,`created_at` timestamp
,`submitted_at` timestamp
,`registered_user_name` varchar(100)
,`registered_user_email` varchar(100)
);
-- --------------------------------------------------------

--
-- Table structure for table `fssai_licences`
--

CREATE TABLE IF NOT EXISTS `fssai_licences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(255) DEFAULT NULL,
  `user_email` varchar(255) DEFAULT NULL,
  `user_phone` varchar(20) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `business_name` varchar(255) NOT NULL,
  `pan_number` varchar(10) DEFAULT NULL,
  `business_type` varchar(100) DEFAULT NULL,
  `licence_type` varchar(50) DEFAULT NULL,
  `food_category` varchar(255) DEFAULT NULL,
  `business_address` text,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `fssai_number` varchar(20) DEFAULT NULL,
  `licence_issue_date` date DEFAULT NULL,
  `licence_expiry_date` date DEFAULT NULL,
  `annual_turnover` decimal(15,2) DEFAULT NULL,
  `number_of_employees` int(11) DEFAULT NULL,
  `water_source` varchar(100) DEFAULT NULL,
  `waste_disposal` varchar(100) DEFAULT NULL,
  `certificate_path` varchar(255) DEFAULT NULL,
  `documents` text COMMENT 'JSON format for multiple documents including rent agreement',
  `notes` text,
  `status` varchar(20) DEFAULT 'Pending',
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_fssai_number` (`fssai_number`),
  KEY `idx_status` (`status`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_user_email` (`user_email`(191)),
  KEY `idx_user_phone` (`user_phone`),
  KEY `idx_pan_number` (`pan_number`),
  KEY `idx_business_name` (`business_name`(100)),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_submitted_at` (`submitted_at`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `fssai_licences`
--

INSERT INTO `fssai_licences` (`id`, `user_name`, `user_email`, `user_phone`, `user_id`, `business_name`, `pan_number`, `business_type`, `licence_type`, `food_category`, `business_address`, `state`, `pincode`, `fssai_number`, `licence_issue_date`, `licence_expiry_date`, `annual_turnover`, `number_of_employees`, `water_source`, `waste_disposal`, `certificate_path`, `documents`, `notes`, `status`, `assigned_to`, `created_at`, `updated_at`, `submitted_at`) VALUES
(1, NULL, NULL, NULL, 5, 'km enterprice', NULL, 'Restaurant', 'State', 'Ice Cream & Desserts', 'b-12', 'Gujarat', '392001', NULL, NULL, NULL, '200000.00', NULL, NULL, NULL, NULL, '{}', '', 'Pending', NULL, '2025-12-24 05:52:09', '2025-12-27 08:26:17', '2025-12-24 05:52:09'),
(2, 'priyani patel', 'kavyamodi746@gmail.com', '7041116223', 2, 'kp enterprice', 'DAJPC4150P', 'Cloud Kitchen', 'Basic', 'Cooked Food Service', '392001', 'Gujarat', '392001', NULL, NULL, NULL, '200000.00', 7, 'Municipal Supply', 'Municipal Collection', NULL, '{"rent_agreement":"uploads\\/fssai_documents\\/rent_agreement_1766825815_694f9f572d3b5.png"}', '', 'Pending', NULL, '2025-12-27 08:56:55', '2025-12-27 08:56:55', '2025-12-27 08:56:55');

--
-- Triggers `fssai_licences`
--
DROP TRIGGER IF EXISTS `auto_determine_licence_type`;
DELIMITER //
CREATE TRIGGER `auto_determine_licence_type` BEFORE INSERT ON `fssai_licences`
 FOR EACH ROW BEGIN
    IF NEW.annual_turnover < 1200000 THEN
        SET NEW.licence_type = 'Basic';
    ELSEIF NEW.annual_turnover >= 1200000 AND NEW.annual_turnover <= 20000000 THEN
        SET NEW.licence_type = 'State';
    ELSE
        SET NEW.licence_type = 'Central';
    END IF;
END
//
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `gst_registrations`
--

CREATE TABLE IF NOT EXISTS `gst_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(255) DEFAULT NULL,
  `user_email` varchar(255) DEFAULT NULL,
  `user_phone` varchar(20) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `business_name` varchar(255) NOT NULL,
  `business_type` varchar(100) DEFAULT NULL,
  `pan_number` varchar(10) NOT NULL,
  `business_address` text,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `business_activity` text,
  `estimated_turnover` decimal(15,2) DEFAULT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `registration_date` date DEFAULT NULL,
  `certificate_path` varchar(255) DEFAULT NULL,
  `documents` text,
  `notes` text,
  `registration_urgency` varchar(20) DEFAULT 'Standard',
  `status` varchar(20) DEFAULT 'Pending',
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_pan` (`pan_number`),
  KEY `idx_gstin` (`gstin`),
  KEY `idx_status` (`status`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_user_email` (`user_email`(191)),
  KEY `idx_user_phone` (`user_phone`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=2 ;

--
-- Dumping data for table `gst_registrations`
--

INSERT INTO `gst_registrations` (`id`, `user_name`, `user_email`, `user_phone`, `user_id`, `business_name`, `business_type`, `pan_number`, `business_address`, `state`, `pincode`, `business_activity`, `estimated_turnover`, `gstin`, `registration_date`, `certificate_path`, `documents`, `notes`, `registration_urgency`, `status`, `assigned_to`, `created_at`, `updated_at`, `submitted_at`) VALUES
(1, NULL, NULL, NULL, 4, 'grow', 'Partnership', 'DAJPC4150P', 'b-12', 'Gujarat', '392001', 'axxax', '2000000.00', NULL, NULL, NULL, NULL, 'sx', 'Standard', 'Pending', NULL, '2025-12-02 08:51:55', '2025-12-02 08:51:55', '2025-12-13 12:15:10');

-- --------------------------------------------------------

--
-- Table structure for table `gst_returns`
--

CREATE TABLE IF NOT EXISTS `gst_returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(255) DEFAULT NULL,
  `user_email` varchar(255) DEFAULT NULL,
  `user_phone` varchar(20) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `gstin` varchar(15) NOT NULL,
  `return_type` varchar(20) NOT NULL,
  `return_period` varchar(20) NOT NULL,
  `financial_year` varchar(10) NOT NULL,
  `filing_date` date DEFAULT NULL,
  `total_sales` decimal(15,2) DEFAULT NULL,
  `total_purchases` decimal(15,2) DEFAULT NULL,
  `exempt_sales` decimal(15,2) DEFAULT '0.00',
  `zero_rated_sales` decimal(15,2) DEFAULT '0.00',
  `output_tax` decimal(15,2) DEFAULT NULL,
  `input_tax_credit` decimal(15,2) DEFAULT NULL,
  `tax_payable` decimal(15,2) DEFAULT NULL,
  `tax_paid` decimal(15,2) DEFAULT NULL,
  `interest_amount` decimal(15,2) DEFAULT '0.00',
  `late_fee` decimal(15,2) DEFAULT '0.00',
  `arn_number` varchar(50) DEFAULT NULL,
  `documents` text,
  `notes` text,
  `status` varchar(20) DEFAULT 'Pending',
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_gstin` (`gstin`),
  KEY `idx_return_type` (`return_type`),
  KEY `idx_return_period` (`return_period`),
  KEY `idx_status` (`status`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_user_email` (`user_email`(191)),
  KEY `idx_user_phone` (`user_phone`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=2 ;

--
-- Dumping data for table `gst_returns`
--

INSERT INTO `gst_returns` (`id`, `user_name`, `user_email`, `user_phone`, `user_id`, `gstin`, `return_type`, `return_period`, `financial_year`, `filing_date`, `total_sales`, `total_purchases`, `exempt_sales`, `zero_rated_sales`, `output_tax`, `input_tax_credit`, `tax_payable`, `tax_paid`, `interest_amount`, `late_fee`, `arn_number`, `documents`, `notes`, `status`, `assigned_to`, `created_at`, `updated_at`, `submitted_at`) VALUES
(1, 'priyani patel', 'kavyamodi746@gmail.com', '7041116223', 2, '27ABCDE1234F1Z5', 'GSTR-1', '', '2023-24', NULL, '200000.00', '500000.00', '0.00', '0.00', '0.00', '0.00', '0.00', NULL, '0.00', '0.00', NULL, NULL, '', 'Pending', NULL, '2025-12-27 08:12:55', '2025-12-27 08:12:55', '2025-12-27 08:12:55');

-- --------------------------------------------------------

--
-- Table structure for table `income_tax_returns`
--

CREATE TABLE IF NOT EXISTS `income_tax_returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `assessment_year` varchar(10) NOT NULL,
  `financial_year` varchar(10) NOT NULL,
  `pan_number` varchar(10) NOT NULL,
  `aadhaar_number` varchar(12) NOT NULL,
  `return_type` varchar(50) NOT NULL,
  `salary_income` decimal(15,2) DEFAULT '0.00',
  `business_income` decimal(15,2) DEFAULT '0.00',
  `capital_gains` decimal(15,2) DEFAULT '0.00',
  `other_income` decimal(15,2) DEFAULT '0.00',
  `total_income` decimal(15,2) DEFAULT '0.00',
  `section_80c` decimal(15,2) DEFAULT '0.00',
  `section_80d` decimal(15,2) DEFAULT '0.00',
  `home_loan_interest` decimal(15,2) DEFAULT '0.00',
  `other_deductions` decimal(15,2) DEFAULT '0.00',
  `total_deductions` decimal(15,2) DEFAULT '0.00',
  `tax_payable` decimal(15,2) DEFAULT '0.00',
  `tds_deducted` decimal(15,2) DEFAULT '0.00',
  `advance_tax` decimal(15,2) DEFAULT '0.00',
  `self_assessment_tax` decimal(15,2) DEFAULT '0.00',
  `tax_paid` decimal(15,2) DEFAULT '0.00',
  `bank_name` varchar(255) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `ifsc_code` varchar(11) DEFAULT NULL,
  `bank_statement_path` varchar(500) DEFAULT NULL,
  `notes` text,
  `status` varchar(50) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_itr` (`pan_number`,`assessment_year`,`financial_year`),
  KEY `idx_pan` (`pan_number`),
  KEY `idx_user` (`user_id`),
  KEY `idx_ay` (`assessment_year`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4 ;

--
-- Dumping data for table `income_tax_returns`
--

INSERT INTO `income_tax_returns` (`id`, `user_id`, `assessment_year`, `financial_year`, `pan_number`, `aadhaar_number`, `return_type`, `salary_income`, `business_income`, `capital_gains`, `other_income`, `total_income`, `section_80c`, `section_80d`, `home_loan_interest`, `other_deductions`, `total_deductions`, `tax_payable`, `tds_deducted`, `advance_tax`, `self_assessment_tax`, `tax_paid`, `bank_name`, `account_number`, `ifsc_code`, `bank_statement_path`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(1, 12, '2024-25', '2023-24', 'BAJPC4350M', '123456789012', 'Individual', '200000.00', '0.00', '0.00', '0.00', '200000.00', '3909.00', '25000.00', '0.00', '0.00', '28909.00', '78655.00', '2000.00', '0.00', '0.00', '2000.00', 'bank of india', '123445754332', 'GHFV0002314', NULL, '', 'Pending', '2025-12-23 05:29:11', '2025-12-23 05:29:11'),
(2, 4, '2024-25', '2022-23', 'DAJPC4150P', '123456782203', 'Business', '500000.00', '50000.00', '0.00', '0.00', '550000.00', '5000.00', '26000.00', '50000.00', '0.00', '81000.00', '5000.00', '2000.00', '0.00', '0.00', '2000.00', 'bank of india', '1234457542203', 'GHFV0002203', NULL, '', 'Pending', '2025-12-23 05:31:27', '2025-12-23 05:31:27'),
(3, 1, '2025-26', '2024-25', 'UWPCL6780T', '123456782210', 'Business', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'bank of india', '1234457542203', 'GHFV0002203', 'uploads/bank_statements/bank_stmt_1766858076_69501d5cc382b.png', '', 'Pending', '2025-12-27 17:54:36', '2025-12-27 17:54:36');

-- --------------------------------------------------------

--
-- Table structure for table `msme_registrations`
--

CREATE TABLE IF NOT EXISTS `msme_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(255) DEFAULT NULL,
  `user_email` varchar(255) DEFAULT NULL,
  `user_phone` varchar(20) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `business_name` varchar(255) NOT NULL,
  `trade_name` varchar(255) DEFAULT NULL,
  `business_type` varchar(100) DEFAULT NULL,
  `pan_number` varchar(10) NOT NULL,
  `aadhaar_number` varchar(12) DEFAULT NULL,
  `business_address` text,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `investment_amount` decimal(15,2) DEFAULT NULL,
  `annual_turnover` decimal(15,2) DEFAULT NULL,
  `number_of_employees` int(11) DEFAULT NULL,
  `udyam_number` varchar(50) DEFAULT NULL,
  `registration_date` date DEFAULT NULL,
  `certificate_path` varchar(255) DEFAULT NULL,
  `passport_photo` varchar(255) DEFAULT NULL,
  `documents` text,
  `notes` text,
  `detailed_info_requested` tinyint(1) DEFAULT '0',
  `info_request_date` timestamp NULL DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_pan` (`pan_number`),
  KEY `idx_udyam` (`udyam_number`),
  KEY `idx_status` (`status`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_user_email` (`user_email`(191)),
  KEY `idx_user_phone` (`user_phone`),
  KEY `idx_trade_name` (`trade_name`(191))
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=4 ;

--
-- Dumping data for table `msme_registrations`
--

INSERT INTO `msme_registrations` (`id`, `user_name`, `user_email`, `user_phone`, `user_id`, `business_name`, `trade_name`, `business_type`, `pan_number`, `aadhaar_number`, `business_address`, `state`, `pincode`, `investment_amount`, `annual_turnover`, `number_of_employees`, `udyam_number`, `registration_date`, `certificate_path`, `passport_photo`, `documents`, `notes`, `detailed_info_requested`, `info_request_date`, `status`, `assigned_to`, `created_at`, `updated_at`, `submitted_at`) VALUES
(1, NULL, NULL, NULL, 2, 'grow', NULL, 'Manufacturing', 'BAJPC4350M', '123456789012', 'asd', 'Gujarat', '392001', '2000000.00', '6000000.00', 200, NULL, NULL, NULL, NULL, NULL, 'asa', 0, NULL, 'In Progress', 1, '2025-12-02 09:20:53', '2025-12-07 08:45:20', '2025-12-13 12:15:11'),
(2, NULL, NULL, NULL, 2, 'kp enterprice', '', 'Manufacturing', 'PEVFV4506Y', '123456782203', 'kkkk', 'Gujarat', '392001', NULL, NULL, NULL, NULL, NULL, NULL, 'uploads/msme_photos/passport_1766828350_694fa93e8e202.png', NULL, '0', 1, '2025-12-27 04:09:10', 'Pending', NULL, '2025-12-27 09:39:10', '2025-12-27 09:39:10', '2025-12-27 09:39:10'),
(3, 'meet', 'kavyamodi889@gmail.com', '8200849299', 1, 'kp enterprice', '', 'Trading', 'UWPCL6780T', '123456782210', '392001', 'Gujarat', '392001', NULL, NULL, NULL, NULL, NULL, NULL, 'uploads/msme_photos/passport_1766829052_694fabfc3d1cb.png', NULL, 'ok we will inform you ', 1, '2025-12-27 04:20:52', 'In Progress', 1, '2025-12-27 09:50:52', '2025-12-27 10:05:56', '2025-12-27 09:50:52');

-- --------------------------------------------------------

--
-- Table structure for table `notification_preferences`
--

CREATE TABLE IF NOT EXISTS `notification_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email_enabled` tinyint(1) DEFAULT '1',
  `sms_enabled` tinyint(1) DEFAULT '1',
  `whatsapp_enabled` tinyint(1) DEFAULT '1',
  `status_updates` tinyint(1) DEFAULT '1',
  `expiry_reminders` tinyint(1) DEFAULT '1',
  `deadline_reminders` tinyint(1) DEFAULT '1',
  `promotional` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `otp_verifications`
--

CREATE TABLE IF NOT EXISTS `otp_verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `phone` varchar(15) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `otp` varchar(6) NOT NULL,
  `otp_type` varchar(50) NOT NULL COMMENT 'password_reset, registration, verification',
  `is_verified` tinyint(1) DEFAULT '0',
  `attempt_count` int(11) DEFAULT '0',
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `verified_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_email` (`email`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_otp_type` (`otp_type`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_otp_lookup` (`phone`,`otp`,`otp_type`,`expires_at`),
  KEY `idx_otp_cleanup` (`is_verified`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `scheduled_notifications`
--

CREATE TABLE IF NOT EXISTS `scheduled_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('email','sms','whatsapp','all') NOT NULL,
  `notification_type` varchar(100) NOT NULL COMMENT 'e.g., expiry_reminder, deadline_reminder',
  `subject` varchar(500) DEFAULT NULL,
  `message` text NOT NULL,
  `scheduled_for` datetime NOT NULL,
  `status` enum('pending','sent','failed','cancelled') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_message` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_scheduled_for` (`scheduled_for`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `service_activity_log`
--

CREATE TABLE IF NOT EXISTS `service_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_id` int(11) NOT NULL,
  `service_type` varchar(50) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `admin_id` (`admin_id`),
  KEY `service_type` (`service_type`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=5 ;

--
-- Dumping data for table `service_activity_log`
--

INSERT INTO `service_activity_log` (`id`, `service_id`, `service_type`, `admin_id`, `action`, `description`, `created_at`) VALUES
(1, 1, 'accounting', 1, 'service_created', 'New accounting service request received', '2025-11-30 06:05:41'),
(2, 1, 'accounting', 1, 'status_update', 'Status: Pending - Awaiting initial review', '2025-12-13 13:44:32'),
(3, 2, 'accounting', 1, 'service_created', 'New accounting service request received', '2025-12-13 06:45:27'),
(4, 2, 'accounting', 1, 'document_received', 'Client documents uploaded', '2025-12-14 11:44:32');

-- --------------------------------------------------------

--
-- Table structure for table `service_applications`
--

CREATE TABLE IF NOT EXISTS `service_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `service_type` varchar(50) NOT NULL,
  `application_number` varchar(50) DEFAULT NULL,
  `form_data` text,
  `amount` decimal(15,2) DEFAULT NULL,
  `payment_status` varchar(50) DEFAULT 'Pending',
  `documents` text,
  `notes` text,
  `status` varchar(20) DEFAULT 'Pending',
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_service_type` (`service_type`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_assigned_to` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `tax_planning`
--

CREATE TABLE IF NOT EXISTS `tax_planning` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(255) DEFAULT NULL,
  `user_email` varchar(255) DEFAULT NULL,
  `user_phone` varchar(20) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `financial_year` varchar(10) NOT NULL,
  `assessment_year` varchar(10) NOT NULL,
  `consultation_date` date DEFAULT NULL,
  `income_sources` text,
  `existing_investments` text,
  `recommended_investments` text,
  `estimated_tax_liability` decimal(15,2) DEFAULT NULL,
  `potential_tax_saving` decimal(15,2) DEFAULT NULL,
  `strategies_suggested` text,
  `documents` text,
  `notes` text,
  `status` varchar(20) DEFAULT 'Pending',
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_financial_year` (`financial_year`),
  KEY `idx_status` (`status`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_user_email` (`user_email`(191)),
  KEY `idx_user_phone` (`user_phone`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=7 ;

--
-- Dumping data for table `tax_planning`
--

INSERT INTO `tax_planning` (`id`, `user_name`, `user_email`, `user_phone`, `user_id`, `financial_year`, `assessment_year`, `consultation_date`, `income_sources`, `existing_investments`, `recommended_investments`, `estimated_tax_liability`, `potential_tax_saving`, `strategies_suggested`, `documents`, `notes`, `status`, `assigned_to`, `created_at`, `updated_at`, `submitted_at`) VALUES
(1, NULL, NULL, NULL, 3, '2023-24', '2024-25', '2026-01-10', '["knn "]', '["jnm"]', NULL, '200000.00', '28888.00', 'zjxjzbn', NULL, 'bzjiskzm', 'Pending', NULL, '2025-12-02 17:07:43', '2025-12-02 17:07:43', '2025-12-13 12:15:12'),
(2, NULL, NULL, NULL, 4, '2024-25', '2025-26', '2025-12-24', '[]', '[]', NULL, '0.00', '0.00', '', NULL, '\n\n--- Additional Information ---\n{"occupation":"Business","age_group":"Below 30","financial_goals":[],"estimated_annual_income":200000,"current_investments":50000,"tax_regime":"New Regime","risk_appetite":"Conservative","investment_horizon":"Short-term (1-3 years)","specific_concerns":""}', 'Pending', NULL, '2025-12-24 11:19:39', '2025-12-24 11:19:39', '2025-12-24 11:19:39'),
(3, NULL, NULL, NULL, 1, '2024-25', '2025-26', '2025-12-27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', 'Pending', NULL, '2025-12-27 18:00:28', '2025-12-27 18:00:28', '2025-12-27 18:00:28'),
(4, NULL, NULL, NULL, 1, '2024-25', '2025-26', '2025-12-30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', 'Pending', NULL, '2025-12-29 03:25:22', '2025-12-29 03:25:22', '2025-12-29 03:25:22'),
(5, 'meet', 'kavyamodi889@gmail.com', '8200849299', 1, '2024-25', '2025-26', '2025-12-30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', 'Pending', NULL, '2025-12-29 03:29:47', '2025-12-29 03:29:47', '2025-12-29 03:29:47'),
(6, 'meet', 'kavyamodi889@gmail.com', '8200849299', 1, '2024-25', '2025-26', '2026-01-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', 'Pending', NULL, '2025-12-29 03:29:55', '2025-12-29 03:29:55', '2025-12-29 03:29:55');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_gstin` (`gstin`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `company_name`, `gstin`, `is_active`, `password`, `created_at`, `updated_at`) VALUES
(1, 'meet', 'kavyamodi889@gmail.com', '8200849299', NULL, NULL, 1, 'e10adc3949ba59abbe56e057f20f883e', '2025-12-26 10:22:58', '2025-12-26 10:22:58'),
(2, 'priyani patel', 'kavyamodi746@gmail.com', '7041116223', NULL, NULL, 1, 'a35cc7f023832e4ff11f63fec092fe06', '2025-12-27 08:09:39', '2025-12-27 08:09:39');

-- --------------------------------------------------------

--
-- Table structure for table `user_notifications`
--

CREATE TABLE IF NOT EXISTS `user_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info' COMMENT 'info, success, warning, danger, status_update, payment, document',
  `action_url` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_type` (`type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_messages`
--

CREATE TABLE IF NOT EXISTS `whatsapp_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `recipient_phone` varchar(20) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `message_id` varchar(100) DEFAULT NULL,
  `error_message` text,
  `sent_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_recipient_phone` (`recipient_phone`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_message_id` (`message_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=2 ;

--
-- Dumping data for table `whatsapp_messages`
--

INSERT INTO `whatsapp_messages` (`id`, `admin_id`, `recipient_phone`, `recipient_name`, `message`, `status`, `message_id`, `error_message`, `sent_at`, `delivered_at`, `created_at`) VALUES
(1, 1, '917041116223', 'kavya modi', '???? Dear kavya modi,\r\n\r\n*LEAVE INTIMATION*\r\n\r\nI am on leave from [START_DATE] to [END_DATE].\r\n\r\nI AM AVAILABLE FROM [RETURN_DATE]\r\n\r\nFOR ASSISTANCE:\r\n???? 02642-227258\r\n???? 8000687342\r\n\r\n???? 10:00 AM TO 6:00 PM\r\n\r\nREGARDS ????\r\n*Anugrah Accounting*', 'sent', NULL, NULL, NULL, NULL, '2025-12-29 04:10:06');

-- --------------------------------------------------------

--
-- Structure for view `fssai_applications_view`
--
DROP TABLE IF EXISTS `fssai_applications_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `fssai_applications_view` AS select `fl`.`id` AS `id`,`fl`.`user_name` AS `user_name`,`fl`.`user_email` AS `user_email`,`fl`.`user_phone` AS `user_phone`,`fl`.`business_name` AS `business_name`,`fl`.`pan_number` AS `pan_number`,`fl`.`business_type` AS `business_type`,`fl`.`licence_type` AS `licence_type`,`fl`.`food_category` AS `food_category`,`fl`.`state` AS `state`,`fl`.`pincode` AS `pincode`,`fl`.`annual_turnover` AS `annual_turnover`,`fl`.`status` AS `status`,`fl`.`created_at` AS `created_at`,`fl`.`submitted_at` AS `submitted_at`,`u`.`name` AS `registered_user_name`,`u`.`email` AS `registered_user_email` from (`fssai_licences` `fl` left join `users` `u` on((`fl`.`user_id` = `u`.`id`))) order by `fl`.`created_at` desc;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounting_services`
--
ALTER TABLE `accounting_services`
  ADD CONSTRAINT `fk_acc_admin` FOREIGN KEY (`assigned_to`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_acc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `fk_log_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD CONSTRAINT `fk_admin_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bulk_emails`
--
ALTER TABLE `bulk_emails`
  ADD CONSTRAINT `bulk_emails_ibfk_1` FOREIGN KEY (`sent_by`) REFERENCES `admin_users` (`id`);

--
-- Constraints for table `cma_data`
--
ALTER TABLE `cma_data`
  ADD CONSTRAINT `fk_cma_admin` FOREIGN KEY (`assigned_to`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cma_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD CONSTRAINT `fk_contact_admin` FOREIGN KEY (`assigned_to`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_contact_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `fk_doc_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_doc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_doc_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_feedback_admin` FOREIGN KEY (`responded_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fssai_licences`
--
ALTER TABLE `fssai_licences`
  ADD CONSTRAINT `fk_fssai_admin` FOREIGN KEY (`assigned_to`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_fssai_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `gst_registrations`
--
ALTER TABLE `gst_registrations`
  ADD CONSTRAINT `fk_gst_reg_admin` FOREIGN KEY (`assigned_to`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_gst_reg_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `gst_returns`
--
ALTER TABLE `gst_returns`
  ADD CONSTRAINT `fk_gst_ret_admin` FOREIGN KEY (`assigned_to`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_gst_ret_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `income_tax_returns`
--
ALTER TABLE `income_tax_returns`
  ADD CONSTRAINT `income_tax_returns_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `msme_registrations`
--
ALTER TABLE `msme_registrations`
  ADD CONSTRAINT `fk_msme_admin` FOREIGN KEY (`assigned_to`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_msme_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD CONSTRAINT `fk_notif_pref_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  ADD CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scheduled_notifications`
--
ALTER TABLE `scheduled_notifications`
  ADD CONSTRAINT `fk_sched_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_activity_log`
--
ALTER TABLE `service_activity_log`
  ADD CONSTRAINT `fk_activity_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `service_applications`
--
ALTER TABLE `service_applications`
  ADD CONSTRAINT `fk_sa_admin` FOREIGN KEY (`assigned_to`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tax_planning`
--
ALTER TABLE `tax_planning`
  ADD CONSTRAINT `fk_tax_plan_admin` FOREIGN KEY (`assigned_to`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tax_plan_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `fk_user_notif` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `whatsapp_messages`
--
ALTER TABLE `whatsapp_messages`
  ADD CONSTRAINT `fk_whatsapp_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
