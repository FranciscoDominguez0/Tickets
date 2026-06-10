-- Ejecutar este script en la base de datos para crear la estructura del módulo de Cotizaciones

DROP TABLE IF EXISTS `quotes`;
CREATE TABLE IF NOT EXISTS `quotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `org_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `amount` decimal(10,2) DEFAULT '0.00',
  `status` enum('draft','pending','requested','answered','accepted','rejected') NOT NULL DEFAULT 'pending',
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `empresa_id` (`empresa_id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `org_id` (`org_id`),
  KEY `staff_id` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `quote_messages`;
CREATE TABLE IF NOT EXISTS `quote_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quote_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `quote_id` (`quote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Asegurarse de que exista el rol o permiso si se usa un sistema estricto, 
-- aunque el código PHP asumirá validación básica si no se agregan permisos específicos a 'role_permissions'.
