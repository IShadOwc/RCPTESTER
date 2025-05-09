-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 25, 2025 at 12:28 PM
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
-- Database: `rcp`
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

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `code`, `description`, `created_at`) VALUES
(1, 'Zielony Przejazd (zmiany i testy)', '', '', '2025-04-24 09:38:06'),
(2, 'Zielony Przejazd (poligon)', '', '', '2025-04-24 09:49:06'),
(3, 'Zielona Nastawnia', '', '', '2025-04-24 10:18:04'),
(4, 'Frauscher Reset', '', '', '2025-04-24 10:18:11'),
(5, 'Frauscher Tester', '', '', '2025-04-24 10:18:16'),
(6, 'Radionika Wdrożenie', '', '', '2025-04-24 10:18:23'),
(7, 'Utrzymanie i wsparcie ETA', '', '', '2025-04-24 10:18:31'),
(8, 'Detekcja Przeszkody Wniosek', '', '', '2025-04-24 10:18:36'),
(9, 'Lampa autonomiczna', '', '', '2025-04-24 10:18:44'),
(10, 'Urlop', '', '', '2025-04-24 10:18:51'),
(11, 'L4', '', '', '2025-04-24 10:18:54'),
(12, 'Inne 1 (spotkania ogólne)', '', '', '2025-04-24 10:19:02'),
(13, 'Inne 2 (zajęcia własne)', '', '', '2025-04-24 10:19:08'),
(14, 'Inne 3 (badania okresowe)', '', '', '2025-04-24 10:19:12'),
(15, 'EZP-1 TOP', '', '', '2025-04-24 10:19:18'),
(16, 'Reset SMS', '', '', '2025-04-24 10:19:24'),
(17, 'Kabel Frauscher', '', '', '2025-04-24 10:19:32'),
(18, 'EZP-1 4 kpl', '', '', '2025-04-24 10:19:38'),
(19, 'Budowa Makoszowy – 0039', '', '', '2025-04-24 10:19:45'),
(20, 'Zielona Nastawnia Racibórz – 0070', '', '', '2025-04-24 10:19:52'),
(21, 'Komentarze dodatkowe informacje', '', '', '2025-04-24 10:20:02');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `users`
--

CREATE TABLE `users` (
  `ID` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin','','') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`ID`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'Admin', '$2y$10$QctdmoubIE7y8LtQatqBcu41ka/AurzD7zNtwXSWKmWE2yfC.hkhm', 'admin', '2025-04-25 10:23:16');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `work_entries`
--
ALTER TABLE `work_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
