-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 18, 2026 at 09:22 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `hangout_db`
--
CREATE DATABASE IF NOT EXISTS `hangout_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hangout_db`;

--
-- Dumping data for table `places`
--

INSERT INTO `places` (`id`, `name`, `description`, `image_url`, `address`, `latitude`, `longitude`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 'Campus Study Cafe', 'A simple cafe concept with seating suitable for studying.', 'https://plus.unsplash.com/premium_photo-1664970900025-1e3099ca757a?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=NHwxMjA3fDA%3D', 'Near the campus entrance', 14.5547000, 121.0244000, '2026-10-11 14:55:23', '2026-10-18 19:20:46', 1),
(2, 'Student Budget Meals', 'An example affordable food spot for students.', NULL, 'Near the student activity area', 14.5539000, 121.0235000, '2026-10-11 14:55:23', '2026-10-18 14:10:41', 0),
(3, 'Quiet Corner', 'A sample quiet location for reading and independent work.', NULL, 'Near the campus library', 14.5552000, 121.0251000, '2026-10-11 14:55:23', '2026-10-18 14:10:38', 0),
(6, 'TestPlace', 'TestDesc', NULL, 'TestAddress', NULL, NULL, '2026-10-18 08:54:56', '2026-10-18 14:10:43', 0),
(7, 'Awesome Sauce Place', 'Area', NULL, 'Test', 14.5539530, 121.0256700, '2026-10-18 14:02:01', '2026-10-18 14:52:36', 1),
(8, 'TFC Chicken', 'Best Burger Steaks In The World', NULL, 'Behind TCU', 14.4923550, 121.0542630, '2026-10-18 16:40:24', '2026-10-18 16:40:24', 1),
(9, 'TestImg', 'ImgTest', 'https://images.unsplash.com/photo-1789349062001-cf5c014bbff0?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=NHwxMjA3fDA%3D', 'ImgTest', 14.4872660, 121.0604310, '2026-10-18 19:15:54', '2026-10-18 19:19:47', 1);

--
-- Dumping data for table `place_suggestions`
--

INSERT INTO `place_suggestions` (`id`, `user_id`, `name`, `description`, `address`, `latitude`, `longitude`, `status`, `admin_note`, `reviewed_by`, `reviewed_at`, `created_at`) VALUES
(1, 4, 'Awesome Sauce Place', 'Area', 'Test', 14.553953, 121.025670, 'approved', NULL, 5, '2026-10-18 22:02:01', '2026-10-18 22:01:24'),
(2, 6, 'TFC Chicken', 'Best Burger Steaks In The World', 'Behind TCU', 14.492355, 121.054263, 'approved', NULL, 5, '2026-10-19 00:40:24', '2026-10-19 00:39:00');

--
-- Dumping data for table `place_tags`
--

INSERT INTO `place_tags` (`place_id`, `tag_id`) VALUES
(1, 1),
(1, 4),
(1, 5),
(1, 7),
(2, 1),
(2, 4),
(3, 2),
(3, 3),
(7, 1),
(7, 4),
(7, 5);

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `user_id`, `place_id`, `rating`, `comment`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 1, 1, 4, 'Good place to study.', '2026-10-12 14:26:44', '2026-10-12 14:26:44', 1),
(2, 3, 1, 5, 'Love this place, Great for studying.', '2026-10-12 15:42:13', '2026-10-12 15:42:13', 1),
(3, 3, 3, 4, 'Fairly Quiet Place, Pretty Good', '2026-10-12 15:42:36', '2026-10-12 15:42:36', 1),
(4, 3, 2, 5, NULL, '2026-10-12 15:44:08', '2026-10-12 15:44:08', 1),
(5, 4, 1, 5, 'Maganda', '2026-10-17 09:25:39', '2026-10-18 16:43:12', 1),
(6, 4, 2, 5, 'Galing', '2026-10-18 08:48:20', '2026-10-18 10:21:06', 1),
(7, 6, 7, 4, 'This looks cool', '2026-10-18 16:36:43', '2026-10-18 16:36:43', 1);

--
-- Dumping data for table `tags`
--

INSERT INTO `tags` (`id`, `name`, `slug`) VALUES
(1, 'Cheap', 'cheap'),
(2, 'Study-Friendly', 'study-friendly'),
(3, 'Quiet', 'quiet'),
(4, 'Great Food', 'great-food'),
(5, 'Hidden Gem', 'hidden-gem'),
(6, 'Pet-Friendly', 'pet-friendly'),
(7, 'Air-Conditioned', 'air-conditioned');

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `created_at`) VALUES
(1, 'Angelo', 'angelonieves312@gmail.com', '$2y$10$vQsRe6yZdZ.8i4gL9epVxeUQ/FzLxnC/00JwuvZQ6w8w1OtcsUUyC', 'user', '2026-10-11 15:21:18'),
(3, 'Test', 'Test@email.com', '$2y$10$BpNAIN2cXWQuMJ3veFeZ3.965wxAMBsfAfst7EuKPy6kqvHX41NqW', 'user', '2026-10-12 13:00:01'),
(4, 'Cosette', 'Cosette@gmail.com', '$2y$10$9EUCJKBf9dWXUk3MDQ3DX.w.mprpBW5V/k.rFvY1g6ns/kX0izLTS', 'user', '2026-10-17 09:24:06'),
(5, 'Admin', 'Admin@admin.com', '$2y$10$9MsPQsSRC.3wYCuYx6aeCOj0e4Z6hF0nH3FDqU3wxyuJZYw45gh2m', 'admin', '2026-10-17 18:41:50'),
(6, 'Kim', 'Kim@gmail.com', '$2y$10$gMw7lhn7zbSrJF6WLuaO1u9gUHVTNZmp1DIDxsnntl8btpf4vUQhG', 'user', '2026-10-18 16:36:09');
COMMIT;
