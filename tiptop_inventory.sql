-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 08, 2025 at 04:01 AM
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
-- Database: `tiptop_inventory`
--

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `id` int(11) NOT NULL,
  `purchase_order_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `actual_delivery_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `status` enum('Scheduled','In Transit','Delivered','Delayed','Cancelled') DEFAULT 'Scheduled',
  `tracking_number` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `stock` int(11) DEFAULT 0,
  `price` decimal(10,2) DEFAULT 0.00,
  `barcode` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `expiration_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `name`, `stock`, `price`, `barcode`, `quantity`, `is_active`, `expiration_date`) VALUES
(9, 'tissue', 18, 222.00, '833992870530', 0, 1, NULL),
(10, 'chair', 1, 222.00, '834613396354', 0, 1, NULL),
(11, 'charger', 1, 200.00, '914223459688', 0, 1, NULL),
(12, 'case', 22, 150.00, '914877562224', 0, 1, NULL),
(13, 'AIRCON', 9, 2500.00, '918238911199', 0, 1, NULL),
(14, 'battery', 12, 150.00, '919805671428', 0, 1, NULL),
(15, 'perfume', 18, 250.00, '037942888991', 0, 1, NULL),
(16, 'RGB LIGHTS', 1, 350.00, '139561632186', 0, 1, NULL),
(17, 'bag', 4, 150.00, '170191802665', 0, 1, NULL),
(18, 'socket', 4, 80.00, '170763791774', 0, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `unit` varchar(50) DEFAULT 'pcs',
  `cost_price` decimal(10,2) DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `minimum_stock` int(11) DEFAULT 0,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `status` enum('Pending','Completed','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `order_date` datetime DEFAULT current_timestamp(),
  `delivery_date` datetime DEFAULT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `actual_delivery_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `priority` enum('Low','Medium','High') DEFAULT 'Medium',
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `supplier_id`, `item_id`, `quantity`, `status`, `created_at`, `total_amount`, `order_date`, `delivery_date`, `expected_delivery_date`, `actual_delivery_date`, `notes`, `priority`, `created_by`, `updated_at`) VALUES
(20, 10, NULL, NULL, 'Completed', '2025-09-06 03:06:23', 2500.00, '2025-09-05 23:06:23', NULL, '2025-09-07', '2025-09-07', '2222', 'Medium', 1, '2025-09-07 17:36:13'),
(22, 10, NULL, NULL, 'Cancelled', '2025-09-07 17:03:35', 666.00, '2025-09-07 13:03:35', NULL, '2025-09-12', NULL, 'ingat', 'High', 1, '2025-09-07 17:17:15'),
(23, 10, NULL, NULL, 'Completed', '2025-09-07 17:06:22', 5000.00, '2025-09-07 13:06:22', NULL, '2025-09-08', NULL, '22', 'Medium', 1, '2025-09-07 17:31:25'),
(24, 11, NULL, NULL, 'Completed', '2025-09-07 17:38:19', 2500.00, '2025-09-07 13:38:19', NULL, '2025-09-07', NULL, '222', 'High', 1, '2025-09-07 17:40:01'),
(26, 11, NULL, NULL, 'Cancelled', '2025-09-07 17:40:38', 250.00, '2025-09-07 13:40:38', NULL, '2025-09-07', NULL, '', 'Low', 1, '2025-09-07 17:42:23'),
(27, 11, NULL, NULL, 'Completed', '2025-09-07 17:43:18', 2500.00, '2025-09-07 13:43:18', NULL, '2025-09-09', NULL, '32', 'High', 1, '2025-09-07 17:43:59');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `received_quantity` int(11) DEFAULT 0,
  `purchase_order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_order_items`
--

