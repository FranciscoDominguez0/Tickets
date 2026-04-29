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

