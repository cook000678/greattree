-- phpMyAdmin SQL Dump
-- version 5.1.3
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2026 年 05 月 25 日 14:14
-- 伺服器版本： 10.2.38-MariaDB
-- PHP 版本： 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 資料庫: `greattree_member`
--

-- --------------------------------------------------------

--
-- 資料表結構 `order_item_vouchers`
--

CREATE TABLE `order_item_vouchers` (
  `item_id` int(11) NOT NULL,
  `voucher_code` varchar(20) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- 傾印資料表的資料 `order_item_vouchers`
--

INSERT INTO `order_item_vouchers` (`item_id`, `voucher_code`) VALUES
(1, '5ljodt4g6hc054moynao'),
(2, 'srbq8v0i8zmh2lnjgkca'),
(3, 'f1cb7qrtnyjrqwv53jnf'),
(12, '4xsdptu33evqzcr8b38s'),
(13, 'lbl5z18k30srpb6abagy'),
(14, 'cqmv1ma373byhuatiuta'),
(15, '5azbhdmfbh0fd7qakm18'),
(16, 'my7t01e0p06a03cnijup'),
(18, '88k5rtcmdn1lhs1lmupj'),
(19, 'qf7rxswk5l9cm7iv1r5l'),
(20, 'n04r04kgfwrlk5ojx4yg'),
(21, '3lb6w8xag6d9j7vehgvr'),
(32, 'xdf1k1n04ihoagsbtotn'),
(33, '8qs1hihhqyjxg7t8mzwy'),
(36, 'wfdy89gc4zf0n98pzk3o'),
(37, 'rc4mku95ag9bwd19n8qx'),
(38, 'dvhft0bp19qhatvt3i9f'),
(39, 'ii41d54yoxrbk8uzfs87'),
(40, 'hsl1ame8anpbasn2c1ko'),
(41, 'cw83en2iyrxuku6c5x3k'),
(42, 'ev0enkor4uhz6jnrugh4'),
(43, 'awwjvmeod3l3g94ttdec'),
(44, 'yio9fy1ulf1eo58ck7ye'),
(45, 'mglyemg6g4mqe7nmvp4n'),
(46, '9adnfpvq1iso337vx8mf'),
(47, 'lk3hps5v1v32h8fi3z0h'),
(48, '3f5o6u4nv57429einflf'),
(49, 'bqsdu35yxmb8lp5ah5at'),
(50, 'bat59vkv0u9zyj2di6sa'),
(51, 'j6mqfe5xqobfkfbutya6'),
(52, 'xr6fheavz19ceozavu4l'),
(54, 'xlr69et41g70l5z9fmci'),
(56, 'jjmpa9ubyil13z5xkapi'),
(57, 'ov9yxuf06wnbya50vk56'),
(59, 'sl4wpuo85di03yaix25j'),
(61, 'cw1emjz0l25j0krksgub'),
(63, 'krj1wbild23s471sv6we'),
(64, '35o6ll1hwlzthc4msrsn'),
(67, 'jxn9eq7vikezntt7grfq'),
(69, 'fywco1g1rd4wwdvntbvc'),
(70, 'ekwyod47hi95wuvm69na');

--
-- 已傾印資料表的索引
--

--
-- 資料表索引 `order_item_vouchers`
--
ALTER TABLE `order_item_vouchers`
  ADD PRIMARY KEY (`item_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
