-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 09, 2025 at 06:19 AM
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
-- Database: `drive_clone`
--

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `folders`
--

CREATE TABLE `folders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `folder_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `folders`
--

INSERT INTO `folders` (`id`, `user_id`, `folder_name`, `created_at`) VALUES
(1, 2, 'JIA', '2025-03-09 02:01:52');

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

CREATE TABLE `lessons` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `lesson_name` varchar(255) NOT NULL,
  `total_chapters` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lesson_files`
--

CREATE TABLE `lesson_files` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `folder_id` int(11) DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(255) NOT NULL,
  `file_type` varchar(10) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lesson_files`
--

INSERT INTO `lesson_files` (`id`, `user_id`, `folder_id`, `filename`, `filepath`, `file_type`, `upload_date`) VALUES
(2, 2, 1, 'acm-code-of-ethics-and-professional-conduct.pdf', 'uploads/67cd14e07dc58_acm-code-of-ethics-and-professional-conduct.pdf', 'pdf', '2025-03-09 04:11:12');

-- --------------------------------------------------------

--
-- Table structure for table `lesson_folders`
--

CREATE TABLE `lesson_folders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `folder_name` varchar(255) NOT NULL,
  `parent_folder_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lesson_folders`
--

INSERT INTO `lesson_folders` (`id`, `user_id`, `folder_name`, `parent_folder_id`, `created_at`) VALUES
(1, 2, 'BREN', NULL, '2025-03-09 01:33:24'),
(2, 2, 'jia', 1, '2025-03-09 01:55:36');

-- --------------------------------------------------------

--
-- Table structure for table `notes`
--

CREATE TABLE `notes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `progress_logs`
--

CREATE TABLE `progress_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `chapter_number` int(11) NOT NULL,
  `status` enum('not_started','in_progress','completed') DEFAULT 'not_started',
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviewer_notes`
--

CREATE TABLE `reviewer_notes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `note_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(255) NOT NULL,
  `highlights` text DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `user_id`, `file_id`, `filename`, `filepath`, `highlights`, `comments`, `created_at`) VALUES
(3, 2, 2, '', '', '{}', 'sas', '2025-03-09 04:23:38'),
(4, 2, 2, '', '', '{}', 'sas', '2025-03-09 04:23:59'),
(5, 2, 2, '', '', '{}', 'sas', '2025-03-09 04:25:08'),
(6, 2, 2, '', '', '{}', 'sas', '2025-03-09 04:27:02'),
(7, 2, 2, '', '', '{}', 'sas', '2025-03-09 04:28:07'),
(8, 2, 2, '', '', '{}', 'sas', '2025-03-09 04:30:54'),
(9, 2, 2, '', '', '{}', 'sas', '2025-03-09 04:33:30'),
(10, 2, 2, '', '', '{}', 'sas', '2025-03-09 04:37:31'),
(11, 2, 2, '', '', '{}', 'sasa', '2025-03-09 04:43:49'),
(12, 2, 2, '', '', '{}', 'sasa', '2025-03-09 04:48:46'),
(13, 2, 2, '', '', '{}', 'sasa', '2025-03-09 04:50:41'),
(14, 2, 2, '', '', '{}', 'sasa', '2025-03-09 04:51:10');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `schedule_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `timers`
--

CREATE TABLE `timers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `timer_name` varchar(255) NOT NULL,
  `study_duration` int(11) NOT NULL,
  `rest_duration` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timers`
--

INSERT INTO `timers` (`id`, `user_id`, `timer_name`, `study_duration`, `rest_duration`, `created_at`) VALUES
(1, 2, 'Lesson Recap', 1500, 300, '2025-03-09 02:15:45');

-- --------------------------------------------------------

--
-- Table structure for table `timer_logs`
--

CREATE TABLE `timer_logs` (
  `id` int(11) NOT NULL,
  `timer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `start_time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `end_time` timestamp NULL DEFAULT NULL,
  `status` enum('studying','resting','completed') DEFAULT 'studying'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'brennz', '$2y$10$XR0UIfVk2kTvgZwhPxdWIeC60K07.wmGlGtlmVA0yjbK9Lsk8Okk.', '2025-03-09 00:40:41'),
(2, 'brenny', '$2y$10$fivTey4BYP6WUisN/yNI6.Rgi4FIZ.jHEkWO7ClNYl8bvJVn4Yw1S', '2025-03-09 00:40:51');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `folders`
--
ALTER TABLE `folders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `lesson_files`
--
ALTER TABLE `lesson_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `folder_id` (`folder_id`);

--
-- Indexes for table `lesson_folders`
--
ALTER TABLE `lesson_folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `parent_folder_id` (`parent_folder_id`);

--
-- Indexes for table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `progress_logs`
--
ALTER TABLE `progress_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_progress` (`user_id`,`lesson_id`,`chapter_number`),
  ADD KEY `lesson_id` (`lesson_id`);

--
-- Indexes for table `reviewer_notes`
--
ALTER TABLE `reviewer_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `note_id` (`note_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `file_id` (`file_id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `timers`
--
ALTER TABLE `timers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `timer_logs`
--
ALTER TABLE `timer_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timer_id` (`timer_id`),
  ADD KEY `user_id` (`user_id`);

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
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `folders`
--
ALTER TABLE `folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lesson_files`
--
ALTER TABLE `lesson_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `lesson_folders`
--
ALTER TABLE `lesson_folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `progress_logs`
--
ALTER TABLE `progress_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviewer_notes`
--
ALTER TABLE `reviewer_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `timers`
--
ALTER TABLE `timers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `timer_logs`
--
ALTER TABLE `timer_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `lessons`
--
ALTER TABLE `lessons`
  ADD CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `lesson_files`
--
ALTER TABLE `lesson_files`
  ADD CONSTRAINT `lesson_files_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `lesson_files_ibfk_2` FOREIGN KEY (`folder_id`) REFERENCES `lesson_folders` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lesson_folders`
--
ALTER TABLE `lesson_folders`
  ADD CONSTRAINT `lesson_folders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `lesson_folders_ibfk_2` FOREIGN KEY (`parent_folder_id`) REFERENCES `lesson_folders` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `progress_logs`
--
ALTER TABLE `progress_logs`
  ADD CONSTRAINT `progress_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `progress_logs_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviewer_notes`
--
ALTER TABLE `reviewer_notes`
  ADD CONSTRAINT `reviewer_notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `reviewer_notes_ibfk_2` FOREIGN KEY (`note_id`) REFERENCES `notes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `lesson_files` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `timers`
--
ALTER TABLE `timers`
  ADD CONSTRAINT `timers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `timer_logs`
--
ALTER TABLE `timer_logs`
  ADD CONSTRAINT `timer_logs_ibfk_1` FOREIGN KEY (`timer_id`) REFERENCES `timers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `timer_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
