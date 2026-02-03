-- ============================================================================
-- TABLA: TASKS (TAREAS)
-- ============================================================================
CREATE TABLE IF NOT EXISTS tasks (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL COMMENT 'Título de la tarea',
  description LONGTEXT COMMENT 'Descripción detallada',
  status ENUM('pending','in_progress','completed','cancelled') DEFAULT 'pending' COMMENT 'Estado de la tarea',
  priority ENUM('low','normal','high','urgent') DEFAULT 'normal' COMMENT 'Prioridad',
  assigned_to INT COMMENT 'Agente asignado (NULL si no asignado)',
  created_by INT NOT NULL COMMENT 'Agente que creó la tarea',
  created DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha creación',
  updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última actualización',
  due_date DATETIME COMMENT 'Fecha límite',
  
  FOREIGN KEY (assigned_to) REFERENCES staff(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE CASCADE,
  
  KEY idx_status (status),
  KEY idx_assigned_to (assigned_to),
  KEY idx_created_by (created_by),
  KEY idx_created (created),
  KEY idx_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DATOS DE EJEMPLO PARA TASKS
-- ============================================================================
INSERT INTO tasks (title, description, status, priority, assigned_to, created_by, due_date) VALUES
('Revisar tickets pendientes', 'Revisar y clasificar los tickets que han llegado en las últimas 24 horas.', 'pending', 'normal', 2, 1, DATE_ADD(NOW(), INTERVAL 1 DAY)),
('Actualizar base de conocimientos', 'Agregar nuevas soluciones a la base de conocimientos para problemas comunes.', 'in_progress', 'high', 3, 1, DATE_ADD(NOW(), INTERVAL 3 DAY)),
('Configurar backup automático', 'Implementar sistema de backup automático para la base de datos.', 'completed', 'urgent', 2, 1, NOW()),
('Entrenar nuevo agente', 'Realizar capacitación para el nuevo miembro del equipo de soporte.', 'pending', 'low', NULL, 1, DATE_ADD(NOW(), INTERVAL 7 DAY));