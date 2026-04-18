UPDATE ticket_status SET name = 'En Camino', color = '#e67e22', icon = 'fa-truck' WHERE id = 2;
UPDATE ticket_status SET name = 'En Proceso', color = '#f1c40f', icon = 'fa-cogs' WHERE id = 3;

ALTER TABLE `departments` ADD COLUMN `requires_report` TINYINT(1) NOT NULL DEFAULT 0;

-- Creación de tablas para el módulo de Reportes de Tickets Cerrados
CREATE TABLE IF NOT EXISTS `ticket_reports` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) unsigned NOT NULL,
  `work_description` text NOT NULL,
  `observations` text DEFAULT NULL,
  `final_price` varchar(50) DEFAULT NULL,
  `created_by` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ticket_id` (`ticket_id`),
  CONSTRAINT `fk_report_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ticket_report_materials` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` int(11) unsigned NOT NULL,
  `material_name` varchar(255) NOT NULL,
  `quantity` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_report_id` (`report_id`),
  CONSTRAINT `fk_material_report` FOREIGN KEY (`report_id`) REFERENCES `ticket_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
