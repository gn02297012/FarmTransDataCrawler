-- phpMyAdmin SQL Dump
-- version 4.2.7
-- http://www.phpmyadmin.net
--
-- 主機: localhost
-- 產生時間： 2014-09-12: 16:35:06
-- 伺服器版本: 5.6.16
-- PHP 版本： 5.5.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- 資料庫： `farmtransdata`
--

-- --------------------------------------------------------

--
-- 資料表結構 `raw`
--

CREATE TABLE IF NOT EXISTS `raw` (
`id` int(11) NOT NULL,
  `date` varchar(12) NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(30) NOT NULL,
  `marketCode` varchar(10) NOT NULL,
  `market` varchar(20) NOT NULL,
  `priceTop` double NOT NULL,
  `priceMid` double NOT NULL,
  `priceBottom` double NOT NULL,
  `price` double NOT NULL,
  `quantity` int(11) NOT NULL,
  `date_int` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2509689 ;

--
-- 已匯出資料表的索引
--

--
-- 資料表索引 `raw`
--
ALTER TABLE `raw`
 ADD PRIMARY KEY (`id`), ADD KEY `name` (`name`), ADD KEY `name_2` (`name`), ADD KEY `market` (`market`), ADD KEY `date_int` (`date_int`);

--
-- 在匯出的資料表使用 AUTO_INCREMENT
--

--
-- 使用資料表 AUTO_INCREMENT `raw`
--
ALTER TABLE `raw`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2509689;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
