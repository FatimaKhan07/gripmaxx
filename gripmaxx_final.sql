-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 25, 2026 at 01:57 AM
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
-- Database: `gripmaxx`
--

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `message` text NOT NULL,
  `message_status` varchar(20) NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `message`, `message_status`, `created_at`) VALUES
(2, 'Fatima Khan', '05khanfatima@gmail.com', 'abcfghjkloouytre', 'replied', '2026-04-21 01:58:18'),
(3, 'Fatima Khan', '05khanfatima@gmail.com', 'I haven\'t received my order yet.', 'replied', '2026-04-23 09:52:59'),
(4, 'Fatima', 'fatima@gmail.com', 'This is a test message.', 'closed', '2026-04-25 04:30:40'),
(5, 'Fatima', 'fatimakhan10a22@gmail.com', 'This is a final test msg.', 'replied', '2026-04-25 04:33:51');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `account_username` varchar(100) DEFAULT NULL,
  `customer_name` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(120) NOT NULL,
  `pincode` varchar(10) NOT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(20) NOT NULL DEFAULT 'cod',
  `payment_status` varchar(40) NOT NULL DEFAULT 'Pending on Delivery',
  `payment_reference` varchar(80) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `order_date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `account_username`, `customer_name`, `phone`, `address`, `city`, `pincode`, `total`, `payment_method`, `payment_status`, `payment_reference`, `status`, `order_date`) VALUES
(1, 15, 'abcde', 'Fatima Khan', '9594640006', 'Bandra West', 'Mumbai', '400050', 550.00, 'cod', 'Pending on Delivery', NULL, 'Pending', '2026-04-23 09:48:23'),
(2, 15, 'abcde', 'Fatima Khan', '9594640006', '31/248, Transit Camp, Reclamation', 'Mumbai', '400050', 545.00, 'cod', 'Pending on Delivery', NULL, 'Cancelled', '2026-04-23 09:50:04'),
(3, 15, 'abcde', 'Fatima Khan', '9594640006', 'Bandra', 'Mumbai', '400050', 449.00, 'cod', 'Pending on Delivery', NULL, 'Processing', '2026-04-23 09:51:20'),
(4, 15, 'abcde', 'Fatima Khan', '9594640006', '31/248, Transit Camp, Reclamation', 'Mumbai', '400050', 545.00, 'qr', 'Failed', 'UPI784512369874', 'Pending', '2026-04-24 13:46:43'),
(5, 15, 'abcde', 'Fatima Khan', '9594640006', '31/248, Transit Camp, Reclamation', 'Mumbai', '400050', 249.00, 'qr', 'Paid', 'UPI784512369874', 'Pending', '2026-04-24 13:50:48'),
(6, 15, 'abcde', 'Fatima Khan', '9594640006', '31/248, Transit Camp, Reclamation', 'Mumbai', '400050', 349.00, 'cod', 'Pending on Delivery', '', 'Pending', '2026-04-24 13:53:35'),
(7, 15, 'abcde', 'Fatima Khan', '9594640006', '31/248, Transit Camp, Reclamation', 'Mumbai', '400050', 545.00, 'qr', 'Paid', 'UPI784512369874', 'Pending', '2026-04-24 14:59:11'),
(8, 15, 'abcde', 'Fatima Khan', '9594640006', '31/248, Transit Camp, Reclamation', 'Mumbai', '400050', 1149.00, 'cod', 'Pending on Delivery', NULL, 'Pending', '2026-04-24 23:30:14'),
(9, 28, 'Fatima', 'Fatima', '9564896541', 'Bandra', 'Mumbai', '410410', 749.00, 'cod', 'Pending on Delivery', NULL, 'Cancelled', '2026-04-25 04:20:15'),
(10, 28, 'Fatima', 'Fatima', '6549889654', 'Mumbai', 'nnn', '123456', 3365.00, 'cod', 'Pending on Delivery', NULL, 'Delivered', '2026-04-25 04:21:59');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(190) NOT NULL,
  `size` varchar(50) NOT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `size`, `price`, `quantity`) VALUES
(1, 1, 5, 'Belt', '1 kg', 500.00, 1),
(2, 2, 4, 'Dumbbell', '5 kg', 500.00, 1),
(3, 3, 2, 'GripMaxx Gym Chalk', '250g', 399.00, 1),
(4, 4, 4, 'Dumbbell', '5 kg', 500.00, 1),
(5, 5, 6, 'Protein Powder', '1 kg', 199.00, 1),
(6, 6, 11, 'Protein Powder', '2 kg', 299.00, 1),
(7, 7, 4, 'Dumbbell', '5 kg', 500.00, 1),
(8, 8, 13, 'Barbell', '20kg', 1099.00, 1),
(9, 9, 3, 'GripMaxx Gym Chalk', '500g', 699.00, 1),
(10, 10, 2, 'GripMaxx Gym Chalk', '250g', 399.00, 1),
(11, 10, 5, 'Belt', '1 kg', 500.00, 1),
(12, 10, 8, 'Belt', '250 g', 120.00, 1),
(13, 10, 6, 'Protein Powder', '1 kg', 199.00, 1),
(14, 10, 11, 'Protein Powder', '2 kg', 299.00, 1),
(15, 10, 9, 'Kettle Bells', '20 kg', 699.00, 1),
(16, 10, 13, 'Barbell', '20kg', 1099.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `size` varchar(20) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `is_popular` tinyint(1) NOT NULL DEFAULT 0,
  `shipping_mode` varchar(20) NOT NULL DEFAULT 'default',
  `shipping_cost` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `size`, `price`, `image`, `description`, `stock`, `status`, `is_popular`, `shipping_mode`, `shipping_cost`) VALUES
(1, 'GripMaxx Gym Chalk', '100g', 199.00, 'chalk.png', 'This is gymchalk.', 10, 'active', 0, 'default', 0.00),
(2, 'GripMaxx Gym Chalk', '250g', 399.00, 'chalk.png', 'abcd', 10, 'active', 0, 'default', 0.00),
(3, 'GripMaxx Gym Chalk', '500g', 699.00, 'chalk.png', NULL, 11, 'active', 0, 'default', 0.00),
(4, 'Dumbbell', '5 kg', 500.00, 'dumbbell.png', 'This ia a dumbbell', 10, 'active', 0, 'flat', 45.00),
(5, 'Belt', '1 kg', 500.00, 'belt-20260420211951-e6fda6ce-20260420212129-bc283423.jpg', 'This is a weight lifting belt', 10, 'active', 0, 'default', 0.00),
(6, 'Protein Powder', '1 kg', 199.00, 'protein-powder-20260425011900-1e6406ec.jpg', 'This is whey protein powder', 10, 'active', 0, 'default', 0.00),
(8, 'Belt', '250 g', 120.00, 'belt-20260420211951-e6fda6ce-20260420212129-bc283423.jpg', 'This is a weight lifting belt', 10, 'active', 0, 'free', 0.00),
(9, 'Kettle Bells', '20 kg', 699.00, 'kettlebells-20260425011923-db0b0920.jpg', 'These are Kettle bells', 10, 'active', 0, 'default', 0.00),
(10, 'Kettle Bells', '250g', 199.00, 'kettlebells-20260425011923-db0b0920.jpg', 'These are Kettle bells', 10, 'active', 0, 'default', 0.00),
(11, 'Protein Powder', '2 kg', 299.00, 'protein-powder-20260425011900-1e6406ec.jpg', 'This is protein powder', 10, 'active', 0, 'default', 0.00),
(13, 'Barbell', '20kg', 1099.00, 'barbell-20260425011841-31fbdd55.png', 'This is a barbell.', 10, 'active', 0, 'default', 0.00),
(14, 'Barbell', '50 kg', 5399.00, 'barbell-20260425011841-31fbdd55.png', 'This is a barbell.', 10, 'active', 0, 'default', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `created_at`) VALUES
(8, 'testuser', 'test@gmail.com', '$2y$10$PXOn3VFGL1zkFqN7k20xWuRpwl1D1fBzIfDqrbLWqC5XVXfu8P3LS', '2026-03-09 00:28:23'),
(10, 'testuser1', 'test1@gmail.com', '$2y$10$ayHA.9bWjoTwS1dMMKWVuu1QZEdkEjtIpY09pmasTZIieKi93Wy8S', '2026-03-09 00:29:38'),
(11, 'avbc', 'avbc@gmail.com', '$2y$10$FZuHUHASRkwd5KTNx7FTS.Ul9uhv7dmTcja3QtAvFcWP7bOjTD3wW', '2026-03-09 00:44:42'),
(12, 'abvc', 'abvc@gmail.com', '$2y$10$aK1/JYAo4sAg9rfWjaavZ.iRtjZuTwUiDGK4NYUuBEPIuVbNWtQXi', '2026-03-09 00:54:12'),
(13, 'saniya', 'sk@gmail.com', '$2y$10$/2Tjpd0GjzsD1kQz0Dg.9O47.upCPcnnakROa.VKaY6.iaHvyJvGS', '2026-03-09 03:09:51'),
(14, 'sharada121', 'sharada@nn.com', '$2y$10$Mycp8PHRgeOiVY8aAaQGZO2ZEgnf5htzge24FPa.hIconsWI7drXu', '2026-03-10 06:41:18'),
(15, 'abcde', '05khanfatima@gmail.com', '$2y$10$TZkkWSnMTdCA16BkK8rOG.LG4A8S3XTlFxXMLhQVi6MpnXYIxHGr.', '2026-04-14 20:41:01'),
(16, 'nitin', 'nitin@bmncollege.com', '$2y$10$qLFlZXwq0dlHU.QHAj7A4O8NZGPUGuPBlrGiOxjtt7P7XNmTLBT4q', '2026-04-16 11:36:07'),
(17, 'user1', 'user1@gmail.com', '$2y$10$OlG66d5rtn9mNxLnOeInlOoFDvSyYmfiM6WwBJsqWunClUmHsi6U6', '2026-04-19 04:49:43'),
(27, 'Fatimaa', 'fatimakhan10a22@gmail.com', '$2y$10$bGaxH.z7VtUpOtX1oLF9v.ptZA4FD6p21xnCaMpJh0VaR73jwRRxe', '2026-04-21 13:37:07'),
(28, 'Fatima', 'fatima@gmail.com', '$2y$10$4hrxB7yus0Y8XrYunx0gFePNAobsuqqUJsQqiTEYDA87JRmUoEmHO', '2026-04-24 22:45:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orders_user_id` (`user_id`),
  ADD KEY `idx_orders_username` (`account_username`),
  ADD KEY `idx_orders_status` (`status`),
  ADD KEY `idx_orders_date` (`order_date`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order_id` (`order_id`),
  ADD KEY `idx_order_items_product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_products_name_size` (`name`,`size`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
