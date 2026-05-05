-- phpMyAdmin SQL Dump
-- version 5.1.3
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2026 年 05 月 05 日 15:22
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
-- 資料表結構 `voucher`
--

CREATE TABLE `voucher` (
  `voucher_id` int(11) NOT NULL,
  `redeem_type` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0:點數 1:印花',
  `type` varchar(8) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'TICKET' COMMENT 'TICKET/DELIVERY',
  `sku_id` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '商品串接編號(對應6編碼)',
  `store_sku_id` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `prices` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL,
  `image_url` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `remarks` text COLLATE utf8_unicode_ci NOT NULL,
  `create_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'active:1 inactive:0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- 傾印資料表的資料 `voucher`
--

INSERT INTO `voucher` (`voucher_id`, `redeem_type`, `type`, `sku_id`, `store_sku_id`, `name`, `prices`, `quantity`, `image_url`, `remarks`, `create_at`, `is_active`) VALUES
(1, 0, 'TICKET', 'PROD-001', NULL, '星巴克咖啡兌換券', 150, 50, 'http://greattree-store.mohist-internal.net/uploads/logo_69aea15b32532.png', '', '2026-03-09 10:30:51', 0),
(2, 0, 'TICKET', 'PROD-001', NULL, '星巴克咖啡兌換券', 150, 50, 'http://greattree-store.mohist-internal.net/uploads/logo_69aea1f635835.png', '', '2026-03-09 10:33:26', 0),
(3, 0, 'TICKET', 'PROD-001', NULL, '星巴克咖啡兌換券', 150, 50, 'http://greattree-store.mohist-internal.net/uploads/logo_69af738cee0ee.png', '', '2026-03-10 01:27:40', 0),
(4, 1, 'DELIVERY', 'PROD-002', NULL, '星巴克咖啡兌換券', 150, 100, 'http://greattree-store.mohist-internal.net/uploads/69ba1625ebe2e.png', '\n<p><strong>券商手續費優惠</strong></p>\n<p><strong>Money101</strong>盤點目前市場上熱門券商手續費，其中又以<span style=\"background-color: rgb(255,255,255)\"><strong>新光證券手續費最低、下殺到1折，犇亞證券也有最優1.68折的手續費優惠。</strong></span>若是盤中零股交易，部分券商有提供最低手續費1元的優惠，如富邦證券、國泰證券、新光證券及口袋證券等，建議大家開戶的時候，可以多方比較券商手續費會比較有利喔！</p>\n<p><strong>券商手續費比一比</strong></p>\n<p><strong>券商</strong></p>\n<p><strong>電子單</strong><br><strong>手續費</strong></p>\n<p><strong>零股最低手續費</strong></p>\n<p><strong>更多優惠</strong></p>\n<p><br>IBKR 盈透證券<br> </p>\n<p>0.5%0.005 美元達指定門檻，美元現金餘額支付最高達<strong> 3.14%</strong> 類活存利息</p>\n<p>富邦<br>AI PRO帳戶<br> </p>\n<p>1.8折1元新開戶享台股1.8折手續費優惠，定期定額台股手續費1元起，美股無低收</p>\n<p>新光證券<br>富貴角多元下單<br> </p>\n<p>0元1元開戶享0元手續費優惠 + $3,000 抵用金定期定額投資 100 元起，年年再送 500 元手續費抵用金股利再投資新上線，輕鬆累積財富<br>犇亞證券<br>ETJ易通金<br><br><br> 1.68折1元????【新戶起跑 · 交易零負擔】<br>現在開立犇亞證券帳戶<br>連續 6 個月，每月享 168萬交易手續費折抵<br>????共計享有 2,412元！<br>????活動即日起至2026/06/30<br>立刻開戶，把握零負擔交易機會！製表：<strong>Money101</strong></p>\n<p><br> </p>\n', '2026-03-10 01:28:19', 1),
(5, 0, 'TICKET', 'STAMP-999', NULL, '高級鑄鐵鍋', 3000, 0, 'http://greattree-store.mohist-internal.net/uploads/logo_69af7b4d2d59b.png', '', '2026-03-10 02:00:45', 1),
(6, 0, 'DELIVERY', 'PROD-001', NULL, '星巴克咖啡兌換券', 150, 51, 'http://greattree-store.mohist-internal.net/uploads/69b0dc12a70e0.png', '\n<p>test</p>\n<p>test</p>\n', '2026-03-11 03:05:54', 1),
(7, 1, 'DELIVERY', 'PROD-001', NULL, '星巴克咖啡兌換券', 150, 51, 'http://greattree-store.mohist-internal.net/uploads/69ba0ddeb51ae.png', 'test', '2026-03-18 02:28:46', 0),
(8, 0, 'DELIVERY', '1', NULL, '111', 1, 1, 'http://greattree-store.mohist-internal.net/uploads/69ba1701c28c5.png', '1', '2026-03-18 03:07:45', 0),
(9, 0, 'TICKET', '111', NULL, '測試商品', 899, 2, 'http://greattree-store.mohist-internal.net/uploads/69c237b2408d8.png', '\n<p><i><u>thett</u></i></p>\n<p><span style=\"color: hsl(120,75%,60%)\">1234</span></p>\n<p><span style=\"background-color: hsl(90,75%,60%)\">567</span></p>\n<p> </p>\n<p><span style=\"background-color: hsl(90,75%,60%)\">&lt;script&gt;alert(\'test\')&lt;/script&gt;</span></p>\n', '2026-03-24 07:05:22', 1),
(10, 1, 'TICKET', 'PROD-004', NULL, '住宿券', 2000, 32, 'http://greattree-store.mohist-internal.net/uploads/69cb6d7eae70e.png', 'test', '2026-03-31 06:45:18', 1),
(11, 1, 'DELIVERY', 'PROD-005', NULL, '餐券', 100, 3, 'http://greattree-store.mohist-internal.net/uploads/69cb6ea46ead8.png', '\n<p>折價券使用規範</p>\n<p><span style=\"background-color: rgb(255,255,255); color: rgb(51,51,51)\">使用優惠券至櫃台結帳還需支付 $ 79 元</span><br><span style=\"background-color: rgb(255,255,255); color: rgb(51,51,51)\">適用範圍</span><br><span style=\"background-color: rgb(255,255,255); color: rgb(51,51,51)\">使用期限 (期間 )</span><br><span style=\"background-color: rgb(255,255,255); color: rgb(51,51,51)\">商品以實物為準，圖片僅供參考</span><br><span style=\"background-color: rgb(255,255,255); color: rgb(51,51,51)\">&gt; 本公司保留隨時修改、變更或終止活動之權利</span><br><br><span style=\"background-color: rgb(255,255,255); color: rgb(51,51,51)\">大樹藥局 | 後台管理系統</span></p>\n', '2026-03-31 06:50:12', 1),
(12, 0, 'DELIVERY', 'EVA-001', NULL, 'Eva 測試商品', 500, 4, 'http://greattree-store.mohist-internal.net/uploads/69d8a2c933ab0.png', '\n<p><span class=\"text-tiny\" style=\"color: hsl(0,0%,30%)\"><strong>Eva</strong></span><span class=\"text-tiny\"><strong> 測試商品</strong></span></p>\n<p><span class=\"text-small\" style=\"color: hsl(0,0%,60%)\"><i>Eva</i></span><span class=\"text-small\"><i> </i></span><span class=\"text-small\" style=\"color: hsl(60,75%,60%)\"><i>測試商品</i></span></p>\n<p><span style=\"color: hsl(159,71%,32%)\"><u>Eva 測試商品</u></span></p>\n<p><span class=\"text-big\" style=\"color: hsl(30,75%,60%)\"><s>Eva 測試商品</s></span></p>\n<p><span class=\"text-huge\" style=\"font-family: \'Times New Roman\', Times, serif\">Eva 測試商品</span></p>\n', '2026-04-10 07:12:09', 1);

--
-- 已傾印資料表的索引
--

--
-- 資料表索引 `voucher`
--
ALTER TABLE `voucher`
  ADD PRIMARY KEY (`voucher_id`);

--
-- 在傾印的資料表使用自動遞增(AUTO_INCREMENT)
--

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `voucher`
--
ALTER TABLE `voucher`
  MODIFY `voucher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
