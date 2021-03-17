-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- Host: scaman.jupiter.dreamhost.com
-- Generation Time: Mar 17, 2021 at 05:39 AM
-- Server version: 5.7.28-log
-- PHP Version: 7.1.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sesame_dashboard`
--
CREATE DATABASE IF NOT EXISTS `sesame_dashboard` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `sesame_dashboard`;

-- --------------------------------------------------------

--
-- Table structure for table `binaries`
--

CREATE TABLE `binaries` (
  `id` int(11) NOT NULL,
  `type` int(11) NOT NULL,
  `target` varchar(255) NOT NULL,
  `data` longblob NOT NULL,
  `headers` longblob NOT NULL,
  `version` varchar(255) NOT NULL,
  `notes` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `invitations`
--

CREATE TABLE `invitations` (
  `invite_code` varchar(255) NOT NULL,
  `created_by` varchar(255) NOT NULL,
  `used_by` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `invitations`
--



-- --------------------------------------------------------

--
-- Table structure for table `loader`
--

CREATE TABLE `loader` (
  `ip` varchar(255) NOT NULL,
  `access_time` bigint(20) NOT NULL,
  `cooldown_time` bigint(20) NOT NULL,
  `access_code` varchar(255) NOT NULL,
  `hwid` varchar(255) NOT NULL,
  `region` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `loader`
--



-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `discord_id` varchar(255) DEFAULT NULL,
  `discord_username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `invitee_id` varchar(255) NOT NULL,
  `registration_date` bigint(20) NOT NULL,
  `ip` varchar(255) DEFAULT NULL,
  `last_login` bigint(20) DEFAULT NULL,
  `permissions` int(11) DEFAULT '0',
  `hwid` varchar(255) DEFAULT NULL,
  `subscription_time` bigint(20) NOT NULL,
  `invites_left` int(11) NOT NULL,
  `ban_reason` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--



--
-- Indexes for dumped tables
--

--
-- Indexes for table `binaries`
--
ALTER TABLE `binaries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `binaries`
--
ALTER TABLE `binaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
