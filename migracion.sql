-- 1. Modificar la columna ENUM para que acepte los nuevos valores
ALTER TABLE ticket_approvals 
MODIFY COLUMN status ENUM('pending', 'cotizacion', 'aprobado', 'rechazado') DEFAULT 'pending';

-- 2. (Opcional pero recomendado) Actualizar cualquier registro viejo que tuviera los estados anteriores
UPDATE ticket_approvals SET status = 'cotizacion' WHERE status = 'aprobar_bajo_aprobacion';
UPDATE ticket_approvals SET status = 'aprobado' WHERE status = 'aprobar_solo';


ALTER TABLE tickets
ADD COLUMN support_start DATETIME NULL;

ALTER TABLE tickets
ADD COLUMN support_end DATETIME NULL;