INSERT INTO `purchase_order_items` (`id`, `po_id`, `inventory_id`, `item_name`, `quantity`, `unit_cost`, `total_cost`, `received_quantity`, `purchase_order_id`, `item_id`, `unit_price`, `total_price`) VALUES
(14, 20, NULL, 'AIRCON', 1, 2500.00, 2500.00, 0, 0, 13, 0.00, 0.00),
(16, 22, NULL, 'chair', 3, 222.00, 666.00, 0, 0, 10, 0.00, 0.00),
(17, 23, NULL, 'AIRCON', 2, 2500.00, 5000.00, 0, 0, 13, 0.00, 0.00),
(18, 24, NULL, 'AIRCON', 1, 2500.00, 2500.00, 0, 0, 13, 0.00, 0.00),
(20, 26, NULL, 'perfume', 1, 250.00, 250.00, 0, 0, 15, 0.00, 0.00),
(21, 27, NULL, 'AIRCON', 1, 2500.00, 2500.00, 0, 0, 13, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sale_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `user_id`, `item_id`, `quantity`, `total_amount`, `created_at`, `sale_date`) VALUES
(5, 1, 14, 10, 1500.00, '2025-09-05 01:07:24', '2025-09-04 21:07:24'),
(7, 1, 9, 1, 222.00, '2025-09-06 04:42:08', '2025-09-06 00:42:08'),
(8, 1, 11, 1, 200.00, '2025-09-06 04:42:08', '2025-09-06 00:42:08'),
(10, 1, 9, 2, 444.00, '2025-09-06 05:40:11', '2025-09-06 01:40:11'),
(11, 1, 15, 1, 250.00, '2025-09-06 05:42:17', '2025-09-06 01:42:17'),
(12, 1, 15, 2, 500.00, '2025-09-06 05:44:14', '2025-09-06 01:44:14'),
(13, 1, 16, 1, 350.00, '2025-09-06 06:55:36', '2025-09-06 02:55:36'),
(14, 1, 16, 1, 350.00, '2025-09-06 15:43:09', '2025-09-06 11:43:09'),
(15, 1, 18, 1, 80.00, '2025-09-06 16:43:49', '2025-09-06 12:43:49'),
(16, 1, 10, 1, 222.00, '2025-09-06 16:47:02', '2025-09-06 12:47:02'),
(18, 1, 17, 1, 150.00, '2025-09-06 17:35:47', '2025-09-06 13:35:47'),
(19, 1, 18, 1, 80.00, '2025-09-06 19:40:03', '2025-09-06 15:40:03'),
(20, 1, 9, 1, 222.00, '2025-09-07 02:00:49', '2025-09-06 22:00:49'),
(21, 1, 15, 1, 250.00, '2025-09-07 02:48:08', '2025-09-06 22:48:08'),
(22, 1, 13, 1, 2500.00, '2025-09-07 18:40:53', '2025-09-07 14:40:53');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `rating` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact`, `address`, `email`, `phone`, `payment_terms`, `rating`, `created_at`, `updated_at`) VALUES
(10, 'kent', 'kent', 'TUPI', 'kent@gmail.com', '123123123', 'COD', 5, '2025-09-05 02:00:36', '2025-09-05 02:00:36'),
(11, 'ej', 'kulot', 'General Santos City', 'ejromero294@gmail.com', '09103443488', 'COD', 2, '2025-09-07 17:37:12', '2025-09-07 17:49:52');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_contacts`
--

CREATE TABLE `supplier_contacts` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `position` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier_documents`
--

CREATE TABLE `supplier_documents` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_type` enum('Contract','Certificate','License','Insurance','Other') DEFAULT 'Other',
  `file_path` varchar(500) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier_performance`
--

CREATE TABLE `supplier_performance` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `month_year` date NOT NULL,
  `total_orders` int(11) DEFAULT 0,
  `completed_orders` int(11) DEFAULT 0,
  `cancelled_orders` int(11) DEFAULT 0,
  `total_value` decimal(15,2) DEFAULT 0.00,
  `avg_delivery_time` decimal(5,2) DEFAULT 0.00,
  `on_time_deliveries` int(11) DEFAULT 0,
  `late_deliveries` int(11) DEFAULT 0,
  `quality_rating` decimal(3,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','cashier') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(1, 'admin', '$2y$10$Yl1Xr1U6mM38v2R9qN5FeuXz7F2n2v9uJrXkM4UNnH1R6k2TfKj36', 'admin'),
(2, 'cashier', '$2y$10$wUqXk4rTgRjQb4M9B4pO5u8cHjQy9dHh2Qq3GmUzqGg9Vf9uPZ1Se', 'cashier');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_purchase_order_id` (`purchase_order_id`),
  ADD KEY `idx_delivery_date` (`delivery_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_sku` (`sku`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `fk_purchase_orders_created_by` (`created_by`),
  ADD KEY `fk_purchase_orders_supplier_id` (`supplier_id`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `fk_inventory_item_id` (`item_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `sales_ibfk_2` (`item_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `supplier_contacts`
--
ALTER TABLE `supplier_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_supplier_id` (`supplier_id`);

--
-- Indexes for table `supplier_documents`
--
ALTER TABLE `supplier_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_expiry_date` (`expiry_date`);

--
-- Indexes for table `supplier_performance`
--
ALTER TABLE `supplier_performance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_supplier_month` (`supplier_id`,`month_year`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_month_year` (`month_year`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `supplier_contacts`
--
ALTER TABLE `supplier_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplier_documents`
--
ALTER TABLE `supplier_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplier_performance`
--
ALTER TABLE `supplier_performance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `fk_purchase_orders_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_purchase_orders_supplier_id` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory` (`id`);

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `fk_inventory_item_id` FOREIGN KEY (`item_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_order_items_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_contacts`
--
ALTER TABLE `supplier_contacts`
  ADD CONSTRAINT `supplier_contacts_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_documents`
--
ALTER TABLE `supplier_documents`
  ADD CONSTRAINT `supplier_documents_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_performance`
--
ALTER TABLE `supplier_performance`
  ADD CONSTRAINT `supplier_performance_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
