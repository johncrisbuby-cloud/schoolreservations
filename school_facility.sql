-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 15, 2026 at 08:18 AM
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
-- Database: `school_facility`
--

-- --------------------------------------------------------

--
-- Table structure for table `borrowings`
--

CREATE TABLE `borrowings` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `equipment_name` varchar(100) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `borrow_date` date NOT NULL,
  `return_date` date NOT NULL,
  `department` varchar(255) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `status` enum('Pending','Borrowed','Returned','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrowings`
--

INSERT INTO `borrowings` (`id`, `equipment_id`, `equipment_name`, `student_name`, `student_id`, `borrow_date`, `return_date`, `department`, `purpose`, `status`, `created_at`, `user_id`) VALUES
(64, 1, 'Dell Latitude Laptop', 'cherry', '0707', '2026-11-11', '2026-11-11', NULL, NULL, 'Borrowed', '2026-11-11 14:20:46', 18);

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL,
  `equipment_code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `total_quantity` int(11) NOT NULL DEFAULT 1,
  `quantity_available` int(11) NOT NULL DEFAULT 1,
  `condition` varchar(50) DEFAULT 'Good',
  `status` varchar(50) DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `equipment_code`, `name`, `category`, `total_quantity`, `quantity_available`, `condition`, `status`) VALUES
(1, '4', 'Dell Latitude Laptop', 'Computer', 10, 6, 'Good', 'Available'),
(2, '3', 'Epson LCD Projector', 'AVR Equipment', 5, 4, 'Good', 'Available'),
(3, '2', 'Wireless UHF Microphone', 'Audio', 6, 5, 'Good', 'Available'),
(7, '1', 'HDMI Cable 10m', 'Electronics', 8, 7, 'Good', 'Available'),
(8, '5', 'Logitech HD Webcam', 'Computer', 5, 5, 'Good', 'Available');

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `id` int(11) NOT NULL,
  `facility_code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `building` varchar(150) NOT NULL,
  `floor` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT 'Main Campus',
  `capacity` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `status` enum('Available','Unavailable','Under Maintenance') DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`id`, `facility_code`, `name`, `building`, `floor`, `location`, `capacity`, `description`, `status`, `created_at`) VALUES
(1, '', 'Audio Visual Room (AVR)', '', NULL, 'Building A - 3rd Floor', 150, 'Equipped with projector, sound system, and air conditioning for presentations and events.', 'Available', '2026-09-25 14:13:31'),
(2, '', 'Computer Laboratory 1', '', NULL, 'IT Building - 2nd Floor', 40, 'Includes 40 high-spec PCs with internet connection for programming and laboratory classes.', 'Available', '2026-09-25 14:13:31'),
(3, '', 'Main Gymnasium', '', NULL, 'Sports Complex', 500, 'Multi-purpose indoor sports court for basketball, volleyball, and institutional gatherings.', 'Available', '2026-09-25 14:13:31'),
(4, '', 'Conference Room', '', NULL, 'Admin Building - 1st Floor', 20, 'Air-conditioned room suitable for student council meetings and small group discussions.', 'Available', '2026-09-25 14:13:31'),
(5, '', 'Covered Court', '', NULL, 'Grounds', 300, 'Open-air covered court suitable for sports practices and outdoor events.', 'Available', '2026-09-25 14:13:31'),
(6, '', 'Ground', '', NULL, 'Outside', 50, 'For PE', 'Available', '2026-10-26 00:37:00');

-- --------------------------------------------------------

--
-- Table structure for table `facility_bookings`
--

CREATE TABLE `facility_bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `facility_id` int(11) NOT NULL,
  `facility_name` varchar(255) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `student_id` varchar(100) NOT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `purpose` text NOT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facility_reservations`
--

