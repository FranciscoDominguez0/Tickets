CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL DEFAULT 1,
  `actor_type` varchar(50) NOT NULL COMMENT 'staff, super_admin, o user',
  `actor_id` int NOT NULL COMMENT 'ID del agente o cliente que realiza la acción',
  `action` varchar(100) NOT NULL COMMENT 'Tipo de acción (ej. ticket_created, user_deleted)',
  `object_type` varchar(50) DEFAULT NULL COMMENT 'Tipo de objeto afectado (ej. ticket, user)',
  `object_id` int DEFAULT NULL COMMENT 'ID del objeto afectado',
  `details` text COMMENT 'Detalles extendidos en texto plano (nombres, IDs, resumen)',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Dirección IP de quien realizó la acción',
  `created` datetime NOT NULL COMMENT 'Fecha y hora de la acción',
  PRIMARY KEY (`id`),
  KEY `empresa_id_idx` (`empresa_id`),
  KEY `actor_idx` (`actor_type`,`actor_id`),
  KEY `object_idx` (`object_type`,`object_id`),
  KEY `created_idx` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
