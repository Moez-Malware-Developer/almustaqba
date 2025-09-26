-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 26, 2025 at 02:34 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `workshop_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `credit_sales`
--

CREATE TABLE `credit_sales` (
  `credit_sale_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `initial_payment` decimal(10,2) DEFAULT 0.00,
  `remaining_amount` decimal(10,2) NOT NULL,
  `sale_date` datetime DEFAULT current_timestamp(),
  `monthly_installment` decimal(10,2) DEFAULT NULL,
  `customer_number` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `credit_sales`
--

INSERT INTO `credit_sales` (`credit_sale_id`, `customer_name`, `customer_phone`, `total_amount`, `initial_payment`, `remaining_amount`, `sale_date`, `monthly_installment`, `customer_number`) VALUES
(1, 'محمود الزبون', '0917777777', 900.00, 900.00, 0.00, '2025-09-25 18:09:17', NULL, NULL),
(2, 'ليلى الزبونة', '0928888888', 1200.00, 1200.00, 0.00, '2025-09-25 18:09:17', NULL, NULL),
(3, 'محمد', '0942933793', 875.00, 875.00, 0.00, '2025-09-25 19:04:33', NULL, NULL),
(4, '', '', 2210.00, 2210.00, 0.00, '2025-09-25 19:08:50', NULL, NULL),
(5, 'محمد', '0942933793', 370.00, 370.00, 0.00, '2025-09-25 19:09:58', NULL, NULL),
(6, '', '', 360.00, 360.00, 0.00, '2025-09-25 19:20:12', 8.00, NULL),
(7, '', '', 350.00, 350.00, 0.00, '2025-09-25 21:32:32', 0.00, NULL),
(8, '', '', 370.00, 370.00, 0.00, '2025-09-25 21:32:41', 0.00, NULL),
(9, 'محمد', '0942933793', 195.00, 40.00, 155.00, '2025-09-26 01:44:27', 0.00, NULL),
(10, 'null', 'null', 180.00, 0.00, 180.00, '2025-09-26 01:48:27', 0.00, NULL),
(11, 'اي', '093853', 45.00, 45.00, 0.00, '2025-09-26 01:48:59', 45.00, NULL),
(12, 'محمد', '0942933793', 150.00, 0.00, 150.00, '2025-09-26 01:56:02', 0.00, NULL),
(13, 'محمدc ', '0942933793', 345.00, 200.00, 145.00, '2025-09-26 01:57:55', 12.08, NULL),
(14, 'محمد', '0942933793', 225.00, 0.00, 225.00, '2025-09-26 02:10:55', 0.00, NULL),
(15, 'محمد احمد', '0942933795', 270.00, 37.00, 233.00, '2025-09-26 02:15:32', 7.06, NULL),
(16, 'محمد uf]', '0942933791', 525.00, 525.00, 0.00, '2025-09-26 02:20:38', 20.63, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`expense_id`, `description`, `amount`, `expense_date`, `category`, `notes`) VALUES
(2, 'إيجار المحل', 1200.00, '2024-07-01', 'إيجار', NULL),
(3, 'إيجار', 250.00, '2024-07-05', 'خدمات', 'تت'),
(4, 'كهرباء', 150.00, '2024-07-08', 'تسويق', 'لاي جي'),
(5, 'مصروف آخر', 90.00, '2025-09-25', NULL, 'لاي جي');

-- --------------------------------------------------------

--
-- Table structure for table `installment_payments`
--

