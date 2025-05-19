-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Maj 08, 2025 at 11:11 AM
-- Wersja serwera: 10.4.32-MariaDB
-- Wersja PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rcp2`
--

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `table_name` varchar(255) NOT NULL,
  `record_id` int(11) NOT NULL,
  `details` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `username`, `action`, `table_name`, `record_id`, `details`, `timestamp`) VALUES
(1, 1, 'Admin', 'User login', 'users', 1, 'Successful login', '2025-05-06 08:43:55'),
(2, 1, 'Admin', 'User login', 'users', 1, 'Successful login', '2025-05-06 08:50:03'),
(3, 1, 'Admin', 'User login', 'users', 1, 'Successful login', '2025-05-06 08:51:41'),
(4, 1, 'Admin', 'User login', 'users', 1, 'Successful login', '2025-05-06 08:51:59'),
(5, 1, 'Admin', 'User login', 'users', 1, 'Successful login', '2025-05-06 08:52:06'),
(6, 1, 'Admin', 'User login', 'users', 1, 'Successful login', '2025-05-06 08:52:41');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `users`
--

CREATE TABLE `users` (
  `ID` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin','','') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `max_hours_per_day` int(11) DEFAULT 8
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`ID`, `username`, `password`, `role`, `created_at`, `max_hours_per_day`) VALUES
(1, 'Admin', '$2y$10$QctdmoubIE7y8LtQatqBcu41ka/AurzD7zNtwXSWKmWE2yfC.hkhm', 'admin', '2025-04-25 10:23:16', 8),
(3, 'pracownik1', '$2y$10$6RsRjM8wl0BTLwBuQN3HP.6aGSTaUkGpCRpBzn834HhO3EjOFZUxG', 'user', '2025-04-28 09:09:21', 8);

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `work_entries`
--

CREATE TABLE `work_entries` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `project_id` int(11) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `hours` decimal(4,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `week_number` int(11) NOT NULL,
  `year` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;

--
-- Indeksy dla zrzutów tabel
--

--
-- Indeksy dla tabeli `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`);

--
-- Indeksy dla tabeli `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indeksy dla tabeli `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`ID`);

--
-- Indeksy dla tabeli `work_entries`
--
ALTER TABLE `work_entries`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `work_entries`
--
ALTER TABLE `work_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
