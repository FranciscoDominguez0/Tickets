-- Crear tabla para rastrear tickets "vistos" por cada agente en la sección de reportes
CREATE TABLE IF NOT EXISTS staff_reports_seen (
    staff_id INT NOT NULL,
    ticket_id INT NOT NULL,
    seen_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (staff_id, ticket_id),
    KEY idx_ticket_id (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