CREATE TABLE `installment_payments` (
  `payment_id` int(11) NOT NULL,
  `credit_sale_id` int(11) DEFAULT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `payment_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `installment_payments`
--

INSERT INTO `installment_payments` (`payment_id`, `credit_sale_id`, `payment_amount`, `payment_date`) VALUES
(1, 1, 200.00, '2025-09-25 18:09:17'),
(2, 1, 100.00, '2025-09-25 18:09:17'),
(3, 2, 400.00, '2025-09-25 18:09:17'),
(4, 1, 600.00, '2025-09-25 18:09:32'),
(5, 4, 40.00, '2025-09-25 19:10:24'),
(6, 8, 370.00, '2025-09-26 01:56:24'),
(7, 4, 1970.00, '2025-09-26 01:56:27'),
(8, 6, 360.00, '2025-09-26 01:56:32'),
(9, 7, 350.00, '2025-09-26 01:56:37'),
(10, 9, 40.00, '2025-09-26 01:56:49'),
(11, 2, 800.00, '2025-09-26 02:09:44'),
(12, 3, 875.00, '2025-09-26 02:13:42'),
(13, 5, 370.00, '2025-09-26 02:14:50'),
(14, 15, 7.00, '2025-09-26 02:45:44'),
(15, 16, 495.00, '2025-09-26 02:48:12');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `purchase_price` decimal(10,2) NOT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `purchase_price`, `selling_price`, `stock_quantity`) VALUES
(2, 'شحن', 2.00, 20.00, 0),
(3, 'شاشة سامسونج A12', 120.00, 180.00, 3),
(4, 'بطارية آيفون 11', 90.00, 150.00, 0),
(5, 'شاحن Type-C أصلي', 25.00, 45.00, 40),
(6, 'كيبورد لابتوب Dell', 100.00, 180.00, 0),
(7, 'RAM 8GB DDR4', 200.00, 300.00, 16);

-- --------------------------------------------------------

--
-- Table structure for table `repairs`
--

CREATE TABLE `repairs` (
  `repair_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `device_type` varchar(100) NOT NULL,
  `fault_type` varchar(255) NOT NULL,
  `agreed_price` decimal(10,2) NOT NULL,
  `advance_payment` decimal(10,2) DEFAULT 0.00,
  `additional_notes` text DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'مستلمة',
  `received_at` datetime DEFAULT current_timestamp(),
  `assigned_to` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `repair_log`
--

CREATE TABLE `repair_log` (
  `log_id` int(11) NOT NULL,
  `repair_id` int(11) DEFAULT NULL,
  `status_change` varchar(50) NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `repair_parts_used`
--

CREATE TABLE `repair_parts_used` (
  `repair_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_used` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL,
  `sale_date` datetime DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','card','bank_transfer','credit') DEFAULT 'cash',
  `technician_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`sale_id`, `sale_date`, `total_amount`, `payment_method`, `technician_id`) VALUES
(1, '2025-09-25 19:04:33', 875.00, 'cash', NULL),
(2, '2025-09-25 19:06:41', 575.00, 'cash', NULL),
(4, '2025-09-25 19:08:50', 2210.00, 'cash', NULL),
(6, '2025-09-25 19:09:58', 370.00, 'cash', NULL),
(7, '2025-09-25 19:20:12', 360.00, 'cash', NULL),
(8, '2025-09-25 21:32:32', 350.00, 'cash', NULL),
(9, '2025-09-25 21:32:41', 370.00, 'cash', NULL),
(10, '2025-09-25 21:32:57', 80.00, 'cash', NULL),
(11, '2025-09-26 01:43:42', 525.00, 'cash', NULL),
(12, '2025-09-26 01:44:27', 195.00, 'cash', NULL),
(13, '2025-09-26 01:48:27', 180.00, 'cash', NULL),
(14, '2025-09-26 01:48:59', 45.00, 'cash', NULL),
(15, '2025-09-26 01:52:17', 375.00, 'cash', NULL),
(16, '2025-09-26 01:56:02', 150.00, 'cash', NULL),
(17, '2025-09-26 01:57:55', 345.00, 'cash', NULL),
(18, '2025-09-26 02:10:55', 225.00, 'cash', NULL),
(19, '2025-09-26 02:15:32', 270.00, 'cash', NULL),
(20, '2025-09-26 02:20:38', 525.00, 'cash', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `sale_item_id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`sale_item_id`, `sale_id`, `product_id`, `quantity`, `price`) VALUES
(1, 2, 4, 1, 150.00),
(2, 2, 3, 1, 180.00),
(3, 2, 2, 1, 20.00),
(4, 2, 6, 1, 180.00),
(5, 2, 5, 1, 45.00),
(9, 4, 4, 1, 150.00),
(10, 4, 3, 2, 180.00),
(11, 4, 2, 10, 20.00),
(12, 4, 7, 2, 300.00),
(13, 4, 6, 5, 180.00),
(16, 6, 2, 2, 20.00),
(17, 6, 3, 1, 180.00),
(18, 6, 4, 1, 150.00),
(19, 7, 6, 2, 180.00),
(20, 8, 3, 1, 180.00),
(21, 8, 2, 1, 20.00),
(22, 8, 4, 1, 150.00),
(23, 9, 2, 2, 20.00),
(24, 9, 4, 1, 150.00),
(25, 9, 3, 1, 180.00),
(26, 10, 2, 4, 20.00),
(27, 11, 4, 2, 150.00),
(28, 11, 3, 1, 180.00),
(29, 11, 5, 1, 45.00),
(30, 12, 4, 1, 150.00),
(31, 12, 5, 1, 45.00),
(32, 13, 3, 1, 180.00),
(33, 14, 5, 1, 45.00),
(34, 15, 3, 1, 180.00),
(35, 15, 4, 1, 150.00),
(36, 15, 5, 1, 45.00),
(37, 16, 4, 1, 150.00),
(38, 17, 7, 1, 300.00),
(39, 17, 5, 1, 45.00),
(40, 18, 3, 1, 180.00),
(41, 18, 5, 1, 45.00),
(42, 19, 5, 2, 45.00),
(43, 19, 3, 1, 180.00),
(44, 20, 3, 1, 180.00),
(45, 20, 5, 1, 45.00),
(46, 20, 7, 1, 300.00);

-- --------------------------------------------------------

--
-- Table structure for table `technicians`
--

CREATE TABLE `technicians` (
  `technician_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `technician_name` varchar(255) NOT NULL,
  `technician_phone` varchar(20) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `specialty` varchar(100) DEFAULT NULL,
  `agreement_type` enum('percentage','salary') DEFAULT NULL,
  `value` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `technicians`
--

INSERT INTO `technicians` (`technician_id`, `full_name`, `technician_name`, `technician_phone`, `hire_date`, `nationality`, `specialty`, `agreement_type`, `value`) VALUES
(2, '', 'معز جلال', '0942933703', NULL, 'ليبي', 'هاروير', 'percentage', 40.00),
(3, '', 'محمد', '0942933703', NULL, 'مصري', 'سفت وير', 'percentage', 50.00);

-- --------------------------------------------------------

--
-- Table structure for table `technician_payments`
--

CREATE TABLE `technician_payments` (
  `payment_id` int(11) NOT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `credit_sales`
--
ALTER TABLE `credit_sales`
  ADD PRIMARY KEY (`credit_sale_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`expense_id`);

--
-- Indexes for table `installment_payments`
--
ALTER TABLE `installment_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `credit_sale_id` (`credit_sale_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `repairs`
--
ALTER TABLE `repairs`
  ADD PRIMARY KEY (`repair_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `repair_log`
--
ALTER TABLE `repair_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `repair_id` (`repair_id`);

--
-- Indexes for table `repair_parts_used`
--
ALTER TABLE `repair_parts_used`
  ADD PRIMARY KEY (`repair_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`sale_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`sale_item_id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `technicians`
--
ALTER TABLE `technicians`
  ADD PRIMARY KEY (`technician_id`);

--
-- Indexes for table `technician_payments`
--
ALTER TABLE `technician_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `technician_id` (`technician_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `credit_sales`
--
ALTER TABLE `credit_sales`
  MODIFY `credit_sale_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `expense_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `installment_payments`
--
ALTER TABLE `installment_payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `repairs`
--
ALTER TABLE `repairs`
  MODIFY `repair_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `repair_log`
--
ALTER TABLE `repair_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `sale_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `technicians`
--
ALTER TABLE `technicians`
  MODIFY `technician_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `technician_payments`
--
ALTER TABLE `technician_payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `installment_payments`
--
ALTER TABLE `installment_payments`
  ADD CONSTRAINT `installment_payments_ibfk_1` FOREIGN KEY (`credit_sale_id`) REFERENCES `credit_sales` (`credit_sale_id`) ON DELETE CASCADE;

--
-- Constraints for table `repairs`
--
ALTER TABLE `repairs`
  ADD CONSTRAINT `repairs_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `technicians` (`technician_id`) ON DELETE SET NULL;

--
-- Constraints for table `repair_log`
--
ALTER TABLE `repair_log`
  ADD CONSTRAINT `repair_log_ibfk_1` FOREIGN KEY (`repair_id`) REFERENCES `repairs` (`repair_id`) ON DELETE CASCADE;

--
-- Constraints for table `repair_parts_used`
--
ALTER TABLE `repair_parts_used`
  ADD CONSTRAINT `repair_parts_used_ibfk_1` FOREIGN KEY (`repair_id`) REFERENCES `repairs` (`repair_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `repair_parts_used_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE SET NULL;

--
-- Constraints for table `technician_payments`
--
ALTER TABLE `technician_payments`
  ADD CONSTRAINT `technician_payments_ibfk_1` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
