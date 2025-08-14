-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- ホスト: mysql320.phy.lolipop.lan
-- 生成日時: 2025 年 5 月 27 日 21:43
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
  `order_session_id` char(21) DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `check_in_date` date DEFAULT NULL,
  `check_out_date` date DEFAULT NULL,
  `access_token` varchar(64) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- テーブルのデータのダンプ `line_room_links`
--

INSERT INTO `line_room_links` (`id`, `line_user_id`, `room_number`, `order_session_id`, `user_name`, `check_in_date`, `check_out_date`, `access_token`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 'U94063df81ada7ad734bc6f999aa58d2a', 'fg#13', NULL, '南雲', '2025-05-20', '2025-05-06', NULL, 1, '2025-05-05 08:39:44', '2025-05-20 08:18:22'),
(4, 'U33f5f9aad71e81a5917ddc7cd83bb8f8', 'fg#12', NULL, '添嶋紘史Hirofumi Biz', '2025-05-05', '2025-05-06', NULL, 0, '2025-05-05 12:41:31', '2025-05-12 23:44:53'),
(9, 'U9e1f5c66b4a26fd0c33c890ad826b192', 'fg#09', '250516191839989404542', '倉田柚有', '2025-05-16', '2025-05-07', NULL, 1, '2025-05-06 06:45:40', '2025-05-26 09:47:53'),
(10, 'Ueff5c52f3e0077e6bf695d59414142ec', 'fg#11', '250526185526255666559', 'washo', '2025-05-13', '2025-05-08', NULL, 0, '2025-05-06 07:27:00', '2025-05-26 09:55:26'),
(30, 'U657b240fc65436d986c4d2dbf9bb9fa1', 'fg#01', '250516193833098604749', 'ひろき', '2025-05-16', '2025-05-09', NULL, 0, '2025-05-08 03:31:12', '2025-05-25 01:59:32'),
(35, 'U4d777614158c719515a7be91a41bc382', 'fg#12', '250516193833098604749', 'くらひで', '2025-05-27', '2025-05-09', NULL, 1, '2025-05-08 12:36:39', '2025-05-26 22:15:34');

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
  ADD KEY `access_token` (`access_token`),
  ADD KEY `order_session_id` (`order_session_id`);

--
-- ダンプしたテーブルの AUTO_INCREMENT
--

--
-- テーブルの AUTO_INCREMENT `line_room_links`
--
ALTER TABLE `line_room_links`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=194;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
