-- =============================================================
-- Migración: Mapa de agentes en tiempo real
-- Ejecutar en la base de datos tickets_db
-- =============================================================

-- 1. Tabla de ubicaciones de agentes
CREATE TABLE IF NOT EXISTS ubicaciones_agentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    empresa_id INT NOT NULL DEFAULT 1,
    latitud DECIMAL(10, 7) NOT NULL,
    longitud DECIMAL(10, 7) NOT NULL,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_staff_empresa (staff_id, empresa_id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_fecha (fecha_actualizacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Estado "En Camino" en ticket_status (si no existe)
INSERT INTO ticket_status (name, color, icon, order_by)
SELECT 'En Camino', '#f59e0b', 'fa-truck', 5
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM ticket_status WHERE name = 'En Camino');
