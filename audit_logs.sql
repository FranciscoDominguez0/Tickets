DROP TABLE IF EXISTS `audit_logs`;

CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL DEFAULT 1,
  `actor_type` varchar(50) NOT NULL,
  `actor_id` int NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `empresa_id_idx` (`empresa_id`),
  KEY `actor_idx` (`actor_type`,`actor_id`),
  KEY `entity_idx` (`entity_type`,`entity_id`),
  KEY `created_at_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
