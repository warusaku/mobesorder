-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- ホスト: mysql320.phy.lolipop.lan
-- 生成日時: 2025 年 5 月 03 日 03:09
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

--
-- テーブルのデータのダンプ `products`
--

INSERT INTO `products` (`id`, `square_item_id`, `name`, `description`, `price`, `image_url`, `stock_quantity`, `local_stock_quantity`, `category`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'IV7TSDH5FQ7V6TBJXS32OLK6', 'オロポセット', '', 10.00, '', 0, 0, '', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(2, 'WMBACTO6TGYHK6L4C2ONZWOQ', 'ご宿泊代金', '', 0.00, '', 0, 0, 'N3YGF2VEZOF5YQTKLSXOHIO4', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(3, 'OXBWUQSUMKIIS4MZO4S54M2X', '薪', '', 16.50, 'NL4ZQJ2DCSA7C542IVJQTFOQ', 0, 0, '', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(4, '74LPEPWRH6NLWRBNSHMDBXIE', 'パープルショット', '', 11.00, 'XDTA7H6DY4U6LOU5CGZCZ6K7', 0, 0, 'HVTWRF424ELTWH6Y22CV5C66', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(5, '5ZWYWPRB5ZV5JDQWYFK262NB', 'イエローツイスト(ノンアル)', '', 8.80, 'XH3GMT52KU3JJIQ2PJYIVVBD', 0, 0, 'ZVN57PX6X2U4R7UV7ATAH5Y5', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(6, 'SGC55HS2PL4FIK4HPVLZHXRE', 'イエローツイスト', '', 11.00, 'TIPSVBNTFVXH3CF63HASESAQ', 0, 0, 'HVTWRF424ELTWH6Y22CV5C66', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(7, 'CPZQ5WPYLABORW7ALF6GVYYF', 'パープルショット(ノンアル)', '', 8.80, 'RIN27KYAFHYVC2HETEIWTOYK', 0, 0, 'ZVN57PX6X2U4R7UV7ATAH5Y5', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(8, '7SR2LEU5PM5XRYI5PTAPJV6E', 'ハーバルグリーン(ノンアル)', '', 8.80, '3QXVMVZNUOOKQIXNREPJLJ6O', 0, 0, 'ZVN57PX6X2U4R7UV7ATAH5Y5', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(9, 'MY3ZCX6BO5N3ED7XE3ULZFI2', 'BBQセット', '', 55.00, '', 0, 0, '', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(10, 'QLA4E4VYKAYAF552MQ2LHMRH', 'ハーバルグリーン', '', 11.00, '33FGI3B3KPZN2Q24N6PU6ZZS', 0, 0, 'HVTWRF424ELTWH6Y22CV5C66', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(11, 'PSY622HTJHBODTWYWNZD524K', 'サニーレッド(ノンアル)', '', 8.80, 'VYKYRCQE7OUPWR2DYZXOZUKV', 0, 0, 'ZVN57PX6X2U4R7UV7ATAH5Y5', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(12, '3OERTMTCZPKHD4OECEVOOZVU', 'サニーレッド', '', 11.00, 'GVTWH4E7VSGIJTRD2I4ERN2B', 0, 0, 'HVTWRF424ELTWH6Y22CV5C66', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(13, 'LCMRLOXP5UECUZSMTHQMVNMJ', 'アサヒスーパードライ', '', 5.50, 'L37LN4QGHBWXPMKQRNOI3IAJ', 0, 0, 'COZQK7FI2FLKJNGQB5R2QBGC', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(14, 'CPXSDRJO3ZHACO3HZ2NTBYS3', 'ピルスナーウルケル', '', 6.60, 'ZKP3BLZV5DY3DES2E2GGDHU3', 0, 0, 'COZQK7FI2FLKJNGQB5R2QBGC', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(15, 'BBXK7EFABEYPVFATECE4ZC2C', 'ヒューガルデンホワイト', '', 6.60, 'D3F5J6IH2QN5IXXELZOISL67', 0, 0, 'COZQK7FI2FLKJNGQB5R2QBGC', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(16, 'WJ32XMMLS75SQ6WCGC24QOVU', 'スペアリブカレー', '', 23.10, 'LSPZK5EMADXNBJAJ2ZACTQOH', 0, 0, 'SO7HLNRC5EVBXAKPXWZFMDAP', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(17, 'C6OLAB4S2XFRN57GUZQIY4L4', '包み焼きピザ', '', 8.80, '5ACWHGPZL547RB2Y5Z2KQQK4', 0, 0, 'SO7HLNRC5EVBXAKPXWZFMDAP', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(18, 'IBRPBBBQSKE5CB4EAVDVXQXP', 'ケイジャンチキン', '', 8.80, '3FGEHLHSQ66MHU5YLFX5V6SJ', 0, 0, 'SO7HLNRC5EVBXAKPXWZFMDAP', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(19, '4GZETKV6TAF4KD73VVRO66UP', 'ひとくちフライドポテト', '', 5.50, 'OEYQYK66OXCHBXMYWOB6KNRR', 0, 0, 'SO7HLNRC5EVBXAKPXWZFMDAP', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(20, 'J2STJBK6UJE2AG5GQNFQWD7L', 'ナチョス風ポテト', '', 8.80, 'H63NWOI4ZC3YRTVUFWYHUE5Z', 0, 0, 'SO7HLNRC5EVBXAKPXWZFMDAP', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(21, 'OFI5UEOSBPHWHXFYE22FD67J', 'ガーリックシュリンプ', '', 13.20, '5R3XC2HTA45O4FWBXBXQRYLQ', 0, 0, 'SO7HLNRC5EVBXAKPXWZFMDAP', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(22, 'VNTQORWKT2LY4QLFLAV67I3O', 'パテドカンパーニュ', '', 13.20, 'HY3LNWBK72IXNUPY4D6WFVWN', 0, 0, 'SO7HLNRC5EVBXAKPXWZFMDAP', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(23, 'H65Z4MOLJEQASLIJOBDEFJOF', '生ハムサラミの盛り合わせ', '', 16.50, 'O5P7ZPQCBIN27LKTPDVXE3UE', 0, 0, 'SO7HLNRC5EVBXAKPXWZFMDAP', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(24, 'HEWYCIMK2YYAQOZDQQLKZKKO', 'チーズの盛り合わせ', '', 16.50, 'LQ5JBDMUT2RDXDJYNKPEF6CK', 0, 0, 'SO7HLNRC5EVBXAKPXWZFMDAP', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(25, 'WJHHMHEXMUYQFJXSA2RBOTAU', 'さくらチップの燻製ナッツ', '', 5.50, '3D5R2CES64L4CNGJAZ6TD4NH', 0, 0, 'SO7HLNRC5EVBXAKPXWZFMDAP', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(26, 'MSKHZRQYXJSEVBRSGXMRUK2J', 'キャロットラペ', '', 16.50, '5AAYYUYMRL3JVN5EBEVSV4FP', 0, 0, 'SO7HLNRC5EVBXAKPXWZFMDAP', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(27, 'SSOV22DVOSZOHMWNL5FQ2SSX', '濃厚ガトーショコラ', '', 9.90, '3BWJ3CZCGQHAIWEZF43QZUT4', 0, 0, 'SO7HLNRC5EVBXAKPXWZFMDAP', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(28, 'LI7WZ2YTCVFJIT7I4CET5GAY', 'ジンソーダ', '', 6.60, 'GOKKXJQWNU6J5Y3UXU7AWN4C', 0, 0, 'RP2QI6OF2PWND5S5SQJYEUXN', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(29, 'JQ5NSPSKFMHNBF37ZEXHG7AG', '房総ハイボール', '', 6.60, 'VSW62NP5S332VB2ZYBMK7OEA', 0, 0, 'RP2QI6OF2PWND5S5SQJYEUXN', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(30, 'HBQ6MH6USXES5DNCYKGSUT6G', '山崎ハイボール', '', 16.50, 'YORI5TV5QVVJESYVT7RQRLOZ', 0, 0, 'RP2QI6OF2PWND5S5SQJYEUXN', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(31, 'IK6SLQRMQFLDH3IOD7MFG3IC', 'ザ　マッカラン　ハイボール', '', 16.50, 'Y3M2ZMN47J45F5NLAIE7UEW7', 0, 0, 'RP2QI6OF2PWND5S5SQJYEUXN', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(32, 'BY5OOUI2KJHMA6B3MPLVBFXY', 'ウッドフォード', '', 16.50, 'Y3M2ZMN47J45F5NLAIE7UEW7', 0, 0, 'RP2QI6OF2PWND5S5SQJYEUXN', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(33, 'RIHQBOEYCVKENUDP6YYZX3BY', '房総ボトル', '', 55.00, 'SWWYZAI7T2SLTO6PULSXNUTA', 0, 0, 'RP2QI6OF2PWND5S5SQJYEUXN', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(34, 'IT6IVD2WXUNMXB2ROX5NKEMD', 'レモンサワー', '', 5.50, '', 0, 0, 'RP2QI6OF2PWND5S5SQJYEUXN', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(35, 'JMDC6CYQJ3TUWUE64GEPC3L2', 'クラフトチェリーコーク', '', 8.80, '', 0, 0, 'ZVN57PX6X2U4R7UV7ATAH5Y5', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(36, 'ER327EC5HOKU45WX2SBQ6VEF', 'クラフトコーラ', '', 8.80, '', 0, 0, 'ZVN57PX6X2U4R7UV7ATAH5Y5', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(37, 'INTJYVXJ2665355GD4M5ZEWF', 'クラフトジンジャー', '', 8.80, '', 0, 0, 'ZVN57PX6X2U4R7UV7ATAH5Y5', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(38, 'XDLUYUDGBGREO6JOIX6B5IS5', 'クラフトチェリーラムコーク', '', 7.70, '', 0, 0, 'HVTWRF424ELTWH6Y22CV5C66', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(39, 'IAQSK35E5HCVNQLAB76CXJ7Y', 'クラフトコークハイ', '', 7.70, '', 0, 0, 'HVTWRF424ELTWH6Y22CV5C66', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(40, 'REC2YTX463IJTZD6TQRQS6J6', 'クラフトジンジャーハイ', '', 7.70, '', 0, 0, 'HVTWRF424ELTWH6Y22CV5C66', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(41, 'P6KT6CKJOAEV5KXYTQUIVHBD', 'Hennessy XO（single）', '', 18.70, 'QYCXGPEV2UFBDNKJBGIQ2HYX', 0, 0, 'RP2QI6OF2PWND5S5SQJYEUXN', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(42, '3D4XZMVASRJTBBSLY7EYIQRP', 'クエルボ　テキーラ', '', 11.00, '', 0, 0, 'RP2QI6OF2PWND5S5SQJYEUXN', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(43, 'OCGXA37EYPJIQBSHPJWGMF6N', '魔王　グラス', '', 8.80, '3JQO7LQWE7WZULCH3YPUJKDY', 0, 0, 'RP2QI6OF2PWND5S5SQJYEUXN', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(44, 'THWPZAXCTO637K3B4F3IHJ75', '百年の孤独　グラス', '', 8.80, 'NYTQA24BOTN3UDHMYUDKIOQM', 0, 0, 'RP2QI6OF2PWND5S5SQJYEUXN', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(45, 'NWYPSBVJJTVSL2Z2PF2R3Q5A', '魔王　ボトル', '', 132.00, '3JQO7LQWE7WZULCH3YPUJKDY', 0, 0, 'RP2QI6OF2PWND5S5SQJYEUXN', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(46, '65CHLOGYPZGFWICB2VXBPNKW', '百年の孤独　ボトル', '宮崎県の大人気蔵元である黒木本店が製造するプレミアム麦焼酎。\n焼酎では珍しく、オーク樽で長期間ねかせたことによる力強く、かつ複雑な味わいが特徴です。', 198.00, 'NYTQA24BOTN3UDHMYUDKIOQM', 0, 0, 'RP2QI6OF2PWND5S5SQJYEUXN', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(47, 'ZYNM4BNXY2VKKIZIDCT7P3BX', '本日の酒', '', 11.00, '', 0, 0, 'RP2QI6OF2PWND5S5SQJYEUXN', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(48, 'KSKEECSHLRKDKGHYR5WAIOME', 'アメリカーノ（HOT)', '', 6.60, 'BMUAIV3HN7ZZYWQIWKUOZJNC', 0, 0, 'BUODEPKQPSQYAXSGLUA4IQOR', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(49, '7UMTL5HE7UFV5RZOFN4LTDKE', 'カフェラテ（HOT）', '', 7.15, 'XWDBGHTAFPVFLLLSOY2LKHSG', 0, 0, 'BUODEPKQPSQYAXSGLUA4IQOR', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(50, 'F53I7O5HVGCDDW73GL7SBX3J', 'キャラメルラテ', '', 7.70, '', 0, 0, 'BUODEPKQPSQYAXSGLUA4IQOR', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(51, 'MZR3U46FPVCZ2AGBHANDKLS3', 'ウーロン茶', '', 5.50, '', 0, 0, 'FCRARY7T2CCLL6DQJD4PVJUC', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(52, 'QZO4BSHEKLOQN237QUFDAJOB', '緑茶', '', 5.50, '', 0, 0, 'FCRARY7T2CCLL6DQJD4PVJUC', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(53, 'YMXVW5XAKWUD5CLHSLZ7GFRR', 'オレンジジュース', '', 5.50, 'EIZVMFHBONODOF2MBP5FNNW6', 0, 0, 'FCRARY7T2CCLL6DQJD4PVJUC', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(54, 'VWOSR3F3JUP6DPQFHJ4SEFUR', 'アップルジュース', '', 5.50, 'WMKZ4FXY7SB2NGWUMTUJPGMH', 0, 0, 'FCRARY7T2CCLL6DQJD4PVJUC', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(55, '2PV3YJP6YOA73LWE4ZAJOIPW', 'ジャスミン茶', '', 5.50, '', 0, 0, 'FCRARY7T2CCLL6DQJD4PVJUC', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(56, 'JQH7LTAW4RRCIBIC35LFSLDU', 'グレープジュース', '', 5.50, '', 0, 0, 'FCRARY7T2CCLL6DQJD4PVJUC', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(57, 'AWUHNH5ITUA7N3TQ64PDXOQH', 'カルピス', '', 5.50, '', 0, 0, 'FCRARY7T2CCLL6DQJD4PVJUC', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(58, 'HH2ZF77A5EUT4AXCCUD4ICNB', '炭酸水', '', 2.20, '', 0, 0, 'FCRARY7T2CCLL6DQJD4PVJUC', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(59, 'EVJ46MK4W2JOYQWN22ZEYKCK', '水', '', 2.00, '', 0, 0, 'FCRARY7T2CCLL6DQJD4PVJUC', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(60, 'ZERSW52C7FPWW3HSBMOHQG25', 'デキャンタ　赤', '', 13.20, '', 0, 0, 'BVAULPYNGQR2BRWIBITPRGRI', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(61, 'N3KPBVGY2K4NZBZXDO5KKAMH', 'デキャンタ白', '', 13.20, '', 0, 0, 'T52WHG5IOZSR6IJV7OWKWPDF', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(62, 'XFR2AAEYQ6M75SK4NFKTD4UB', '奥房総ジビエバーガー', '', 27.50, 'E7ZKOFJCUSCXQTTFPJAKUDAR', 0, 0, 'ZJOIVLZ2X4H4ZJ6DB34XEJFC', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(63, 'HXT5FXJHR3544VO37RXUFWPY', '野菜BBQバーガー', '', 19.80, 'M27TTUR4WG6CP6TXL54B23FU', 0, 0, 'ZJOIVLZ2X4H4ZJ6DB34XEJFC', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(64, 'MMCUCAUEGE57TRA2WQ47APHY', 'BBQバーガー', '', 16.50, '', 0, 0, 'ZJOIVLZ2X4H4ZJ6DB34XEJFC', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(65, 'DO2E5DZZQ7BU2JXE7L34IB3X', 'まるごと燻製バーガー', '', 19.80, '', 0, 0, 'ZJOIVLZ2X4H4ZJ6DB34XEJFC', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(66, '4KOECWTNJODWCKKB3NJCWPYZ', 'ホットバタードラムカウ', '寒い季節にぴったりの一杯、ホットバタードラムカウ。\n ラムの芳醇な香りに、バターのまろやかさとミルクのやさしさが溶け合った、心まで温まる大人のホットカクテルです。\n 甘くスパイシーな風味が、ほっとひと息つきたい夜におすすめ。', 11.00, 'NEBA7CGKS7ZKEMID6P5NGNJQ', 0, 0, 'M4D6MVSWFRDAVIISWQ4BDJUO', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(67, 'YKRT77BB4ASSP2BCHRHX2PNO', 'ホット赤ワイン', '', 11.00, 'VETPGJFZYUQMDIMUT56EHTQT', 0, 0, 'M4D6MVSWFRDAVIISWQ4BDJUO', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(68, 'EMUMKB2AYP23OHJONXMN47ZZ', 'ホット白ワイン', '', 11.00, '666GNZOC5KIKT377XHSIEGPX', 0, 0, 'M4D6MVSWFRDAVIISWQ4BDJUO', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(69, '4DF4ZJ342FAA32NX47LBJLYV', '体験コンテンツ', '', 0.00, '', 0, 0, '', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(70, 'H5PMKOKELZ3NCXBGGYPC3NN3', 'welcome セット', '', 6.60, '', 0, 0, '', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(71, 'YLXZ2FGMZ2XK5FJPIB7UCIE7', '191オーパスワン', 'ブラックプラム、ブルーベリー、黒スグリ、乾燥したバラの花びらのアロマが高く、かすかなミネラルのニュアンスが感じられます。\nきめ細やかなタンニンはクリーミーでサテンのような質感をもたらします。\n優しく広がる酸味とフレッシュさが、ダークフルーツ、サボリーハーブ、エスプレッソ、カカオなどの風味を引き立てています。\n余韻に長引く、繊細なダークチョコレートの心地よい苦味が印象的です。', 1210.00, 'XUVHMHWUJ6PLALP5QIVS7KCH', 0, 0, 'BVAULPYNGQR2BRWIBITPRGRI', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(72, 'Q4QCYOPRIAJLXQB7UQDE6ZYJ', '192 OPUS ONE OVERTURE', 'プラム、ブラックベリー、チェリーの爽やかなアロマとともに、乾いたバラの花びら、森の下草、土を感じさせるミネラル感が感じられます。口に入れると、黒スグリ、ブルーベリー、野イチゴの味わいが充満。果実味と繊細なタンニンが織り交ざり、ベルベットのような滑らかな舌触りをもらたします。微かなコーヒーとダークチョコレートのニュアンスが、融け込んだ酸味と相まって、心地良く長い余韻を導く魅惑的な仕上がりです。', 880.00, '7TLCWGD2MVFYRW6K7LDX5EPO', 0, 0, 'BVAULPYNGQR2BRWIBITPRGRI', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(73, 'KRQEMUWUN3OWQKB4FLNBVE23', '104 Villa Noria GRAND PRESTIGE PINOT NOIR', '濃厚なルビー色。\nスパイシーさとフルーティーの香りが複雑に絡み、レッドチェリー、ブラックチェリー、キルシュのといった様々な香りが感じられます。\nほのかに赤い実のフルーツ、バニラ、チョコレートの味わいがします。\nバランスの良く取れたワインでシルキーなタンニンが感じられ、後口も長く続きます。', 77.00, 'NBYSSELFOKRBYE7PLBSCOHMY', 0, 0, 'BVAULPYNGQR2BRWIBITPRGRI', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(74, 'TEKTDMZQCQSAM3XXG3NXN3KJ', '102 Villa Noria GRAND PRESTIGE CABERNET SAUVIGNON', '深紅のルビー色。\nヴィラノリア・カベルネ・ソーヴィニヨンはスパイシーでフルーティなノートと複雑な香り。\n赤い果実、バニラとチョコレートが大胆に口の中で開く味わい。\nエレガントで、ソフトなタンニンと果実味の持続性がバランスよくまとまっている。', 55.00, 'X2XLVMAPZYXW4W25HEMWWV6I', 0, 0, 'BVAULPYNGQR2BRWIBITPRGRI', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(75, 'ETSEU27VSKV7KXZ74T4P3DAM', '103 Nebbiolo d’Alba Superiore “San Ponzio”', 'サン ポンツィオ ネッビオーロ ダルバ DOC スペリオーレ\n「サン・ポンツィオ」の丘は、ロエロ県モンティチェッロ・ダルバの町にあります。土壌は典型的な砂質石灰岩ですが、構造が崩れることはありません。このワインは、グラスの中で急速に、しかし複雑に変化していくのが特徴です。\n組み合わせ：ラグー、白身肉、豚肉、ソフトチーズを使った前菜', 71.50, '6LLQKO4K5DO6LFNBBMTKQ2O4', 0, 0, 'BVAULPYNGQR2BRWIBITPRGRI', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(76, 'QTB4DHMMYLTBCYJUEYEHNRUP', '105 Chateauneuf du Pape La Cour des Papes', '脂分が豊富な和牛ロース肉ステーキ、フォアグラを乗せたフィレ肉など豪華で贅沢な料理、ジビエなどとの相性は抜群です。また、すきやきやしゃぶしゃぶなどの和牛を使った日本料理にも合わせやすい赤ワインです。', 77.00, '27VG6WR6N5DZR26U7UDTDKHL', 0, 0, 'BVAULPYNGQR2BRWIBITPRGRI', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(77, 'R5UKCS2MSCRQG5TZHOIXYGEY', '106 CHATEAU LA CROIXPOMEROL', '', 93.50, '2BIZOSR2FVNR3P5L2MXET6LF', 0, 0, 'BVAULPYNGQR2BRWIBITPRGRI', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(78, 'AKX56WRW6IA7OY2DM25SWSEH', '101 BARINAS ORGANIC', '美しいルビー色、プラムやブルーベリーの濃縮したアロマとたる樽熟成によるヴァニラの香りが心地よく香りが上品に漂う。しっかりと腰のある味わいでありながら、タンニンと酸味のバランスが均衡し、果実の濃厚な味わいが力強く残るワインです。', 49.50, 'KH23ZH4SC3HHV5R2UOBAOEBA', 0, 0, 'BVAULPYNGQR2BRWIBITPRGRI', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(79, 'UKQ5JACH2MJ37Q22Z72JILLD', '292 Morley-Saint-Denis ler Cru Clos des Monts Luisants Blanc V.V', 'ブルゴーニュ最高峰のドメーヌ「ポンソ」が造る特級畑コルトン・シャルルマーニュ！\n\n色調は明るいイエロー。バターや焼きリンゴ、シナモンや蜂蜜等、複雑味に富んだアロマが華やかに広がります。\n\n上品な酸と果実味のバランスに優れ、これぞポンソと呼べる味わい。魚介類や鶏肉のクリーム煮、ヤギのチーズと相性抜群です。', 605.00, 'IVNJJXLU47CY5ZE4JEFMZWEQ', 0, 0, 'T52WHG5IOZSR6IJV7OWKWPDF', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(80, 'HBSJLQWOTG6IBV5YU7NRIOH5', '291 DOMAIN CHEVALIER CORTON CHARLEMAGNE', '', 550.00, '', 0, 0, 'T52WHG5IOZSR6IJV7OWKWPDF', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(81, 'EXRXWNBJYGXGQ32JV2R75NJ6', '202 Villa Noria GRAND PRESTIGE SAUVIGNON BLANC', '', 60.50, 'V626MCQ5DN3HWXR37MW3GX4H', 0, 0, 'T52WHG5IOZSR6IJV7OWKWPDF', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(82, 'SJKBZZUSKDSNVM4O5MFZ7CWO', '203 Barrel Fermented CHARDONNAY', '', 66.00, '', 0, 0, 'T52WHG5IOZSR6IJV7OWKWPDF', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(83, 'KF6SD3DJRXT46LJBAPMEMRB7', '204 Chablis “Chatillons”', '', 71.50, '', 0, 0, 'T52WHG5IOZSR6IJV7OWKWPDF', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(84, '7NHG5ONJ56IMYQXRRFF6KGPF', '206 Santa Barbara County Viognier', '', 77.00, '', 0, 0, 'T52WHG5IOZSR6IJV7OWKWPDF', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(85, '2BZUETPBWESVVDVP63CSFMDF', '201 FALLEGRO Langhe Favorita', '', 55.00, '', 0, 0, 'T52WHG5IOZSR6IJV7OWKWPDF', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(86, 'V3AGTKOTJWD3OSIDWCTTRM4Q', '205 Saint-Joseph “Meribets”', '', 71.50, '', 0, 0, 'T52WHG5IOZSR6IJV7OWKWPDF', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(87, 'N5M7ITPZ52K4YKVUBNLNI7ZM', '207 Petit Celler Chardonnay', '', 38.50, '', 0, 0, 'T52WHG5IOZSR6IJV7OWKWPDF', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(88, 'WCNXOQCWDUTTMQRDMVBFNO5N', '492 LOUIS ROEDERER CRISTAL', '', 935.00, '', 0, 0, 'RNVQ6ILXOEM6RUGPXWEA576G', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(89, 'HMFJLPA6TLBOGB6CONGVHMQU', '491 POMMERY Cuvée Louise', '', 495.00, '', 0, 0, 'RNVQ6ILXOEM6RUGPXWEA576G', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(90, '4YB22WTJDTHMG4I5LR47QHW3', '401 VEUVE CLICQUOT YELLOW LABEL BRUT', '', 110.00, '', 0, 0, 'SZRODVIBCRPMMCINSGH2IU35', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(91, 'UK5NKXBXAYNXXJXYZDPWVRGV', '301 La Vie en Rose', '', 60.50, '', 0, 0, 'PBPGYI7ELYRZWMZ3LQCKY7Q6', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(92, 'EGO34KW7DVASMW2DJD4GCMOL', '山崎ロック', '', 15.00, '', 0, 0, 'PBPGYI7ELYRZWMZ3LQCKY7Q6', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(93, 'NOJXPI3BOLL4V7W4GNFPKNTJ', '山崎ロック', '', 16.50, '', 0, 0, 'RP2QI6OF2PWND5S5SQJYEUXN', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(94, 'QUIWMOXGSYR23DB6FQX3UIV5', 'パープルショット(ノンアル)', '', 4.40, '', 0, 0, '', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(95, 'KONRPROCU7J5ZVOSV5NFDE3C', '(ノンアル)パープルショット', '', 4.40, '', 0, 0, 'MRZ2GMJMQL5HQQCILIN6KR5A', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(96, 'ML7MGOZQFCCSIG2RX5FWYC6B', '(ノンアル)ハーバルグリーン', '', 4.40, '', 0, 0, 'MRZ2GMJMQL5HQQCILIN6KR5A', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(97, '2QNLX3VR4ZMIEMUZKCMJHXLO', '(ノンアル)イエローツイスト', '', 4.40, '', 0, 0, 'MRZ2GMJMQL5HQQCILIN6KR5A', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(98, 'EJGVM3FMXMBCNDT7VLHMMKXB', '(ノンアル)サニーレッド', '', 4.40, '', 0, 0, 'MRZ2GMJMQL5HQQCILIN6KR5A', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(99, '7N3Z32NELUOODV5M4ENNJ2OD', 'クラフトコーラ', '', 4.40, '', 0, 0, 'MRZ2GMJMQL5HQQCILIN6KR5A', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37'),
(100, '2FAOIJTSN7ROBHU3IR6IINSR', 'クラフトジンジャーエール', '', 4.40, '', 0, 0, 'MRZ2GMJMQL5HQQCILIN6KR5A', 1, '2025-05-01 21:17:01', '2025-05-02 04:48:37');

-- --------------------------------------------------------

--
-- テーブルの構造 `room_tickets`
--

CREATE TABLE `room_tickets` (
  `id` int NOT NULL,
  `room_number` varchar(20) NOT NULL,
  `square_order_id` varchar(255) NOT NULL,
  `status` enum('OPEN','COMPLETED','CANCELED') NOT NULL DEFAULT 'OPEN',
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
-- テーブルの構造 `sync_status`
--

CREATE TABLE `sync_status` (
  `id` int NOT NULL,
  `provider` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `table_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `last_sync_time` datetime NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `details` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `sync_status`
--

INSERT INTO `sync_status` (`id`, `provider`, `table_name`, `last_sync_time`, `status`, `details`, `created_at`, `updated_at`) VALUES
(1, 'square', 'products', '2025-05-02 13:48:37', 'success', '{\"added\":0,\"updated\":100,\"errors\":0}', '2025-05-01 21:17:01', '2025-05-02 04:48:37');

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

-- --------------------------------------------------------

--
-- テーブルの構造 `test_write`
--

CREATE TABLE `test_write` (
  `id` int DEFAULT NULL,
  `data` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `test_write`
--

INSERT INTO `test_write` (`id`, `data`) VALUES
(1, 'テストデータ');

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
-- テーブルのインデックス `room_tickets`
--
ALTER TABLE `room_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_number` (`room_number`),
  ADD KEY `square_order_id` (`square_order_id`);

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
-- テーブルのインデックス `sync_status`
--
ALTER TABLE `sync_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `provider_table` (`provider`,`table_name`);

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- テーブルの AUTO_INCREMENT `room_tickets`
--
ALTER TABLE `room_tickets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `room_tokens`
--
ALTER TABLE `room_tokens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `sync_status`
--
ALTER TABLE `sync_status`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
