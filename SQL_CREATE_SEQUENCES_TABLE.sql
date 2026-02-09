-- Tabla para gestionar secuencias de números de ticket
CREATE TABLE IF NOT EXISTS `sequences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `next` bigint(20) NOT NULL DEFAULT 1,
  `increment` int(11) NOT NULL DEFAULT 1,
  `padding` int(11) NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `updated` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar secuencia por defecto "General Tickets"
INSERT INTO `sequences` (`name`, `next`, `increment`, `padding`, `created`) 
VALUES ('General Tickets', 1, 1, 0, NOW())
ON DUPLICATE KEY UPDATE `name` = `name`;