CREATE TABLE `facility_reservations` (
  `id` int(11) NOT NULL,
  `facility` varchar(255) NOT NULL,
  `attendance` int(11) DEFAULT NULL,
  `reservation_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `department` varchar(255) DEFAULT NULL,
  `event_type` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(10, 10, 'Equipment Request Approved! 🎉', 'Inaprubahan na ng Admin ang iyong hiling na hiramin ang equipment.', 1, '2026-10-09 16:00:51'),
(30, 10, 'Equipment Request Approved! 🎉', 'Inaprubahan na ng Admin ang iyong request para sa equipment na: Dell Latitude Laptop.', 1, '2026-10-14 14:34:54'),
(31, 10, 'Facility Reservation Approved! 🎉', 'Inaprubahan na ng Admin ang iyong reservation request para sa facility na: Computer Laboratory 1.', 1, '2026-10-14 14:35:00'),
(32, 10, 'Equipment Request Approved! 🎉', 'Inaprubahan na ng Admin ang iyong request para sa equipment na: Epson LCD Projector.', 1, '2026-10-14 14:35:17'),
(33, 10, 'Facility Reservation Approved! 🎉', 'Inaprubahan na ng Admin ang iyong reservation request para sa facility na: Audio Visual Room (AVR).', 1, '2026-10-14 14:35:27'),
(34, 2, 'New Equipment Added 📦', 'Bagong kagamitan ang idinagdag sa inventory: Epson LCD Projector (Electronics).', 0, '2026-10-14 14:42:29'),
(35, 3, 'New Equipment Added 📦', 'Bagong kagamitan ang idinagdag sa inventory: Epson LCD Projector (Electronics).', 0, '2026-10-14 14:42:29'),
(36, 7, 'New Equipment Added 📦', 'Bagong kagamitan ang idinagdag sa inventory: Epson LCD Projector (Electronics).', 0, '2026-10-14 14:42:29'),
(37, 8, 'New Equipment Added 📦', 'Bagong kagamitan ang idinagdag sa inventory: Epson LCD Projector (Electronics).', 0, '2026-10-14 14:42:29'),
(39, 10, 'New Equipment Added 📦', 'Bagong kagamitan ang idinagdag sa inventory: Epson LCD Projector (Electronics).', 1, '2026-10-14 14:42:29'),
(40, 2, 'Equipment Removed ❌', 'Ang kagamitang \'Epson LCD Projector\' ay tinanggal na sa inventory.', 0, '2026-10-14 14:42:47'),
(41, 3, 'Equipment Removed ❌', 'Ang kagamitang \'Epson LCD Projector\' ay tinanggal na sa inventory.', 0, '2026-10-14 14:42:47'),
(42, 7, 'Equipment Removed ❌', 'Ang kagamitang \'Epson LCD Projector\' ay tinanggal na sa inventory.', 0, '2026-10-14 14:42:47'),
(43, 8, 'Equipment Removed ❌', 'Ang kagamitang \'Epson LCD Projector\' ay tinanggal na sa inventory.', 0, '2026-10-14 14:42:47'),
(45, 10, 'Equipment Removed ❌', 'Ang kagamitang \'Epson LCD Projector\' ay tinanggal na sa inventory.', 1, '2026-10-14 14:42:47'),
(46, 2, 'New Equipment Added 📦', 'Bagong kagamitan ang idinagdag sa inventory: HDMI Cable 10m (Electronics).', 0, '2026-10-14 14:42:59'),
(47, 3, 'New Equipment Added 📦', 'Bagong kagamitan ang idinagdag sa inventory: HDMI Cable 10m (Electronics).', 0, '2026-10-14 14:42:59'),
(48, 7, 'New Equipment Added 📦', 'Bagong kagamitan ang idinagdag sa inventory: HDMI Cable 10m (Electronics).', 0, '2026-10-14 14:42:59'),
(49, 8, 'New Equipment Added 📦', 'Bagong kagamitan ang idinagdag sa inventory: HDMI Cable 10m (Electronics).', 0, '2026-10-14 14:42:59'),
(51, 10, 'New Equipment Added 📦', 'Bagong kagamitan ang idinagdag sa inventory: HDMI Cable 10m (Electronics).', 1, '2026-10-14 14:42:59'),
(52, 2, 'Equipment Removed ❌', 'Ang kagamitang \'Equipment Item\' ay tinanggal na sa inventory.', 0, '2026-10-14 14:42:59'),
(53, 3, 'Equipment Removed ❌', 'Ang kagamitang \'Equipment Item\' ay tinanggal na sa inventory.', 0, '2026-10-14 14:42:59'),
(54, 7, 'Equipment Removed ❌', 'Ang kagamitang \'Equipment Item\' ay tinanggal na sa inventory.', 0, '2026-10-14 14:42:59'),
(55, 8, 'Equipment Removed ❌', 'Ang kagamitang \'Equipment Item\' ay tinanggal na sa inventory.', 0, '2026-10-14 14:42:59'),
(57, 10, 'Equipment Removed ❌', 'Ang kagamitang \'Equipment Item\' ay tinanggal na sa inventory.', 1, '2026-10-14 14:42:59'),
(58, 2, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-14 14:47:08'),
(59, 3, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-14 14:47:08'),
(60, 7, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-14 14:47:08'),
(61, 8, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-14 14:47:08'),
(63, 10, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 1, '2026-10-14 14:47:08'),
(82, 10, 'Equipment Request Approved! 🎉', 'Your borrowing request for equipment: Dell Latitude Laptop has been approved by the Admin.', 1, '2026-10-14 15:00:59'),
(83, 10, 'Facility Reservation Rejected ❌', 'Sorry, your reservation request for facility: Conference Room has been rejected by the Admin.', 1, '2026-10-14 15:01:01'),
(89, 10, 'Equipment Request Approved! 🎉', 'Your borrowing request for equipment: HDMI Cable 10m has been approved by the Admin.', 1, '2026-10-14 15:27:31'),
(95, 14, 'Equipment Request Approved! 🎉', 'Your borrowing request for equipment: Dell Latitude Laptop has been approved by the Admin.', 1, '2026-10-17 14:13:00'),
(108, 2, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-19 14:55:16'),
(109, 3, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-19 14:55:16'),
(110, 7, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-19 14:55:16'),
(111, 8, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-19 14:55:16'),
(113, 10, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-19 14:55:16'),
(114, 12, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-19 14:55:16'),
(115, 14, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 1, '2026-10-19 14:55:16'),
(117, 2, 'Equipment Updated 💻', 'Equipment details have been updated: Wireless UHF Microphone.', 0, '2026-10-19 14:55:22'),
(118, 3, 'Equipment Updated 💻', 'Equipment details have been updated: Wireless UHF Microphone.', 0, '2026-10-19 14:55:22'),
(119, 7, 'Equipment Updated 💻', 'Equipment details have been updated: Wireless UHF Microphone.', 0, '2026-10-19 14:55:22'),
(120, 8, 'Equipment Updated 💻', 'Equipment details have been updated: Wireless UHF Microphone.', 0, '2026-10-19 14:55:22'),
(122, 10, 'Equipment Updated 💻', 'Equipment details have been updated: Wireless UHF Microphone.', 0, '2026-10-19 14:55:22'),
(123, 12, 'Equipment Updated 💻', 'Equipment details have been updated: Wireless UHF Microphone.', 0, '2026-10-19 14:55:22'),
(124, 14, 'Equipment Updated 💻', 'Equipment details have been updated: Wireless UHF Microphone.', 1, '2026-10-19 14:55:22'),
(126, 2, 'Equipment Updated 💻', 'Equipment details have been updated: Epson LCD Projector.', 0, '2026-10-19 14:55:29'),
(127, 3, 'Equipment Updated 💻', 'Equipment details have been updated: Epson LCD Projector.', 0, '2026-10-19 14:55:29'),
(128, 7, 'Equipment Updated 💻', 'Equipment details have been updated: Epson LCD Projector.', 0, '2026-10-19 14:55:29'),
(129, 8, 'Equipment Updated 💻', 'Equipment details have been updated: Epson LCD Projector.', 0, '2026-10-19 14:55:29'),
(131, 10, 'Equipment Updated 💻', 'Equipment details have been updated: Epson LCD Projector.', 0, '2026-10-19 14:55:29'),
(132, 12, 'Equipment Updated 💻', 'Equipment details have been updated: Epson LCD Projector.', 0, '2026-10-19 14:55:29'),
(133, 14, 'Equipment Updated 💻', 'Equipment details have been updated: Epson LCD Projector.', 1, '2026-10-19 14:55:29'),
(135, 2, 'Equipment Updated 💻', 'Equipment details have been updated: Dell Latitude Laptop.', 0, '2026-10-19 14:55:35'),
(136, 3, 'Equipment Updated 💻', 'Equipment details have been updated: Dell Latitude Laptop.', 0, '2026-10-19 14:55:35'),
(137, 7, 'Equipment Updated 💻', 'Equipment details have been updated: Dell Latitude Laptop.', 0, '2026-10-19 14:55:35'),
(138, 8, 'Equipment Updated 💻', 'Equipment details have been updated: Dell Latitude Laptop.', 0, '2026-10-19 14:55:35'),
(140, 10, 'Equipment Updated 💻', 'Equipment details have been updated: Dell Latitude Laptop.', 0, '2026-10-19 14:55:35'),
(141, 12, 'Equipment Updated 💻', 'Equipment details have been updated: Dell Latitude Laptop.', 0, '2026-10-19 14:55:35'),
(142, 14, 'Equipment Updated 💻', 'Equipment details have been updated: Dell Latitude Laptop.', 1, '2026-10-19 14:55:35'),
(149, 15, 'Profile Settings Changed ⚙️', 'You successfully updated your account settings (Password).', 1, '2026-10-19 15:43:14'),
(150, 2, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-19 15:52:38'),
(151, 3, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-19 15:52:38'),
(152, 7, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-19 15:52:38'),
(153, 8, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-19 15:52:38'),
(155, 10, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-19 15:52:38'),
(156, 12, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-19 15:52:38'),
(157, 14, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 1, '2026-10-19 15:52:38'),
(158, 15, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 1, '2026-10-19 15:52:38'),
(159, 8, 'Account Registration Status ⚠️', 'We regret to inform you that your account registration request has been rejected by the Admin.', 0, '2026-10-19 15:54:24'),
(166, 15, 'Equipment Request Rejected ❌', 'Sorry, your borrowing request for equipment: Dell Latitude Laptop has been rejected by the Admin.', 1, '2026-10-19 16:13:32'),
(172, 15, 'Facility Reservation Rejected ❌', 'Sorry, your reservation request for facility: Computer Laboratory 1 has been rejected by the Admin.', 1, '2026-10-19 16:14:34'),
(175, 15, 'Equipment Returned 📦', 'Your return of equipment: Epson LCD Projector has been successfully recorded. Thank you!', 1, '2026-10-19 16:16:29'),
(185, 9, 'Equipment Request Rejected ❌', 'Sorry, your borrowing request for equipment: Dell Latitude Laptop has been rejected by the Admin.', 1, '2026-10-19 16:33:03'),
(186, 9, 'Equipment Request Rejected ❌', 'Sorry, your borrowing request for equipment: Dell Latitude Laptop has been rejected by the Admin.', 1, '2026-10-19 16:33:09'),
(187, 9, 'Equipment Request Rejected ❌', 'Sorry, your borrowing request for equipment: Epson LCD Projector has been rejected by the Admin.', 1, '2026-10-19 16:33:25'),
(188, 9, 'Equipment Request Rejected ❌', 'Sorry, your borrowing request for equipment: Dell Latitude Laptop has been rejected by the Admin.', 1, '2026-10-19 16:33:30'),
(189, 9, 'Equipment Request Rejected ❌', 'Sorry, your borrowing request for equipment: Dell Latitude Laptop has been rejected by the Admin.', 1, '2026-10-19 16:34:11'),
(190, 2, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-19 16:34:33'),
(191, 3, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-19 16:34:33'),
(192, 7, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-19 16:34:33'),
(193, 8, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-19 16:34:33'),
(194, 9, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 1, '2026-10-19 16:34:33'),
(195, 10, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-19 16:34:33'),
(196, 12, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 0, '2026-10-19 16:34:33'),
(197, 14, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 1, '2026-10-19 16:34:33'),
(198, 15, 'Equipment Updated 💻', 'Equipment details have been updated: HDMI Cable 10m.', 1, '2026-10-19 16:34:33'),
(199, 2, 'New Equipment Added 📦', 'New equipment added to inventory: Logitech HD Webcam (Computer).', 0, '2026-10-19 16:36:04'),
(200, 3, 'New Equipment Added 📦', 'New equipment added to inventory: Logitech HD Webcam (Computer).', 0, '2026-10-19 16:36:04'),
(201, 7, 'New Equipment Added 📦', 'New equipment added to inventory: Logitech HD Webcam (Computer).', 0, '2026-10-19 16:36:04'),
(202, 8, 'New Equipment Added 📦', 'New equipment added to inventory: Logitech HD Webcam (Computer).', 0, '2026-10-19 16:36:04'),
(203, 9, 'New Equipment Added 📦', 'New equipment added to inventory: Logitech HD Webcam (Computer).', 1, '2026-10-19 16:36:04'),
(204, 10, 'New Equipment Added 📦', 'New equipment added to inventory: Logitech HD Webcam (Computer).', 0, '2026-10-19 16:36:04'),
(205, 12, 'New Equipment Added 📦', 'New equipment added to inventory: Logitech HD Webcam (Computer).', 0, '2026-10-19 16:36:04'),
(206, 14, 'New Equipment Added 📦', 'New equipment added to inventory: Logitech HD Webcam (Computer).', 1, '2026-10-19 16:36:04'),
(207, 15, 'New Equipment Added 📦', 'New equipment added to inventory: Logitech HD Webcam (Computer).', 1, '2026-10-19 16:36:04'),
(209, 9, 'Equipment Request Approved! 🎉', 'Your borrowing request for equipment: HDMI Cable 10m has been approved by the Admin.', 1, '2026-10-19 16:37:06'),
(212, 9, 'Equipment Request Approved! 🎉', 'Your borrowing request for equipment: Dell Latitude Laptop has been approved by the Admin.', 1, '2026-10-21 05:33:28'),
(217, 16, 'Account Approved! 🎉', 'Hello Ervin! Your account registration has been approved by the Admin. You can now use the system.', 1, '2026-10-21 06:08:51'),
(219, 2, 'New Facility Added 🏫', 'A new facility is available: Ground (Outside).', 0, '2026-10-26 00:37:00'),
(220, 3, 'New Facility Added 🏫', 'A new facility is available: Ground (Outside).', 0, '2026-10-26 00:37:00'),
(221, 7, 'New Facility Added 🏫', 'A new facility is available: Ground (Outside).', 0, '2026-10-26 00:37:00'),
(222, 8, 'New Facility Added 🏫', 'A new facility is available: Ground (Outside).', 0, '2026-10-26 00:37:00'),
(223, 9, 'New Facility Added 🏫', 'A new facility is available: Ground (Outside).', 1, '2026-10-26 00:37:00'),
(224, 10, 'New Facility Added 🏫', 'A new facility is available: Ground (Outside).', 0, '2026-10-26 00:37:00'),
(225, 12, 'New Facility Added 🏫', 'A new facility is available: Ground (Outside).', 0, '2026-10-26 00:37:00'),
(226, 14, 'New Facility Added 🏫', 'A new facility is available: Ground (Outside).', 0, '2026-10-26 00:37:00'),
(227, 15, 'New Facility Added 🏫', 'A new facility is available: Ground (Outside).', 1, '2026-10-26 00:37:00'),
(228, 16, 'New Facility Added 🏫', 'A new facility is available: Ground (Outside).', 1, '2026-10-26 00:37:00'),
(230, 16, 'Facility Reservation Approved! 🎉', 'Your reservation request for facility: Audio Visual Room (AVR) has been approved by the Admin.', 1, '2026-10-26 14:24:01'),
(232, 15, 'Facility Reservation Approved! 🎉', 'Your reservation request for facility: Ground has been approved by the Admin.', 1, '2026-10-26 14:29:02'),
(244, 15, 'Facility Reservation Approved! 🎉', 'Your reservation request for facility: Ground has been approved by the Admin.', 1, '2026-10-29 07:58:32'),
(245, 15, 'Facility Reservation Completed 🏫', 'Your facility reservation for Ground has been marked as Completed.', 1, '2026-10-29 07:58:50'),
(249, 15, 'Equipment Request Approved! 🎉', 'Your borrowing request for equipment: HDMI Cable 10m has been approved by the Admin.', 1, '2026-10-29 08:02:27'),
(250, 15, 'Facility Reservation Approved! 🎉', 'Your reservation request for facility: Ground has been approved by the Admin.', 1, '2026-10-29 08:02:31'),
(259, 2, 'Facility Updated 🏛️', 'Facility details have been updated: Ground (Capacity: 50 pax).', 0, '2026-10-29 08:15:32'),
(260, 3, 'Facility Updated 🏛️', 'Facility details have been updated: Ground (Capacity: 50 pax).', 0, '2026-10-29 08:15:32'),
(261, 7, 'Facility Updated 🏛️', 'Facility details have been updated: Ground (Capacity: 50 pax).', 0, '2026-10-29 08:15:32'),
(262, 8, 'Facility Updated 🏛️', 'Facility details have been updated: Ground (Capacity: 50 pax).', 0, '2026-10-29 08:15:32'),
(263, 9, 'Facility Updated 🏛️', 'Facility details have been updated: Ground (Capacity: 50 pax).', 0, '2026-10-29 08:15:32'),
(264, 10, 'Facility Updated 🏛️', 'Facility details have been updated: Ground (Capacity: 50 pax).', 0, '2026-10-29 08:15:32'),
(265, 12, 'Facility Updated 🏛️', 'Facility details have been updated: Ground (Capacity: 50 pax).', 0, '2026-10-29 08:15:32'),
(266, 14, 'Facility Updated 🏛️', 'Facility details have been updated: Ground (Capacity: 50 pax).', 0, '2026-10-29 08:15:32'),
(267, 15, 'Facility Updated 🏛️', 'Facility details have been updated: Ground (Capacity: 50 pax).', 1, '2026-10-29 08:15:32'),
(268, 16, 'Facility Updated 🏛️', 'Facility details have been updated: Ground (Capacity: 50 pax).', 1, '2026-10-29 08:15:32'),
(272, 15, 'Facility Reservation Approved! 🎉', 'Your reservation request for facility: Ground has been approved by the Admin.', 1, '2026-10-29 08:19:24'),
(273, 15, 'Equipment Request Approved! 🎉', 'Your borrowing request for equipment: Logitech HD Webcam has been approved by the Admin.', 1, '2026-10-29 08:19:27'),
(274, 15, 'Facility Reservation Completed 🏫', 'Your facility reservation for Ground has been marked as Completed. Capacity restored.', 1, '2026-10-29 08:20:06'),
(281, 18, 'Account Approved! 🎉', 'Hello cherry! Your account registration has been approved by the Admin. You can now use the system.', 1, '2026-11-11 14:07:46'),
(284, 18, 'Equipment Request Approved! 🎉', 'Your borrowing request for equipment: Dell Latitude Laptop has been approved by the Admin.', 1, '2026-11-11 14:21:02'),
(286, 18, 'Profile Settings Changed ⚙️', 'You successfully updated your account settings (Profile Picture).', 0, '2026-11-11 15:33:35'),
(288, 15, 'Profile Settings Changed ⚙️', 'You successfully updated your account settings (Profile Picture).', 1, '2026-11-13 16:11:19'),
(290, 16, 'Profile Settings Changed ⚙️', 'You successfully updated your account settings (Profile Picture).', 1, '2026-11-13 16:15:53'),
(292, 16, 'Profile Settings Changed ⚙️', 'You successfully updated your account settings (Profile Picture).', 1, '2026-11-13 16:34:05'),
(293, 2, 'Account Registration Status ⚠️', 'We regret to inform you that your account registration request has been rejected by the Admin.', 0, '2026-11-14 14:46:28'),
(298, 12, 'Account Approved! 🎉', 'Hello johncrisbubayyyy! Your account registration has been approved by the Admin. You can now use the system.', 0, '2026-11-14 15:03:15'),
(301, 7, 'Account Registration Status ⚠️', 'We regret to inform you that your account registration request has been rejected by the Admin.', 0, '2026-11-14 15:07:22'),
(302, 3, 'Account Registration Status ⚠️', 'We regret to inform you that your account registration request has been rejected by the Admin.', 0, '2026-11-14 15:07:24'),
(327, 32, 'Account Approved! 🎉', 'Hello John cris Bubay! Your account registration has been approved by the Admin. You can now use the system.', 0, '2026-11-14 16:11:36'),
(329, 33, 'Account Approved! 🎉', 'Hello rai! Your account registration has been approved by the Admin. You can now use the system.', 0, '2026-11-14 16:14:07'),
(330, 1, 'New User Registration 👤', 'johncris has registered a new student account and is awaiting approval.', 1, '2026-11-15 03:41:22'),
(331, 34, 'Account Approved! 🎉', 'Hello johncris! Your account registration has been approved by the Admin. You can now use the system.', 0, '2026-11-15 03:41:41');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `target_type` enum('Facility','Equipment') NOT NULL,
  `target_name` varchar(150) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `status` enum('Pending','In Review','Resolved') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `student_id` varchar(100) DEFAULT NULL,
  `student_name` varchar(255) DEFAULT NULL,
  `room_name` varchar(255) NOT NULL,
  `reservation_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `purpose` text NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `student_id`, `student_name`, `room_name`, `reservation_date`, `start_time`, `end_time`, `purpose`, `status`, `created_at`, `user_id`) VALUES
