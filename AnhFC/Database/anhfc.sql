-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th3 29, 2025 lúc 05:47 AM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `anhfc`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `banners`
--

CREATE TABLE `banners` (
  `id` int(11) NOT NULL,
  `background` varchar(255) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `expiry_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `banners`
--

INSERT INTO `banners` (`id`, `background`, `title`, `description`, `expiry_date`, `created_at`) VALUES
(1, '1743179640_Banner1.jpg', 'Ăn thả ga, Quà cực đã', 'Tặng thêm 1 bánh trứng hoặc 1 Khoai tây múi cau cho đơn hàng trên 200K', '2025-04-03 23:59:00', '2025-03-28 16:19:47'),
(2, '1743179651_Banner2.jpg', 'Trưa nay ăn gì?', 'Đồng giá 42K cho các món ăn từ thứ 2 đến thứ 6 (10 giờ đến 22 giờ)', NULL, '2025-03-28 16:21:28');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `categories`
--

INSERT INTO `categories` (`id`, `name`, `created_at`) VALUES
(1, 'Combo 1 người', '2025-03-29 03:32:54'),
(2, 'Combo 2 người', '2025-03-29 03:32:54'),
(3, 'Khuyến mãi', '2025-03-29 03:32:54'),
(4, 'Đồ uống', '2025-03-29 03:32:54');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `menu`
--

CREATE TABLE `menu` (
  `id` int(11) NOT NULL,
  `combo_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rating` decimal(3,2) DEFAULT 0.00,
  `rating_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `menu`
--

INSERT INTO `menu` (`id`, `combo_name`, `description`, `price`, `image`, `created_at`, `updated_at`, `rating`, `rating_count`) VALUES
(1, 'Combo gà quay', '1 Đùi Gà Quay Flava + 1 Salad Hạt + 1 Lipton (lớn)', 117000.00, 'Combo_ga_quay.jpg', '2025-03-23 06:17:21', '2025-03-29 03:33:07', 0.00, 0),
(2, 'Combo Burger Tôm', '1 Burger Tôm + 1 Khoai Tây Chiên (vừa) + 1 Pepsi (lớn)', 67000.00, 'Combo_burger_tom.jpg', '2025-03-23 06:26:15', '2025-03-29 04:19:05', 4.00, 1),
(3, 'Combo Mì ý gà viên', '1 Mì Ý Popcorn + 1 Pepsi (lớn)', 47000.00, 'Combo_mi_y_ga_vien.jpg', '2025-03-27 03:08:34', '2025-03-29 03:33:07', 0.00, 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `menu_categories`
--

CREATE TABLE `menu_categories` (
  `menu_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `menu_categories`
--

INSERT INTO `menu_categories` (`menu_id`, `category_id`) VALUES
(1, 1),
(2, 1),
(3, 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `shipping_address` text NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'Pending',
  `final_amount` double DEFAULT 0,
  `receiver_name` varchar(255) NOT NULL,
  `receiver_phone` varchar(20) NOT NULL,
  `note` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `total_amount`, `shipping_address`, `payment_method`, `order_date`, `status`, `final_amount`, `receiver_name`, `receiver_phone`, `note`, `completed_at`) VALUES
(1, 2, 114000.00, '123 đường ABC', 'COD', '2025-03-27 04:42:08', 'Completed', 114000, 'Người nhận mặc định', '0123456789', NULL, '2025-03-28 19:49:51'),
(2, 2, 670000.00, '123 đường ABC', 'COD', '2025-03-28 18:04:27', 'Completed', 570000, 'Người nhận mặc định', '0123456789', 'Giao hàng lần 1 thất bại', '2025-03-28 20:25:44'),
(3, 2, 114000.00, '123 đường ABC', 'PayPal', '2025-03-29 02:50:05', 'Cancelled', 91200, 'TuanAnh', '3054895255', 'Người dùng chủ động hủy: Tôi muốn áp dụng thêm voucher', NULL),
(4, 2, 114000.00, '123 đường ABC', 'COD', '2025-03-29 03:18:30', 'Cancelled', 84200, 'TuanAnh', '3054895255', 'Chúng tôi đã hết hàng. Vui lòng đặt lại vào buổi chiều', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `item_id`, `quantity`, `price`) VALUES
(1, 1, 2, 1, 67000.00),
(2, 1, 3, 1, 47000.00),
(3, 2, 2, 10, 67000.00),
(4, 3, 2, 1, 67000.00),
(5, 3, 3, 1, 47000.00),
(6, 4, 2, 1, 67000.00),
(7, 4, 3, 1, 47000.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_vouchers`
--

CREATE TABLE `order_vouchers` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `voucher_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `order_vouchers`
--

INSERT INTO `order_vouchers` (`id`, `order_id`, `voucher_id`) VALUES
(1, 2, 1),
(2, 3, 1),
(3, 4, 1),
(4, 4, 3);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `ratings`
--

CREATE TABLE `ratings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `ratings`
--

INSERT INTO `ratings` (`id`, `user_id`, `menu_id`, `rating`, `description`, `created_at`) VALUES
(1, 2, 2, 4, 'Món ăn này khá hợp gu tôi', '2025-03-29 04:16:05');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_admin` tinyint(1) DEFAULT 0,
  `restrict_cod` tinyint(1) DEFAULT 0,
  `restrict_order` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `phone`, `address`, `password`, `created_at`, `is_admin`, `restrict_cod`, `restrict_order`) VALUES
(1, 'testuser', 'test@gmail.com', '0123456789', '123 đường ABC', '$2y$10$eQ/YykXkoWwJ/yUY/N5pB./AV6AUL/5rD4lcrHa7QIH2WBn7/OlU.', '2025-03-26 20:28:39', 1, 0, 0),
(2, 'testuser1', 'test1@gmail.com', '0123456789', '123 đường ABC', '$2y$10$d0d5se672.ER6NSLwFKQ3efnqkVkraPHSz/FjfvkV90/gmmTdkbPS', '2025-03-26 20:30:46', 0, 0, 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `discount_percent` decimal(5,2) DEFAULT NULL,
  `max_discount_value` decimal(10,2) DEFAULT NULL,
  `fixed_discount` decimal(10,2) DEFAULT NULL,
  `min_order_value` decimal(10,2) DEFAULT NULL,
  `expiry_date` datetime DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_public` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `vouchers`
--

INSERT INTO `vouchers` (`id`, `code`, `discount_percent`, `max_discount_value`, `fixed_discount`, `min_order_value`, `expiry_date`, `quantity`, `created_at`, `is_public`) VALUES
(1, 'SUMMER2025', 20.00, 100000.00, NULL, NULL, NULL, 3999, '2025-03-28 17:42:31', 1),
(2, 'KHACHHANGTHANTHIETANHFC', NULL, NULL, 10000.00, 99000.00, NULL, NULL, '2025-03-28 19:33:25', 0),
(3, 'HAPPYSATURDAY', NULL, NULL, 7000.00, 77000.00, '2025-03-30 00:00:00', NULL, '2025-03-28 19:34:51', 1);

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Chỉ mục cho bảng `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `menu_categories`
--
ALTER TABLE `menu_categories`
  ADD PRIMARY KEY (`menu_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Chỉ mục cho bảng `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Chỉ mục cho bảng `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Chỉ mục cho bảng `order_vouchers`
--
ALTER TABLE `order_vouchers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `voucher_id` (`voucher_id`);

--
-- Chỉ mục cho bảng `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_menu` (`user_id`,`menu_id`),
  ADD KEY `menu_id` (`menu_id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Chỉ mục cho bảng `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `menu`
--
ALTER TABLE `menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `order_vouchers`
--
ALTER TABLE `order_vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `ratings`
--
ALTER TABLE `ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `menu_categories`
--
ALTER TABLE `menu_categories`
  ADD CONSTRAINT `menu_categories_ibfk_1` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `menu_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Các ràng buộc cho bảng `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Các ràng buộc cho bảng `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `menu` (`id`);

--
-- Các ràng buộc cho bảng `order_vouchers`
--
ALTER TABLE `order_vouchers`
  ADD CONSTRAINT `order_vouchers_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_vouchers_ibfk_2` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `ratings`
--
ALTER TABLE `ratings`
  ADD CONSTRAINT `ratings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ratings_ibfk_2` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
