-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3309
-- Tiempo de generación: 15-04-2026 a las 17:31:42
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
-- Estructura de tabla para la tabla `notification_recipients`
--

CREATE TABLE `notification_recipients` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  `staff_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `notification_recipients`
--

INSERT INTO `notification_recipients` (`id`, `empresa_id`, `staff_id`, `created_at`) VALUES
(1, 1, 1, '2026-04-15 10:03:02'),
(2, 1, 9, '2026-04-15 10:03:02'),
(3, 1, 1000, '2026-04-15 10:03:02'),
(4, 1, 13, '2026-04-15 10:03:02'),
(5, 1, 7, '2026-04-15 10:03:02'),
(6, 1, 16, '2026-04-15 10:03:02'),
(7, 1, 18, '2026-04-15 10:03:02'),
(8, 1, 14, '2026-04-15 10:03:02'),
(9, 1, 15, '2026-04-15 10:03:02'),
(10, 1, 2, '2026-04-15 10:03:02'),
(11, 1, 999, '2026-04-15 10:03:02');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `notification_recipients`
--
ALTER TABLE `notification_recipients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_notification_recipient` (`empresa_id`,`staff_id`),
  ADD KEY `idx_notification_staff` (`staff_id`),
  ADD KEY `idx_notification_empresa` (`empresa_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `notification_recipients`
--
ALTER TABLE `notification_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
