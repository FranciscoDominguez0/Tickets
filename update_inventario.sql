-- Agregar columna para vincular requisición con ticket
ALTER TABLE requisitions
ADD COLUMN ticket_id INT DEFAULT NULL AFTER id;

-- Agregar columna para reportar cantidad utilizada
ALTER TABLE requisition_items
ADD COLUMN quantity_used INT DEFAULT NULL AFTER quantity;
