CREATE TABLE IF NOT EXISTS ticket_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    requested_by_staff_id INT NOT NULL, 
    manager_id INT NULL, 
    status ENUM('pending', 'aprobar_bajo_aprobacion', 'aprobar_solo') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL
);

ALTER TABLE tickets ADD COLUMN IF NOT EXISTS support_start DATETIME NULL;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS support_end DATETIME NULL;
