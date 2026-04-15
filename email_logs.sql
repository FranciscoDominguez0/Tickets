-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3309
-- Tiempo de generación: 15-04-2026 a las 17:29:23
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `tickets_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `email_logs`
--

CREATE TABLE `email_logs` (
  `id` bigint(20) NOT NULL,
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  `queue_id` bigint(20) DEFAULT NULL,
  `recipient_email` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `email_logs`
--

INSERT INTO `email_logs` (`id`, `empresa_id`, `queue_id`, `recipient_email`, `status`, `error_message`, `created_at`) VALUES
(1, 1, 1, 'cuenta8fran@gmail.com', 'sent', NULL, '2026-04-15 10:19:34'),
(2, 1, 2, 'soport1@gmail.com', 'sent', NULL, '2026-04-15 10:19:36'),
(3, 1, 3, 'dominguezf041@gmail.com', 'sent', NULL, '2026-04-15 10:19:38'),
(4, 1, 4, 'ashley@gmail.com', 'sent', NULL, '2026-04-15 10:19:41'),
(5, 1, 5, 'cuenta9fran@gmail.com', 'sent', NULL, '2026-04-15 10:19:43'),
(6, 1, 6, 'dominguezf225@gmail.com', 'sent', NULL, '2026-04-15 10:19:45'),
(7, 1, 7, 'francisco.domiguez01@up.ac.pa', 'sent', NULL, '2026-04-15 10:19:48'),
(8, 1, 8, 'franciscocuent3@gmail.com', 'sent', NULL, '2026-04-15 10:19:50'),
(9, 1, 9, 'franciscocuent2@gmail.com', 'sent', NULL, '2026-04-15 10:19:52'),
(10, 1, 10, 'superadmin@vigitec.local', 'sent', NULL, '2026-04-15 10:19:54'),
(11, 1, 11, 'ashley22d@gmail.com', 'sent', NULL, '2026-04-15 10:19:57'),
(12, 1, 12, 'cuenta8fran@gmail.com', 'sent', NULL, '2026-04-15 10:19:59'),
(13, 1, 13, 'soport1@gmail.com', 'sent', NULL, '2026-04-15 10:20:03'),
(14, 1, 14, 'dominguezf041@gmail.com', 'sent', NULL, '2026-04-15 10:20:05'),
(15, 1, 15, 'ashley@gmail.com', 'sent', NULL, '2026-04-15 10:20:07'),
(16, 1, 16, 'cuenta9fran@gmail.com', 'sent', NULL, '2026-04-15 10:20:10'),
(17, 1, 17, 'dominguezf225@gmail.com', 'sent', NULL, '2026-04-15 10:20:12'),
(18, 1, 18, 'francisco.domiguez01@up.ac.pa', 'sent', NULL, '2026-04-15 10:20:15'),
(19, 1, 19, 'franciscocuent3@gmail.com', 'sent', NULL, '2026-04-15 10:20:17'),
(20, 1, 20, 'franciscocuent2@gmail.com', 'sent', NULL, '2026-04-15 10:20:19'),
(21, 1, 21, 'superadmin@vigitec.local', 'sent', NULL, '2026-04-15 10:21:12'),
(22, 1, 22, 'ashley22d@gmail.com', 'sent', NULL, '2026-04-15 10:21:14'),
(23, 1, 23, 'cuenta8fran@gmail.com', 'sent', NULL, '2026-04-15 10:21:16'),
(24, 1, 24, 'soport1@gmail.com', 'sent', NULL, '2026-04-15 10:21:19'),
(25, 1, 25, 'dominguezf041@gmail.com', 'sent', NULL, '2026-04-15 10:21:21'),
(26, 1, 26, 'ashley@gmail.com', 'sent', NULL, '2026-04-15 10:21:23'),
(27, 1, 27, 'cuenta9fran@gmail.com', 'sent', NULL, '2026-04-15 10:21:25'),
(28, 1, 28, 'dominguezf225@gmail.com', 'sent', NULL, '2026-04-15 10:21:28'),
(29, 1, 29, 'francisco.domiguez01@up.ac.pa', 'sent', NULL, '2026-04-15 10:21:30'),
(30, 1, 30, 'franciscocuent3@gmail.com', 'sent', NULL, '2026-04-15 10:21:32'),
(31, 1, 31, 'franciscocuent2@gmail.com', 'sent', NULL, '2026-04-15 10:21:34'),
(32, 1, 32, 'superadmin@vigitec.local', 'sent', NULL, '2026-04-15 10:21:36'),
(33, 1, 33, 'ashley22d@gmail.com', 'sent', NULL, '2026-04-15 10:21:38');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_logs_empresa` (`empresa_id`),
  ADD KEY `idx_email_logs_queue` (`queue_id`),
  ADD KEY `idx_email_logs_status` (`status`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
