-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 06, 2025 at 05:13 PM
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
-- Database: `pengumpulantugas`
--

-- --------------------------------------------------------

--
-- Table structure for table `laporan`
--

CREATE TABLE `laporan` (
  `id` int(11) NOT NULL,
  `modul_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_laporan` varchar(255) NOT NULL,
  `tanggal_submit` timestamp NOT NULL DEFAULT current_timestamp(),
  `nilai` int(11) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `tanggal_dinilai` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `laporan`
--

INSERT INTO `laporan` (`id`, `modul_id`, `user_id`, `file_laporan`, `tanggal_submit`, `nilai`, `feedback`, `tanggal_dinilai`) VALUES
(1, 1, 7, 'laporan_686a8cd826d3e.pdf', '2025-07-06 14:48:56', 1, 'Belajar lagi dek!', '2025-07-06 14:51:31');

-- --------------------------------------------------------

--
-- Table structure for table `mata_praktikum`
--

CREATE TABLE `mata_praktikum` (
  `id` int(11) NOT NULL,
  `nama_praktikum` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `kode_praktikum` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mata_praktikum`
--

INSERT INTO `mata_praktikum` (`id`, `nama_praktikum`, `deskripsi`, `kode_praktikum`, `created_at`) VALUES
(1, 'Pemrograman Web', 'Mata Kuliah Semester 4', 'PW(TI04)', '2025-07-06 13:39:08'),
(3, 'Jaringan Komputer', 'Mata Kuliah Semester 4', 'JK(TI04)', '2025-07-06 13:43:49');

-- --------------------------------------------------------

--
-- Table structure for table `modul`
--

CREATE TABLE `modul` (
  `id` int(11) NOT NULL,
  `mata_praktikum_id` int(11) NOT NULL,
  `nama_modul` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `file_materi` varchar(255) DEFAULT NULL,
  `urutan` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `modul`
--

INSERT INTO `modul` (`id`, `mata_praktikum_id`, `nama_modul`, `deskripsi`, `file_materi`, `urutan`, `created_at`) VALUES
(1, 3, 'Modul Pertemuan 1', 'Modul Praktikum', 'materi_686a826a17d57.pdf', 1, '2025-07-06 14:04:26');

-- --------------------------------------------------------

--
-- Table structure for table `praktikum_mahasiswa`
--

CREATE TABLE `praktikum_mahasiswa` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `mata_praktikum_id` int(11) NOT NULL,
  `tanggal_daftar` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `praktikum_mahasiswa`
--

INSERT INTO `praktikum_mahasiswa` (`id`, `user_id`, `mata_praktikum_id`, `tanggal_daftar`) VALUES
(2, 7, 3, '2025-07-06 14:34:59');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('mahasiswa','asisten') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `email`, `password`, `role`, `created_at`) VALUES
(6, 'Asisten1', 'a1@gmail.com', '$2y$10$pzZyrkzFVm/79q936.njpuMhAVTczQuScv7CTqHdfDA8GcrNkIR9O', 'asisten', '2025-07-06 14:21:30'),
(7, 'Mahasiswa1', 'm1@gmail.com', '$2y$10$YQx81yQ0eqCvf2BNMiBLnemqmNisz0LefL7bJk0P.dHlhRC0eWnhm', 'mahasiswa', '2025-07-06 14:23:56');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `laporan`
--
ALTER TABLE `laporan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_report` (`modul_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `mata_praktikum`
--
ALTER TABLE `mata_praktikum`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_praktikum` (`kode_praktikum`);

--
-- Indexes for table `modul`
--
ALTER TABLE `modul`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mata_praktikum_id` (`mata_praktikum_id`);

--
-- Indexes for table `praktikum_mahasiswa`
--
ALTER TABLE `praktikum_mahasiswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`user_id`,`mata_praktikum_id`),
  ADD KEY `mata_praktikum_id` (`mata_praktikum_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `laporan`
--
ALTER TABLE `laporan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `mata_praktikum`
--
ALTER TABLE `mata_praktikum`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `modul`
--
ALTER TABLE `modul`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `praktikum_mahasiswa`
--
ALTER TABLE `praktikum_mahasiswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `laporan`
--
ALTER TABLE `laporan`
  ADD CONSTRAINT `laporan_ibfk_1` FOREIGN KEY (`modul_id`) REFERENCES `modul` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `laporan_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `modul`
--
ALTER TABLE `modul`
  ADD CONSTRAINT `modul_ibfk_1` FOREIGN KEY (`mata_praktikum_id`) REFERENCES `mata_praktikum` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `praktikum_mahasiswa`
--
ALTER TABLE `praktikum_mahasiswa`
  ADD CONSTRAINT `praktikum_mahasiswa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `praktikum_mahasiswa_ibfk_2` FOREIGN KEY (`mata_praktikum_id`) REFERENCES `mata_praktikum` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
