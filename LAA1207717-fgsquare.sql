-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- ホスト: mysql320.phy.lolipop.lan
-- 生成日時: 2025 年 5 月 01 日 05:50
-- サーバのバージョン： 8.0.35
-- PHP のバージョン: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- データベース: `LAA1207717-fgsquare`
--

-- --------------------------------------------------------

--
-- テーブルの構造 `line_room_links`
--

CREATE TABLE `line_room_links` (
  `id` int NOT NULL,
  `line_user_id` varchar(255) NOT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  `access_token` varchar(64) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `orders`
--

CREATE TABLE `orders` (
  `id` int NOT NULL,
  `square_order_id` varchar(255) DEFAULT NULL COMMENT 'Square注文ID',
  `room_number` varchar(20) NOT NULL,
  `guest_name` varchar(255) DEFAULT NULL,
  `order_status` enum('OPEN','COMPLETED','CANCELED') NOT NULL DEFAULT 'OPEN',
  `total_amount` decimal(10,2) DEFAULT '0.00' COMMENT '税抜合計金額',
  `note` text COMMENT 'ゲストからの備考',
  `order_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `checkout_datetime` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `order_details`
--

CREATE TABLE `order_details` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `square_item_id` varchar(255) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL COMMENT '注文時点の商品名',
  `unit_price` decimal(10,2) DEFAULT NULL COMMENT '注文時点の単価',
  `quantity` int NOT NULL DEFAULT '1',
  `subtotal` decimal(10,2) DEFAULT NULL COMMENT '単価 * 数量',
  `note` text COMMENT '商品ごとの備考',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `products`
--

CREATE TABLE `products` (
  `id` int NOT NULL,
  `square_item_id` varchar(255) NOT NULL COMMENT 'Squareの商品ID',
  `name` varchar(255) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL COMMENT '税抜価格 - 円単位',
  `image_url` varchar(1024) DEFAULT NULL COMMENT 'Square画像ID or キャッシュURL',
  `stock_quantity` int DEFAULT '0' COMMENT 'Squareから同期した在庫数',
  `local_stock_quantity` int DEFAULT '0' COMMENT '未使用 or 予約在庫等に利用可',
  `category` varchar(255) DEFAULT NULL COMMENT 'SquareカテゴリID or カテゴリ名',
  `is_active` tinyint(1) DEFAULT '1' COMMENT '表示フラグ',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `room_tokens`
--

CREATE TABLE `room_tokens` (
  `id` int NOT NULL,
  `room_number` varchar(20) NOT NULL,
  `access_token` varchar(64) NOT NULL COMMENT '認証用トークン',
  `is_active` tinyint(1) DEFAULT '1' COMMENT 'トークン有効フラグ',
  `guest_name` varchar(255) DEFAULT NULL,
  `check_in_date` date DEFAULT NULL,
  `check_out_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int NOT NULL,
  `log_level` enum('DEBUG','INFO','WARNING','ERROR') NOT NULL,
  `log_source` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `context` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- テーブルのデータのダンプ `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('app_name', 'LacisMobileOrder', '2025-04-25 06:21:45'),
('app_version', '1.0.0', '2025-04-25 06:21:45'),
('maintenance_mode', 'false', '2025-04-25 06:21:45'),
('order_notification_enabled', 'true', '2025-04-25 06:21:45');

--
-- ダンプしたテーブルのインデックス
--

--
-- テーブルのインデックス `line_room_links`
--
ALTER TABLE `line_room_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `line_user_id` (`line_user_id`),
  ADD KEY `line_user_id_2` (`line_user_id`),
  ADD KEY `room_number` (`room_number`),
  ADD KEY `access_token` (`access_token`);

--
-- テーブルのインデックス `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `square_order_id` (`square_order_id`),
  ADD KEY `square_order_id_2` (`square_order_id`),
  ADD KEY `room_number` (`room_number`),
  ADD KEY `order_status` (`order_status`);

--
-- テーブルのインデックス `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `square_item_id` (`square_item_id`);

--
-- テーブルのインデックス `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `square_item_id` (`square_item_id`),
  ADD KEY `square_item_id_2` (`square_item_id`),
  ADD KEY `category` (`category`),
  ADD KEY `is_active` (`is_active`);

--
-- テーブルのインデックス `room_tokens`
--
ALTER TABLE `room_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_number` (`room_number`),
  ADD UNIQUE KEY `access_token` (`access_token`),
  ADD KEY `access_token_2` (`access_token`),
  ADD KEY `room_number_2` (`room_number`),
  ADD KEY `is_active` (`is_active`);

--
-- テーブルのインデックス `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `log_level` (`log_level`),
  ADD KEY `log_source` (`log_source`),
  ADD KEY `created_at` (`created_at`);

--
-- テーブルのインデックス `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- ダンプしたテーブルの AUTO_INCREMENT
--

--
-- テーブルの AUTO_INCREMENT `line_room_links`
--
ALTER TABLE `line_room_links`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `order_details`
--
ALTER TABLE `order_details`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `room_tokens`
--
ALTER TABLE `room_tokens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- ダンプしたテーブルの制約
--

--
-- テーブルの制約 `order_details`
--
ALTER TABLE `order_details`
  ADD CONSTRAINT `order_details_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
