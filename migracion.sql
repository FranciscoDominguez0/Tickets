ALTER TABLE notifications 
MODIFY COLUMN type VARCHAR(50) DEFAULT 'general';


-- Corregir tabla de logs
ALTER TABLE logs 
MODIFY COLUMN user_type VARCHAR(50) NULL;
-- Corregir tabla de sesiones
ALTER TABLE sessions 
MODIFY COLUMN user_type VARCHAR(50) NULL;

UPDATE sequences SET next = 42 WHERE name = 'tickets' AND empresa_id = 1;

ALTER TABLE ticket_reports MODIFY COLUMN empresa_id INT NOT NULL DEFAULT 1;
ALTER TABLE ticket_report_items MODIFY COLUMN empresa_id INT NOT NULL DEFAULT 1;
