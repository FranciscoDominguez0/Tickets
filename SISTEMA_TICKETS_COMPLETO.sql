-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3309
-- Tiempo de generación: 14-02-2026 a las 18:29:33
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
-- Estructura de tabla para la tabla `app_settings`
--

CREATE TABLE `app_settings` (
  `key` varchar(191) NOT NULL,
  `value` longtext DEFAULT NULL,
  `updated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `app_settings`
--

INSERT INTO `app_settings` (`key`, `value`, `updated`) VALUES
('agents.bind_session_ip', '0', '2026-02-09 14:59:27'),
('agents.lockout_minutes', '1', '2026-02-09 14:59:27'),
('agents.max_login_attempts', '4', '2026-02-09 14:59:27'),
('agents.session_timeout_minutes', '28', '2026-02-09 14:59:27'),
('company.address', 'CALLE LOS PINOS, Aguadulce, Panamá', '2026-02-12 09:23:40'),
('company.logo', '', '2026-02-12 09:23:40'),
('company.logo_mode', 'default', '2026-02-12 09:23:40'),
('company.name', 'Vigitec panama', '2026-02-12 09:23:40'),
('company.phone', '6977-4190', '2026-02-12 09:23:40'),
('company.website', 'https://www.vigitecpanama.com/', '2026-02-12 09:23:40'),
('login.background', '../publico/uploads/settings/login_background-20260212082340-e45df077beda.png', '2026-02-12 09:23:40'),
('login.background_mode', 'custom', '2026-02-12 09:23:40'),
('mail.admin_notify_email', 'cuenta9fran@gmail.com', '2026-02-14 12:13:13'),
('mail.alert_from', 'dominguezf225@gmail.com', '2026-02-14 12:13:13'),
('mail.alert_from_name', 'Sistema de Tickets', '2026-02-14 12:13:13'),
('mail.from', 'dominguezf225@gmail.com', '2026-02-14 12:13:13'),
('mail.from_name', 'Sistema de Tickets', '2026-02-14 12:13:13'),
('staff.14.email_task_assigned', '1', '2026-02-14 12:04:19'),
('staff.14.email_ticket_assigned', '1', '2026-02-14 12:04:19'),
('staff.15.email_task_assigned', '1', '2026-02-14 12:04:19'),
('staff.15.email_ticket_assigned', '1', '2026-02-14 12:04:19'),
('staff.16.email_task_assigned', '1', '2026-02-14 12:04:19'),
('staff.16.email_ticket_assigned', '1', '2026-02-14 12:04:19'),
('staff.17.email_task_assigned', '1', '2026-02-12 20:28:12'),
('staff.17.email_ticket_assigned', '1', '2026-02-12 20:28:12'),
('staff.18.email_task_assigned', '1', '2026-02-14 12:04:19'),
('staff.18.email_ticket_assigned', '1', '2026-02-14 12:04:19'),
('staff.2.email_task_assigned', '1', '2026-02-14 12:04:19'),
('staff.2.email_ticket_assigned', '1', '2026-02-14 12:04:19'),
('staff.7.email_task_assigned', '1', '2026-02-14 12:04:19'),
('staff.7.email_ticket_assigned', '1', '2026-02-14 12:04:19'),
('staff.9.email_task_assigned', '1', '2026-02-14 12:04:19'),
('staff.9.email_ticket_assigned', '1', '2026-02-14 12:04:19'),
('system.acl', '', '2026-02-14 12:19:38'),
('system.allow_iframe', '', '2026-02-14 12:19:38'),
('system.attachments_require_auth', '1', '2026-02-14 12:19:38'),
('system.attachment_storage', 'db', '2026-02-14 12:19:38'),
('system.collision_duration', '3', '2026-02-14 12:19:38'),
('system.default_dept_id', '1', '2026-02-14 12:19:38'),
('system.embed_whitelist', 'youtube.com, dailymotion.com, vimeo.com, player.vimeo.com, web.microsoftstream.com', '2026-02-14 12:19:38'),
('system.enable_rich_text', '1', '2026-02-14 12:19:38'),
('system.force_https', '0', '2026-02-14 12:19:38'),
('system.helpdesk_status', 'online', '2026-02-14 12:19:38'),
('system.helpdesk_title', 'Sistema de Tickets', '2026-02-14 12:19:38'),
('system.helpdesk_url', 'http://localhost/sistema-tickets/upload/', '2026-02-14 12:19:38'),
('system.log_level', 'notice', '2026-02-14 12:19:38'),
('system.max_agent_file_mb', '32', '2026-02-14 12:19:38'),
('system.page_size', '25', '2026-02-14 12:19:38'),
('system.primary_language', 'es_MX', '2026-02-14 12:19:38'),
('system.purge_logs_months', '12', '2026-02-14 12:19:38'),
('system.show_avatars', '1', '2026-02-14 12:19:38'),
('system.timezone', 'America/Mexico_City', '2026-02-14 12:19:38'),
('tasks.default_task_priority_id', '1', '2026-02-14 12:10:43'),
('tasks.task_number_format', '#', '2026-02-14 12:10:43'),
('tasks.task_sequence_id', '6', '2026-02-14 12:10:43'),
('tickets.allow_external_images', '1', '2026-02-14 12:10:17'),
('tickets.auto_claim_tickets', '1', '2026-02-14 12:10:17'),
('tickets.auto_refer_closed', '1', '2026-02-14 12:10:17'),
('tickets.collaborator_ticket_visibility', '1', '2026-02-14 12:10:17'),
('tickets.default_help_topic', '0', '2026-02-14 12:10:17'),
('tickets.default_priority_id', '1', '2026-02-14 12:10:17'),
('tickets.default_sla_id', '1', '2026-02-14 12:10:17'),
('tickets.default_ticket_queue', 'open', '2026-02-14 12:10:17'),
('tickets.default_ticket_status_id', '1', '2026-02-14 12:10:17'),
('tickets.enable_captcha', '0', '2026-02-09 14:40:06'),
('tickets.max_open_tickets', '0', '2026-02-14 12:10:17'),
('tickets.queue_bucket_counts', '1', '2026-02-14 12:10:17'),
('tickets.require_topic_to_close', '1', '2026-02-14 12:10:17'),
('tickets.ticket_lock', 'activity', '2026-02-14 12:10:17'),
('tickets.ticket_max_file_mb', '10', '2026-02-14 12:10:17'),
('tickets.ticket_max_uploads', '5', '2026-02-14 12:10:17'),
('tickets.ticket_number_format', '#', '2026-02-14 12:10:17'),
('tickets.ticket_sequence_id', '4', '2026-02-14 12:10:17'),
('users.lockout_minutes', '1', '2026-02-12 10:50:01'),
('users.max_login_attempts', '3', '2026-02-12 10:50:01'),
('users.registration_required', '0', '2026-02-12 10:50:01'),
('users.session_timeout_minutes', '10', '2026-02-12 10:50:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `attachments`
--

CREATE TABLE `attachments` (
  `id` int(11) NOT NULL,
  `thread_entry_id` int(11) DEFAULT NULL COMMENT 'Mensaje asociado',
  `filename` varchar(255) NOT NULL COMMENT 'Nombre en servidor',
  `original_filename` varchar(255) DEFAULT NULL COMMENT 'Nombre original subido',
  `mimetype` varchar(100) DEFAULT NULL COMMENT 'Tipo MIME',
  `size` int(11) DEFAULT NULL COMMENT 'Tamaño en bytes',
  `path` varchar(500) DEFAULT NULL COMMENT 'Ruta del archivo',
  `hash` varchar(64) DEFAULT NULL COMMENT 'Hash SHA256',
  `created` datetime DEFAULT current_timestamp() COMMENT 'Fecha upload'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `banlist`
--

CREATE TABLE `banlist` (
  `id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created` datetime DEFAULT current_timestamp(),
  `updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `config`
--

CREATE TABLE `config` (
  `id` int(11) NOT NULL,
  `config_key` varchar(100) NOT NULL COMMENT 'Clave de configuración',
  `config_value` longtext DEFAULT NULL COMMENT 'Valor',
  `description` text DEFAULT NULL COMMENT 'Descripción',
  `created` datetime DEFAULT current_timestamp() COMMENT 'Creación'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'Nombre del departamento',
  `description` text DEFAULT NULL COMMENT 'Descripción',
  `is_active` tinyint(4) DEFAULT 1 COMMENT '1=activo, 0=inactivo',
  `created` datetime DEFAULT current_timestamp() COMMENT 'Fecha de creación',
  `default_staff_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `is_active`, `created`, `default_staff_id`) VALUES
(1, 'Soporte Técnico', 'Problemas técnicos y troubleshooting', 1, '2026-01-26 23:34:13', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `email_accounts`
--

CREATE TABLE `email_accounts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `priority` varchar(32) DEFAULT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `smtp_host` varchar(255) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT NULL,
  `smtp_secure` varchar(10) DEFAULT NULL,
  `smtp_user` varchar(255) DEFAULT NULL,
  `smtp_pass` varchar(255) DEFAULT NULL,
  `created` datetime DEFAULT current_timestamp(),
  `updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `email_accounts`
--

INSERT INTO `email_accounts` (`id`, `email`, `name`, `priority`, `dept_id`, `is_default`, `smtp_host`, `smtp_port`, `smtp_secure`, `smtp_user`, `smtp_pass`, `created`, `updated`) VALUES
(1, 'dominguezf225@gmail.com', 'Sistema de Tickets', 'Normal', NULL, 1, 'smtp.gmail.com', 465, 'ssl', 'dominguezf225@gmail.com', 'uzlewbhpmzgzsbad', '2026-02-08 20:12:57', '2026-02-14 12:12:52'),
(2, 'cuenta9fran@gmail.com', 'Notificaciones', 'Normal', 1, 0, '', NULL, '', '', '', '2026-02-08 20:13:04', '2026-02-09 13:03:01'),
(5, 'info@vigitecpanama.com', 'Support', 'Normal', 1, 0, 'blizzard.mxrouting.net', 587, 'tls', 'info@vigitecpanama.com', 'Panama26**', '2026-02-09 17:36:34', '2026-02-12 11:45:42');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `help_topics`
--

CREATE TABLE `help_topics` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `is_active` tinyint(4) DEFAULT 1,
  `created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `help_topics`
--

INSERT INTO `help_topics` (`id`, `name`, `description`, `dept_id`, `is_active`, `created`) VALUES
(9, 'Visita tecnica', 'Visita del personal', 1, 1, '2026-02-14 12:01:36');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL COMMENT 'Acción realizada',
  `object_type` varchar(50) DEFAULT NULL COMMENT 'Tipo de objeto',
  `object_id` int(11) DEFAULT NULL COMMENT 'ID del objeto',
  `user_type` enum('user','staff') DEFAULT NULL COMMENT 'Tipo de usuario',
  `user_id` int(11) DEFAULT NULL COMMENT 'Usuario que realizó',
  `details` text DEFAULT NULL COMMENT 'Detalles de la acción',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP del usuario',
  `created` datetime DEFAULT current_timestamp() COMMENT 'Fecha del evento'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` enum('task_assigned','ticket_assigned','general') DEFAULT 'general',
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `organizations`
--

CREATE TABLE `organizations` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `phone_ext` varchar(20) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created` datetime DEFAULT current_timestamp(),
  `updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `priorities`
--

CREATE TABLE `priorities` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL COMMENT 'Nombre de prioridad',
  `level` int(11) DEFAULT 0 COMMENT 'Nivel numérico (1=bajo, 4=urgente)',
  `color` varchar(20) DEFAULT NULL COMMENT 'Color en hex'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `priorities`
--

INSERT INTO `priorities` (`id`, `name`, `level`, `color`) VALUES
(1, 'Baja', 1, '#3498db'),
(2, 'Normal', 2, '#2ecc71'),
(3, 'Alta', 3, '#f39c12'),
(4, 'Urgente', 4, '#e74c3c');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created` datetime DEFAULT current_timestamp(),
  `updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `name`, `is_enabled`, `created`, `updated`) VALUES
(1, 'admin', 1, '2026-02-09 12:35:45', '2026-02-09 12:35:45'),
(2, 'agent', 1, '2026-02-09 12:35:45', '2026-02-09 12:35:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `perm_key` varchar(120) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created` datetime DEFAULT current_timestamp(),
  `updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_name`, `perm_key`, `is_enabled`, `created`, `updated`) VALUES
(1, 'agent', 'ticket.assign', 1, '2026-02-09 21:22:40', '2026-02-09 21:22:40'),
(2, 'agent', 'ticket.close', 1, '2026-02-09 21:22:40', '2026-02-09 21:22:40'),
(3, 'agent', 'ticket.create', 1, '2026-02-09 21:22:40', '2026-02-09 21:22:40'),
(4, 'agent', 'ticket.delete', 1, '2026-02-09 21:22:40', '2026-02-09 21:22:40'),
(5, 'agent', 'ticket.edit', 1, '2026-02-09 21:22:40', '2026-02-09 21:22:40'),
(6, 'agent', 'ticket.edit_thread', 1, '2026-02-09 21:22:40', '2026-02-09 21:22:40'),
(7, 'agent', 'ticket.link', 1, '2026-02-09 21:22:40', '2026-02-09 21:22:40'),
(8, 'agent', 'ticket.markanswered', 1, '2026-02-09 21:22:40', '2026-02-09 21:22:40'),
(9, 'agent', 'ticket.merge', 1, '2026-02-09 21:22:40', '2026-02-09 21:22:40'),
(10, 'agent', 'ticket.reply', 1, '2026-02-09 21:22:40', '2026-02-09 21:22:40'),
(11, 'agent', 'ticket.refer', 1, '2026-02-09 21:22:40', '2026-02-09 21:22:40'),
(12, 'agent', 'ticket.post', 1, '2026-02-09 21:22:40', '2026-02-09 21:22:40'),
(13, 'agent', 'ticket.transfer', 1, '2026-02-09 21:22:40', '2026-02-09 21:22:40'),
(14, 'agent', 'task.create', 1, '2026-02-09 21:22:40', '2026-02-09 21:22:40'),
(15, 'agent', 'task.edit', 1, '2026-02-09 21:22:40', '2026-02-09 21:22:40'),
(16, 'agent', 'task.close', 1, '2026-02-09 21:22:40', '2026-02-09 21:22:40'),
(17, 'agent', 'task.assign', 1, '2026-02-09 21:22:40', '2026-02-09 21:22:40'),
(18, 'agent', 'task.delete', 1, '2026-02-09 21:22:40', '2026-02-09 21:22:40'),
(19, 'admin', 'ticket.assign', 1, '2026-02-09 21:23:02', '2026-02-09 21:23:02'),
(20, 'admin', 'ticket.close', 1, '2026-02-09 21:23:02', '2026-02-09 21:23:02'),
(21, 'admin', 'ticket.create', 1, '2026-02-09 21:23:02', '2026-02-09 21:23:02'),
(22, 'admin', 'ticket.delete', 1, '2026-02-09 21:23:02', '2026-02-09 21:23:02'),
(23, 'admin', 'ticket.edit', 1, '2026-02-09 21:23:02', '2026-02-09 21:23:02'),
(24, 'admin', 'ticket.edit_thread', 1, '2026-02-09 21:23:02', '2026-02-09 21:23:02'),
(25, 'admin', 'ticket.link', 1, '2026-02-09 21:23:02', '2026-02-09 21:23:02'),
(26, 'admin', 'ticket.markanswered', 1, '2026-02-09 21:23:02', '2026-02-09 21:23:02'),
(27, 'admin', 'ticket.merge', 1, '2026-02-09 21:23:02', '2026-02-09 21:23:02'),
(28, 'admin', 'ticket.reply', 1, '2026-02-09 21:23:02', '2026-02-09 21:23:02'),
(29, 'admin', 'ticket.refer', 1, '2026-02-09 21:23:02', '2026-02-09 21:23:02'),
(30, 'admin', 'ticket.post', 1, '2026-02-09 21:23:02', '2026-02-09 21:23:02'),
(31, 'admin', 'ticket.transfer', 1, '2026-02-09 21:23:02', '2026-02-09 21:23:02'),
(32, 'admin', 'task.create', 1, '2026-02-09 21:23:02', '2026-02-09 21:23:02'),
(33, 'admin', 'task.edit', 1, '2026-02-09 21:23:02', '2026-02-09 21:23:02'),
(34, 'admin', 'task.close', 1, '2026-02-09 21:23:02', '2026-02-09 21:23:02'),
(35, 'admin', 'task.assign', 1, '2026-02-09 21:23:02', '2026-02-09 21:23:02'),
(36, 'admin', 'task.delete', 1, '2026-02-09 21:23:02', '2026-02-09 21:23:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sequences`
--

CREATE TABLE `sequences` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `next` bigint(20) NOT NULL DEFAULT 1,
  `increment` int(11) NOT NULL DEFAULT 1,
  `padding` int(11) NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `updated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sequences`
--

INSERT INTO `sequences` (`id`, `name`, `next`, `increment`, `padding`, `created`, `updated`) VALUES
(4, 'tickets', 1, 1, 0, '2026-02-14 12:09:37', '2026-02-14 12:10:01'),
(6, 'Tareas', 1, 1, 0, '2026-02-14 12:10:30', '2026-02-14 12:10:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL COMMENT 'ID de sesión',
  `user_type` enum('user','staff') NOT NULL COMMENT 'Tipo de usuario',
  `user_id` int(11) DEFAULT NULL COMMENT 'ID del usuario',
  `data` longblob DEFAULT NULL COMMENT 'Datos de sesión',
  `created` datetime DEFAULT current_timestamp() COMMENT 'Creación',
  `expires` datetime DEFAULT NULL COMMENT 'Expiración',
  `last_activity` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Último movimiento'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL COMMENT 'Nombre de usuario único',
  `password` varchar(255) NOT NULL COMMENT 'Hash bcrypt de la contraseña',
  `email` varchar(255) NOT NULL COMMENT 'Email del agente',
  `firstname` varchar(100) NOT NULL COMMENT 'Primer nombre',
  `lastname` varchar(100) NOT NULL COMMENT 'Apellido',
  `dept_id` int(11) DEFAULT NULL COMMENT 'Departamento asignado',
  `role` enum('agent','supervisor','admin') DEFAULT 'agent' COMMENT 'Rol del agente',
  `is_active` tinyint(4) DEFAULT 1 COMMENT '1=activo, 0=inactivo',
  `created` datetime DEFAULT current_timestamp() COMMENT 'Fecha de creación',
  `updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Última actualización',
  `last_login` datetime DEFAULT NULL COMMENT 'Último acceso',
  `signature` text DEFAULT NULL COMMENT 'Firma opcional en respuestas'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `staff`
--

INSERT INTO `staff` (`id`, `username`, `password`, `email`, `firstname`, `lastname`, `dept_id`, `role`, `is_active`, `created`, `updated`, `last_login`, `signature`) VALUES
(1, 'admin', '$2y$12$HY3j2AKbDAv.Z2Mt9pRswODW6aZ1IGm3yysctj/1KcanAXRj5wEjq', 'cuenta8fran@gmail.com', 'Admin', 'System', 1, 'admin', 1, '2026-01-26 23:34:13', '2026-02-14 12:21:32', '2026-02-14 12:21:32', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `staff_login_attempts`
--

CREATE TABLE `staff_login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `updated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `staff_password_resets`
--

CREATE TABLE `staff_password_resets` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `dept_id` int(11) NOT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `due_date` datetime DEFAULT NULL,
  `created` datetime DEFAULT current_timestamp(),
  `updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `threads`
--

CREATE TABLE `threads` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL COMMENT 'Ticket asociado',
  `created` datetime DEFAULT current_timestamp() COMMENT 'Fecha creación'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `thread_entries`
--

CREATE TABLE `thread_entries` (
  `id` int(11) NOT NULL,
  `thread_id` int(11) NOT NULL COMMENT 'Conversación',
  `user_id` int(11) DEFAULT NULL COMMENT 'Usuario que escribió (NULL si es agente)',
  `staff_id` int(11) DEFAULT NULL COMMENT 'Agente que escribió (NULL si es usuario)',
  `body` longtext NOT NULL COMMENT 'Contenido del mensaje',
  `is_internal` tinyint(4) DEFAULT 0 COMMENT '1=nota interna (solo agentes)',
  `created` datetime DEFAULT current_timestamp() COMMENT 'Fecha creación',
  `updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Última edición'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `ticket_number` varchar(20) NOT NULL COMMENT 'Número visible (ej: ABC-20250126-001234)',
  `user_id` int(11) NOT NULL COMMENT 'Usuario que creó el ticket',
  `staff_id` int(11) DEFAULT NULL COMMENT 'Agente asignado (NULL si no asignado)',
  `dept_id` int(11) DEFAULT 1 COMMENT 'Departamento responsable',
  `status_id` int(11) DEFAULT 1 COMMENT 'Estado del ticket',
  `priority_id` int(11) DEFAULT 1 COMMENT 'Prioridad',
  `subject` varchar(255) NOT NULL COMMENT 'Asunto del ticket',
  `created` datetime DEFAULT current_timestamp() COMMENT 'Fecha creación',
  `updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Última actualización',
  `closed` datetime DEFAULT NULL COMMENT 'Fecha de cierre',
  `topic_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ticket_links`
--

CREATE TABLE `ticket_links` (
  `ticket_id` int(11) NOT NULL,
  `linked_ticket_id` int(11) NOT NULL,
  `created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ticket_status`
--

CREATE TABLE `ticket_status` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL COMMENT 'Nombre del estado',
  `color` varchar(20) DEFAULT NULL COMMENT 'Color en hex (ej: #3498db)',
  `icon` varchar(50) DEFAULT NULL COMMENT 'Ícono Font Awesome',
  `order_by` int(11) DEFAULT 0 COMMENT 'Orden de visualización'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ticket_status`
--

INSERT INTO `ticket_status` (`id`, `name`, `color`, `icon`, `order_by`) VALUES
(1, 'Abierto', '#3498db', 'fa-folder-open', 1),
(2, 'En Progreso', '#f39c12', 'fa-spinner', 2),
(3, 'Esperando Usuario', '#9b59b6', 'fa-hourglass', 3),
(4, 'Resuelto', '#27ae60', 'fa-check-circle', 4),
(5, 'Cerrado', '#95a5a6', 'fa-times-circle', 5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL COMMENT 'Email único del usuario',
  `password` varchar(255) NOT NULL COMMENT 'Hash bcrypt de la contraseña',
  `firstname` varchar(100) NOT NULL COMMENT 'Primer nombre',
  `lastname` varchar(100) NOT NULL COMMENT 'Apellido',
  `phone` varchar(20) DEFAULT NULL COMMENT 'Teléfono del contacto',
  `company` varchar(100) DEFAULT NULL COMMENT 'Empresa/Compañía',
  `status` enum('active','inactive','banned') DEFAULT 'active' COMMENT 'Estado del usuario',
  `created` datetime DEFAULT current_timestamp() COMMENT 'Fecha de creación',
  `updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Última actualización',
  `last_login` datetime DEFAULT NULL COMMENT 'Último acceso'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_login_attempts`
--

CREATE TABLE `user_login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `updated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_notes`
--

CREATE TABLE `user_notes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `note` text NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `updated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_dept_stats`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_dept_stats` (
`id` int(11)
,`name` varchar(100)
,`total_tickets` bigint(21)
,`open_tickets` decimal(22,0)
,`in_progress` decimal(22,0)
,`resolved` decimal(22,0)
,`closed` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_tickets_full`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_tickets_full` (
`id` int(11)
,`ticket_number` varchar(20)
,`subject` varchar(255)
,`user_first` varchar(100)
,`user_last` varchar(100)
,`user_email` varchar(255)
,`staff_name` varchar(201)
,`dept_name` varchar(100)
,`status_name` varchar(50)
,`priority_name` varchar(50)
,`created` datetime
,`updated` datetime
,`closed` datetime
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_unassigned_tickets`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_unassigned_tickets` (
`id` int(11)
,`ticket_number` varchar(20)
,`subject` varchar(255)
,`user_name` varchar(201)
,`dept_name` varchar(100)
,`status_name` varchar(50)
,`priority_name` varchar(50)
,`created` datetime
);

-- --------------------------------------------------------

--
-- Estructura para la vista `v_dept_stats`
--
DROP TABLE IF EXISTS `v_dept_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_dept_stats`  AS SELECT `d`.`id` AS `id`, `d`.`name` AS `name`, count(`t`.`id`) AS `total_tickets`, sum(case when `t`.`status_id` = 1 then 1 else 0 end) AS `open_tickets`, sum(case when `t`.`status_id` = 2 then 1 else 0 end) AS `in_progress`, sum(case when `t`.`status_id` = 4 then 1 else 0 end) AS `resolved`, sum(case when `t`.`status_id` = 5 then 1 else 0 end) AS `closed` FROM (`departments` `d` left join `tickets` `t` on(`d`.`id` = `t`.`dept_id`)) GROUP BY `d`.`id`, `d`.`name` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_tickets_full`
--
DROP TABLE IF EXISTS `v_tickets_full`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_tickets_full`  AS SELECT `t`.`id` AS `id`, `t`.`ticket_number` AS `ticket_number`, `t`.`subject` AS `subject`, `u`.`firstname` AS `user_first`, `u`.`lastname` AS `user_last`, `u`.`email` AS `user_email`, ifnull(concat(`s`.`firstname`,' ',`s`.`lastname`),'Sin asignar') AS `staff_name`, `d`.`name` AS `dept_name`, `ts`.`name` AS `status_name`, `p`.`name` AS `priority_name`, `t`.`created` AS `created`, `t`.`updated` AS `updated`, `t`.`closed` AS `closed` FROM (((((`tickets` `t` join `users` `u` on(`t`.`user_id` = `u`.`id`)) left join `staff` `s` on(`t`.`staff_id` = `s`.`id`)) join `departments` `d` on(`t`.`dept_id` = `d`.`id`)) join `ticket_status` `ts` on(`t`.`status_id` = `ts`.`id`)) join `priorities` `p` on(`t`.`priority_id` = `p`.`id`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_unassigned_tickets`
--
DROP TABLE IF EXISTS `v_unassigned_tickets`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_unassigned_tickets`  AS SELECT `t`.`id` AS `id`, `t`.`ticket_number` AS `ticket_number`, `t`.`subject` AS `subject`, concat(`u`.`firstname`,' ',`u`.`lastname`) AS `user_name`, `d`.`name` AS `dept_name`, `ts`.`name` AS `status_name`, `p`.`name` AS `priority_name`, `t`.`created` AS `created` FROM ((((`tickets` `t` join `users` `u` on(`t`.`user_id` = `u`.`id`)) join `departments` `d` on(`t`.`dept_id` = `d`.`id`)) join `ticket_status` `ts` on(`t`.`status_id` = `ts`.`id`)) join `priorities` `p` on(`t`.`priority_id` = `p`.`id`)) WHERE `t`.`staff_id` is null AND `t`.`status_id` <> 5 ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`key`);

--
-- Indices de la tabla `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_thread_entry_id` (`thread_entry_id`),
  ADD KEY `idx_created` (`created`);

--
-- Indices de la tabla `banlist`
--
ALTER TABLE `banlist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_domain` (`domain`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indices de la tabla `config`
--
ALTER TABLE `config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`),
  ADD KEY `idx_key` (`config_key`);

--
-- Indices de la tabla `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indices de la tabla `email_accounts`
--
ALTER TABLE `email_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_default` (`is_default`),
  ADD KEY `idx_dept` (`dept_id`);

--
-- Indices de la tabla `help_topics`
--
ALTER TABLE `help_topics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_dept_id` (`dept_id`);

--
-- Indices de la tabla `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created` (`created`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_object` (`object_type`,`object_id`);

--
-- Indices de la tabla `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_read` (`staff_id`,`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indices de la tabla `organizations`
--
ALTER TABLE `organizations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_name` (`name`);

--
-- Indices de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_token_hash` (`token_hash`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indices de la tabla `priorities`
--
ALTER TABLE `priorities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_level` (`level`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_name` (`name`);

--
-- Indices de la tabla `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_role_perm` (`role_name`,`perm_key`),
  ADD KEY `idx_role` (`role_name`);

--
-- Indices de la tabla `sequences`
--
ALTER TABLE `sequences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indices de la tabla `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expires` (`expires`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_user_type` (`user_type`);

--
-- Indices de la tabla `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_dept_id` (`dept_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indices de la tabla `staff_login_attempts`
--
ALTER TABLE `staff_login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_staff_login_attempts` (`username`,`ip`);

--
-- Indices de la tabla `staff_password_resets`
--
ALTER TABLE `staff_password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_id` (`staff_id`),
  ADD KEY `idx_token_hash` (`token_hash`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indices de la tabla `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `created_by` (`created_by`);

--
-- Indices de la tabla `threads`
--
ALTER TABLE `threads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ticket` (`ticket_id`),
  ADD KEY `idx_created` (`created`);

--
-- Indices de la tabla `thread_entries`
--
ALTER TABLE `thread_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_thread_id` (`thread_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_staff_id` (`staff_id`),
  ADD KEY `idx_created` (`created`),
  ADD KEY `idx_internal` (`is_internal`),
  ADD KEY `idx_thread_entries_thread` (`thread_id`,`created`);

--
-- Indices de la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `dept_id` (`dept_id`),
  ADD KEY `priority_id` (`priority_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_staff_id` (`staff_id`),
  ADD KEY `idx_status_id` (`status_id`),
  ADD KEY `idx_created` (`created`),
  ADD KEY `idx_ticket_number` (`ticket_number`),
  ADD KEY `idx_tickets_user_status` (`user_id`,`status_id`),
  ADD KEY `idx_tickets_staff_status` (`staff_id`,`status_id`),
  ADD KEY `idx_topic_id` (`topic_id`);

--
-- Indices de la tabla `ticket_links`
--
ALTER TABLE `ticket_links`
  ADD UNIQUE KEY `uq_ticket_link` (`ticket_id`,`linked_ticket_id`),
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_linked` (`linked_ticket_id`);

--
-- Indices de la tabla `ticket_status`
--
ALTER TABLE `ticket_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_name` (`name`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created`);

--
-- Indices de la tabla `user_login_attempts`
--
ALTER TABLE `user_login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_login_attempts` (`email`,`ip`);

--
-- Indices de la tabla `user_notes`
--
ALTER TABLE `user_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_created` (`user_id`,`created`),
  ADD KEY `idx_staff` (`staff_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `attachments`
--
ALTER TABLE `attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `banlist`
--
ALTER TABLE `banlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `config`
--
ALTER TABLE `config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `email_accounts`
--
ALTER TABLE `email_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `help_topics`
--
ALTER TABLE `help_topics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `organizations`
--
ALTER TABLE `organizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `priorities`
--
ALTER TABLE `priorities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=275;

--
-- AUTO_INCREMENT de la tabla `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT de la tabla `sequences`
--
ALTER TABLE `sequences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `staff_login_attempts`
--
ALTER TABLE `staff_login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `staff_password_resets`
--
ALTER TABLE `staff_password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `threads`
--
ALTER TABLE `threads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;

--
-- AUTO_INCREMENT de la tabla `thread_entries`
--
ALTER TABLE `thread_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=364;

--
-- AUTO_INCREMENT de la tabla `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=181;

--
-- AUTO_INCREMENT de la tabla `ticket_status`
--
ALTER TABLE `ticket_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `user_login_attempts`
--
ALTER TABLE `user_login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `user_notes`
--
ALTER TABLE `user_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `attachments`
--
ALTER TABLE `attachments`
  ADD CONSTRAINT `attachments_ibfk_1` FOREIGN KEY (`thread_entry_id`) REFERENCES `thread_entries` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `help_topics`
--
ALTER TABLE `help_topics`
  ADD CONSTRAINT `fk_help_topics_dept` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`id`);

--
-- Filtros para la tabla `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `staff_password_resets`
--
ALTER TABLE `staff_password_resets`
  ADD CONSTRAINT `staff_password_resets_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `staff` (`id`),
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`);

--
-- Filtros para la tabla `threads`
--
ALTER TABLE `threads`
  ADD CONSTRAINT `threads_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `thread_entries`
--
ALTER TABLE `thread_entries`
  ADD CONSTRAINT `thread_entries_ibfk_1` FOREIGN KEY (`thread_id`) REFERENCES `threads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `thread_entries_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `thread_entries_ibfk_3` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_tickets_topic` FOREIGN KEY (`topic_id`) REFERENCES `help_topics` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `tickets_ibfk_4` FOREIGN KEY (`status_id`) REFERENCES `ticket_status` (`id`),
  ADD CONSTRAINT `tickets_ibfk_5` FOREIGN KEY (`priority_id`) REFERENCES `priorities` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
