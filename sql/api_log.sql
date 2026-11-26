-- phpMyAdmin SQL Dump
-- version 5.1.3
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2026 年 11 月 25 日 15:10
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
-- 資料表結構 `api_log`
--

CREATE TABLE `api_log` (
  `id` int(11) NOT NULL,
  `http_method` varchar(6) COLLATE utf8_unicode_ci DEFAULT NULL,
  `request_id` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `api_path` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ip` varbinary(16) NOT NULL,
  `user_agent_id` int(11) NOT NULL,
  `header` text COLLATE utf8_unicode_ci NOT NULL,
  `request` text COLLATE utf8_unicode_ci NOT NULL,
  `response` text COLLATE utf8_unicode_ci NOT NULL,
  `create_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `runtime_ms` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- 已傾印資料表的索引
--

--
-- 資料表索引 `api_log`
--
ALTER TABLE `api_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `create_at` (`create_at`);

--
-- 在傾印的資料表使用自動遞增(AUTO_INCREMENT)
--

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `api_log`
--
ALTER TABLE `api_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
