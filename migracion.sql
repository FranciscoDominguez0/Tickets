ALTER TABLE ticket_reports 
MODIFY COLUMN billing_status VARCHAR(50) DEFAULT 'pending';


CREATE TABLE IF NOT EXISTS ticket_deletion_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    empresa_id INT NOT NULL,
    ticket_number VARCHAR(100) NOT NULL,
    ticket_subject VARCHAR(255) NOT NULL,
    requested_by INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    resolved_by INT NULL,
    INDEX (empresa_id),
    INDEX (status)
);

ALTER TABLE users ADD COLUMN address TEXT NULL AFTER email;

-- Estructura de la tabla para el rastreo de agentes en tiempo real
CREATE TABLE IF NOT EXISTS `staff_locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,                   -- ID del agente (staff)
  `lat` decimal(10,8) NOT NULL,                  -- Latitud de la ubicación
  `lng` decimal(11,8) NOT NULL,                  -- Longitud de la ubicación
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Última actualización
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff` (`staff_id`),            -- Garantiza un único registro por agente
  KEY `idx_updated` (`updated_at`)               -- Índice para búsquedas por tiempo
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Migración para el sistema de firma remota de clientes
ALTER TABLE `tickets` 
    ADD COLUMN IF NOT EXISTS `signature_token` VARCHAR(64) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `signature_requested` TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `client_signature` VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `close_message` TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `closed_at` DATETIME DEFAULT NULL;

-- Índices recomendados para optimizar la búsqueda por token
CREATE INDEX IF NOT EXISTS `idx_signature_token` ON `tickets` (`signature_token`);

ALTER TABLE `users` 
ADD COLUMN `latitude` DECIMAL(10,8) NULL DEFAULT NULL AFTER `address`,
ADD COLUMN `longitude` DECIMAL(11,8) NULL DEFAULT NULL AFTER `latitude`;
