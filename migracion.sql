UPDATE ticket_status SET name = 'En Camino', color = '#e67e22', icon = 'fa-truck' WHERE id = 2;
UPDATE ticket_status SET name = 'En Proceso', color = '#f1c40f', icon = 'fa-cogs' WHERE id = 3;

ALTER TABLE `departments` ADD COLUMN `requires_report` TINYINT(1) NOT NULL DEFAULT 0;
