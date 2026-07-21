-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- Generation Time: May 25, 2026 at 07:43 AM
-- Server version: 11.4.11-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+06:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `book_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `isbn` varchar(20) NOT NULL,
  `author_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `status` enum('AVAILABLE','ISSUED','LOST') DEFAULT 'AVAILABLE',
  `added_date` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`book_id`, `title`, `author`, `quantity`, `isbn`, `author_id`, `category_id`, `status`, `added_date`) VALUES
(7, 'Structured Programming Language', '', 10, 'CSE510201', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(8, 'Electrical and Electronic Circuit', '', 10, 'CSE510203', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(9, 'Calculus', '', 9, 'CSE510205', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(10, 'Physics', '', 10, 'CSE510207', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(11, 'English', '', 10, 'CSE510209', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(12, 'Digital Systems Design', '', 10, 'CSE510221', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(13, 'Discrete Mathematics', '', 10, 'CSE510223', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(14, 'Linear Algebra', '', 10, 'CSE510225', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(15, 'Statistics and Probability', '', 10, 'CSE510227', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(16, 'History of the Emergence of Independent Bangladesh', '', 10, 'CSE510229', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(17, 'Data Structure', '', 10, 'CSE520201', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(18, 'Object Oriented Programming', '', 10, 'CSE520203', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(19, 'Computer Architecture', '', 9, 'CSE520205', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(20, 'Ordinary Differential Equation', '', 10, 'CSE520207', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(21, 'Fundamental of Business Studies', '', 10, 'CSE520209', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(22, 'Database Management System', '', 10, 'CSE520221', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(23, 'Microprocessor and Assembly Language', '', 10, 'CSE520223', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(24, 'Design and Analysis of Algorithm', '', 10, 'CSE520225', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(25, 'Numerical Analysis', ' ', 10, 'CSE520227', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(26, 'Peripheral and Interfacing', '', 10, 'CSE530201', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(27, 'Data and Telecommunications', '', 10, 'CSE530203', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(28, 'Operating System', '', 10, 'CSE530205', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(29, 'Economics', '', 10, 'CSE530207', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(30, 'Software Engineering', '', 10, 'CSE530219', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(31, 'Computer Networking', '', 10, 'CSE530221', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(32, 'System Analysis and Design', '', 10, 'CSE530223', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(33, 'Theory of Computation', '', 10, 'CSE530225', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(34, 'Artificial Intelligence', '', 10, 'CSE540201', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(35, 'Compiler Design and Construction', '', 10, 'CSE540203', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(36, 'Computer Graphics', '', 10, 'CSE540205', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(37, 'E-Commerce and Web Engineering', '', 10, 'CSE540207', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(38, 'Network and Information Security', '', 10, 'CSE540219', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(39, 'Digital Image Processing', '', 10, 'CSE540221', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10'),
(40, 'Parallel and Distributed Processing', '', 10, 'CSE540223', NULL, 1, 'AVAILABLE', '2026-01-24 14:35:10');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`) VALUES
(1, 'CSE'),
(2, 'AMT'),
(3, 'FDT'),
(4, 'BBA');

-- --------------------------------------------------------

--
-- Table structure for table `issued_books`
--

CREATE TABLE `issued_books` (
  `issue_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `issue_date` datetime NOT NULL,
  `return_date` datetime DEFAULT NULL,
  `status` enum('issued','returned') DEFAULT 'issued'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `issued_books`
--

INSERT INTO `issued_books` (`issue_id`, `book_id`, `user_id`, `issue_date`, `return_date`, `status`) VALUES
(35, 9, 7, '2026-01-21 10:13:33', '2026-02-26 13:30:18', 'returned'),
(40, 9, 8, '2020-01-01 13:41:57', NULL, 'issued'),
(43, 19, 7, '2026-02-23 09:25:54', NULL, 'issued'),
(44, 34, 8, '2026-01-27 18:50:18', '2026-02-27 00:00:00', 'returned');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mobile_no` varchar(20) DEFAULT NULL,
  `role` enum('admin','student') DEFAULT 'student',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `login_attempts` int(11) DEFAULT 0,
  `lockout_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `email`, `mobile_no`, `role`, `created_at`, `login_attempts`, `lockout_time`) VALUES
(1, 'admin', '$2y$10$4zc9yRxkkama19GNdmMqIOa5DtUVA400GgMhaPDyx5f3lrQfA3URK', 'ADMINISTRATOR', NULL, NULL, 'admin', '2026-01-24 05:27:50', 0, NULL),
(2, 'admin2', '$2a$12$FIkkrxeIvSyoNTrA3wExIuwyZ9BjhDZEb8CMMUlEYUcAjXUHz15vO', 'ADMIN-2', NULL, NULL, 'admin', '2026-02-27 03:45:40', 0, NULL),
(5, 'librarian', '$2y$10$Hodokrwbilkcyoof0jImmOxDOg26CNlAQh/6xD1MFTDdZCDN/qRjS', 'librarian', NULL, '0', 'admin', '2026-01-28 05:31:47', 0, NULL),
(7, 'CSE2270502022', '$2y$10$3XS11uoFBTpuDVdFBtLFwue355a4soCQOAPKyQbyNtE2m7bNI77Z2', 'Student1', NULL, '', 'student', '2026-01-23 12:58:48', 0, NULL),
(8, 'CSE2270502047', '$2y$10$j1bbl5LUAkoRxqDxV2uc2Oc0d8xKjB55mbaU4ODkc.rCAeYdUKDZ6', 'Student2', NULL, NULL, 'student', '2026-01-24 14:24:37', 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`book_id`),
  ADD UNIQUE KEY `isbn` (`isbn`),
  ADD KEY `author_id` (`author_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `issued_books`
--
ALTER TABLE `issued_books`
  ADD PRIMARY KEY (`issue_id`),
  ADD KEY `book_id` (`book_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `book_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `issued_books`
--
ALTER TABLE `issued_books`
  MODIFY `issue_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `books`
--
ALTER TABLE `books`
  ADD CONSTRAINT `books_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL;

--
-- Constraints for table `issued_books`
--
ALTER TABLE `issued_books`
  ADD CONSTRAINT `issued_books_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `issued_books_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