(1, '', 'System Administrator', 'Audio Visual Room (AVR)', '2026-10-05', '11:20:00', '23:20:00', '[BSIT ert{} Academic Lecture | Pax: 20] SIA', 'Pending', '2026-10-05 18:20:36', NULL),
(2, '', 'System Administrator', 'Gymnasium / Court', '2026-10-09', '11:25:00', '23:25:00', '[BSIT ert{} Academic Lecture | Pax: 20] sia', 'Pending', '2026-10-05 18:25:35', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `rooms`
-- (See below for the actual view)
--
CREATE TABLE `rooms` (
`id` int(11)
,`name` varchar(255)
,`location` varchar(255)
,`capacity` int(11)
,`description` text
,`status` enum('Available','Unavailable','Under Maintenance')
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('Admin','Student','Faculty') NOT NULL DEFAULT 'Student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email` varchar(100) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `profile_pic` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `student_id`, `username`, `password`, `full_name`, `role`, `created_at`, `email`, `status`, `profile_pic`) VALUES
(1, NULL, 'admin', '$2y$10$ObjyKODsopBOL5gFoIMgj.4UFvo9Ri6AxewvTpa5oij.IZuvMNRBq', 'System Administrator', 'Admin', '2026-10-04 13:02:59', NULL, 'Approved', 'uploads/user_1_1794667877.png'),
(2, '2026-0001', 'student1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Dela Cruz', 'Student', '2026-10-04 13:02:59', NULL, 'Rejected', NULL),
(3, 's22', '', '1234', 'john cris', 'Student', '2026-10-04 13:17:25', 'johnbubay111@gmail.com', 'Rejected', NULL),
(7, 's0909', 's0909', '1234', 'john cris bubay', 'Student', '2026-10-04 13:24:37', 'john111@gmail.com', 'Rejected', NULL),
(8, 's123', 's123', '1234', 'john', 'Student', '2026-10-05 13:59:20', 'johnbubay11111@gmail.com', 'Rejected', NULL),
(9, 's8080', 's9090', '1234', 'john cris bubay', 'Student', '2026-10-05 14:10:27', 'john@gmail.com', 'Approved', NULL),
(10, NULL, 'faculty', '$2y$10$QpmIVTVKC1ixKpmFR/COSO/aLf2xFEiLCZNjd/pm1sk36dS6mYPiC', 'Mam Grace Buban', 'Faculty', '2026-10-05 17:10:47', NULL, 'Approved', NULL),
(12, '121213', '121213', '$2y$10$Rz6nQmRSRqPcuJHml2es8ugfJdL.ZAIUVIGnN9aX2eaX8GUN1CBIW', 'johncrisbubayyyy', 'Student', '2026-10-14 15:07:31', 'johnbubay14@gmail.com', 'Approved', NULL),
(14, NULL, 'bubay', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Sir Bubay', 'Faculty', '2026-10-05 17:10:47', NULL, 'Approved', NULL),
(15, NULL, 'buban', '123', 'Mam Grace Buban', 'Faculty', '2026-10-05 17:10:47', NULL, 'Approved', 'uploads/user_15_1794586279.jpg'),
(16, 'S241009', 'S241009', '$2y$10$xsI3Bfh8.BAgzS5fpXJBUOObUhBA9Ip941/cLHq5OMkrrI3jFFq9C', 'Ervin', 'Student', '2026-10-21 06:08:29', 'lumenervin@gmail.com', 'Approved', 'uploads/user_16_1794587645.jpg'),
(18, '0707', '0707', '$2y$10$ztYKKgDQu7P0t8aAiEWUj.JmOk.u13iEr0XYLG.w1DLaDRfFbwHYq', 'cherry', 'Student', '2026-11-11 13:56:34', 'cherry@gmail.com', 'Approved', 'uploads/user_18_1794411215.webp'),
(32, 's5656', 's5656', '$2y$10$ABDgPA1XnMoHC9dpSXjIfeSEpbj581bXQDKmDr3NNDb3GqJcb6IJu', 'John cris Bubay', 'Student', '2026-11-14 16:11:24', 'johnbubay116@gmail.com', 'Approved', NULL),
(33, 's999', 's999', '$2y$10$tZF8sqMpEJ4bAhw0XtNtd.Q0iCa66pBCdYOiFhR4QWQ1iK4cAsxiG', 'rai', 'Student', '2026-11-14 16:14:00', 'johnbubays111@gmail.com', 'Approved', NULL),
(34, 's4545', 's4545', '$2y$10$TotNcXkHd3BxMSIu4Bd5HOoPftWEryZ/9od4qbH/DD/QZr.3gpaOS', 'johncris', 'Student', '2026-11-15 03:41:22', 'johnbubay611@gmail.com', 'Approved', NULL);

-- --------------------------------------------------------

--
-- Structure for view `rooms`
--
DROP TABLE IF EXISTS `rooms`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `rooms`  AS SELECT `facilities`.`id` AS `id`, `facilities`.`name` AS `name`, `facilities`.`location` AS `location`, `facilities`.`capacity` AS `capacity`, `facilities`.`description` AS `description`, `facilities`.`status` AS `status`, `facilities`.`created_at` AS `created_at` FROM `facilities` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `borrowings`
--
ALTER TABLE `borrowings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipment_id` (`equipment_id`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `equipment_code` (`equipment_code`);

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `facility_bookings`
--
ALTER TABLE `facility_bookings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `facility_reservations`
--
ALTER TABLE `facility_reservations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `borrowings`
--
ALTER TABLE `borrowings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `facility_bookings`
--
ALTER TABLE `facility_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `facility_reservations`
--
ALTER TABLE `facility_reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=332;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `borrowings`
--
ALTER TABLE `borrowings`
  ADD CONSTRAINT `borrowings_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
