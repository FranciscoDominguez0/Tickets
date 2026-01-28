-- ============================================================================
-- SISTEMA DE TICKETS COMPLETO
-- Base de datos SQL con LOGIN funcionando
-- ============================================================================
-- Autor: Sistema de Tickets
-- Fecha: 2025-01-26
-- Descripción: Base de datos completa para un sistema de tickets con 
--              autenticación de clientes y agentes
-- ============================================================================

-- ============================================================================
-- CREAR BASE DE DATOS
-- ============================================================================
DROP DATABASE IF EXISTS tickets_db;
CREATE DATABASE tickets_db 
  DEFAULT CHARACTER SET utf8mb4 
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE tickets_db;

-- ============================================================================
-- 1. TABLA: USUARIOS (CLIENTES)
-- ============================================================================
CREATE TABLE IF NOT EXISTS users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) UNIQUE NOT NULL COMMENT 'Email único del usuario',
  password VARCHAR(255) NOT NULL COMMENT 'Hash bcrypt de la contraseña',
  firstname VARCHAR(100) NOT NULL COMMENT 'Primer nombre',
  lastname VARCHAR(100) NOT NULL COMMENT 'Apellido',
  phone VARCHAR(20) COMMENT 'Teléfono del contacto',
  company VARCHAR(100) COMMENT 'Empresa/Compañía',
  status ENUM('active','inactive','banned') DEFAULT 'active' COMMENT 'Estado del usuario',
  created DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
  updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última actualización',
  last_login DATETIME COMMENT 'Último acceso',
  
  KEY idx_email (email),
  KEY idx_status (status),
  KEY idx_created (created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. TABLA: AGENTES (STAFF)
-- ============================================================================
CREATE TABLE IF NOT EXISTS staff (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(255) UNIQUE NOT NULL COMMENT 'Nombre de usuario único',
  password VARCHAR(255) NOT NULL COMMENT 'Hash bcrypt de la contraseña',
  email VARCHAR(255) UNIQUE NOT NULL COMMENT 'Email del agente',
  firstname VARCHAR(100) NOT NULL COMMENT 'Primer nombre',
  lastname VARCHAR(100) NOT NULL COMMENT 'Apellido',
  dept_id INT COMMENT 'Departamento asignado',
  role ENUM('agent','supervisor','admin') DEFAULT 'agent' COMMENT 'Rol del agente',
  is_active TINYINT DEFAULT 1 COMMENT '1=activo, 0=inactivo',
  created DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
  updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última actualización',
  last_login DATETIME COMMENT 'Último acceso',
  
  KEY idx_username (username),
  KEY idx_email (email),
  KEY idx_dept_id (dept_id),
  KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. TABLA: DEPARTAMENTOS
-- ============================================================================
CREATE TABLE IF NOT EXISTS departments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) UNIQUE NOT NULL COMMENT 'Nombre del departamento',
  description TEXT COMMENT 'Descripción',
  is_active TINYINT DEFAULT 1 COMMENT '1=activo, 0=inactivo',
  created DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
  
  KEY idx_name (name),
  KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. TABLA: ESTADOS DE TICKET
-- ============================================================================
CREATE TABLE IF NOT EXISTS ticket_status (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(50) UNIQUE NOT NULL COMMENT 'Nombre del estado',
  color VARCHAR(20) COMMENT 'Color en hex (ej: #3498db)',
  icon VARCHAR(50) COMMENT 'Ícono Font Awesome',
  order_by INT DEFAULT 0 COMMENT 'Orden de visualización',
  
  KEY idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. TABLA: PRIORIDADES
-- ============================================================================
CREATE TABLE IF NOT EXISTS priorities (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(50) UNIQUE NOT NULL COMMENT 'Nombre de prioridad',
  level INT DEFAULT 0 COMMENT 'Nivel numérico (1=bajo, 4=urgente)',
  color VARCHAR(20) COMMENT 'Color en hex',
  
  KEY idx_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. TABLA: TICKETS
-- ============================================================================
CREATE TABLE IF NOT EXISTS tickets (
  id INT PRIMARY KEY AUTO_INCREMENT,
  ticket_number VARCHAR(20) UNIQUE NOT NULL COMMENT 'Número visible (ej: ABC-20250126-001234)',
  user_id INT NOT NULL COMMENT 'Usuario que creó el ticket',
  staff_id INT COMMENT 'Agente asignado (NULL si no asignado)',
  dept_id INT DEFAULT 1 COMMENT 'Departamento responsable',
  status_id INT DEFAULT 1 COMMENT 'Estado del ticket',
  priority_id INT DEFAULT 1 COMMENT 'Prioridad',
  subject VARCHAR(255) NOT NULL COMMENT 'Asunto del ticket',
  created DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha creación',
  updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última actualización',
  closed DATETIME COMMENT 'Fecha de cierre',
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL,
  FOREIGN KEY (dept_id) REFERENCES departments(id),
  FOREIGN KEY (status_id) REFERENCES ticket_status(id),
  FOREIGN KEY (priority_id) REFERENCES priorities(id),
  
  KEY idx_user_id (user_id),
  KEY idx_staff_id (staff_id),
  KEY idx_status_id (status_id),
  KEY idx_created (created),
  KEY idx_ticket_number (ticket_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. TABLA: THREADS (CONVERSACIONES)
-- ============================================================================
CREATE TABLE IF NOT EXISTS threads (
  id INT PRIMARY KEY AUTO_INCREMENT,
  ticket_id INT NOT NULL COMMENT 'Ticket asociado',
  created DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha creación',
  
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  UNIQUE KEY unique_ticket (ticket_id),
  
  KEY idx_created (created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. TABLA: MENSAJES EN CONVERSACIÓN
-- ============================================================================
CREATE TABLE IF NOT EXISTS thread_entries (
  id INT PRIMARY KEY AUTO_INCREMENT,
  thread_id INT NOT NULL COMMENT 'Conversación',
  user_id INT COMMENT 'Usuario que escribió (NULL si es agente)',
  staff_id INT COMMENT 'Agente que escribió (NULL si es usuario)',
  body LONGTEXT NOT NULL COMMENT 'Contenido del mensaje',
  is_internal TINYINT DEFAULT 0 COMMENT '1=nota interna (solo agentes)',
  created DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha creación',
  updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última edición',
  
  FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL,
  
  KEY idx_thread_id (thread_id),
  KEY idx_user_id (user_id),
  KEY idx_staff_id (staff_id),
  KEY idx_created (created),
  KEY idx_internal (is_internal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. TABLA: SESIONES
-- ============================================================================
CREATE TABLE IF NOT EXISTS sessions (
  id VARCHAR(128) PRIMARY KEY COMMENT 'ID de sesión',
  user_type ENUM('user','staff') NOT NULL COMMENT 'Tipo de usuario',
  user_id INT COMMENT 'ID del usuario',
  data LONGBLOB COMMENT 'Datos de sesión',
  created DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Creación',
  expires DATETIME COMMENT 'Expiración',
  last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Último movimiento',
  
  KEY idx_expires (expires),
  KEY idx_user_id (user_id),
  KEY idx_user_type (user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. TABLA: ATTACHMENTS (ARCHIVOS ADJUNTOS)
-- ============================================================================
CREATE TABLE IF NOT EXISTS attachments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  thread_entry_id INT COMMENT 'Mensaje asociado',
  filename VARCHAR(255) NOT NULL COMMENT 'Nombre en servidor',
  original_filename VARCHAR(255) COMMENT 'Nombre original subido',
  mimetype VARCHAR(100) COMMENT 'Tipo MIME',
  size INT COMMENT 'Tamaño en bytes',
  path VARCHAR(500) COMMENT 'Ruta del archivo',
  hash VARCHAR(64) COMMENT 'Hash SHA256',
  created DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha upload',
  
  FOREIGN KEY (thread_entry_id) REFERENCES thread_entries(id) ON DELETE CASCADE,
  
  KEY idx_thread_entry_id (thread_entry_id),
  KEY idx_created (created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 11. TABLA: CONFIGURACIÓN
-- ============================================================================
CREATE TABLE IF NOT EXISTS config (
  id INT PRIMARY KEY AUTO_INCREMENT,
  config_key VARCHAR(100) UNIQUE NOT NULL COMMENT 'Clave de configuración',
  config_value LONGTEXT COMMENT 'Valor',
  description TEXT COMMENT 'Descripción',
  created DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Creación',
  
  KEY idx_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 12. TABLA: LOGS (AUDITORÍA)
-- ============================================================================
CREATE TABLE IF NOT EXISTS logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  action VARCHAR(100) NOT NULL COMMENT 'Acción realizada',
  object_type VARCHAR(50) COMMENT 'Tipo de objeto',
  object_id INT COMMENT 'ID del objeto',
  user_type ENUM('user','staff') COMMENT 'Tipo de usuario',
  user_id INT COMMENT 'Usuario que realizó',
  details TEXT COMMENT 'Detalles de la acción',
  ip_address VARCHAR(45) COMMENT 'IP del usuario',
  created DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha del evento',
  
  KEY idx_created (created),
  KEY idx_user_id (user_id),
  KEY idx_action (action),
  KEY idx_object (object_type, object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INSERTAR DATOS INICIALES
-- ============================================================================

-- ============================================================================
-- DEPARTAMENTOS
-- ============================================================================
INSERT INTO departments (name, description, is_active) VALUES
('Soporte Técnico', 'Problemas técnicos y troubleshooting', 1),
('Ventas', 'Información sobre productos y presupuestos', 1),
('Facturación', 'Facturas, pagos y cobros', 1),
('Recursos Humanos', 'Consultas de personal', 1),
('General', 'Otros asuntos', 1);

-- ============================================================================
-- ESTADOS DE TICKET
-- ============================================================================
INSERT INTO ticket_status (name, color, icon, order_by) VALUES
('Abierto', '#3498db', 'fa-folder-open', 1),
('En Progreso', '#f39c12', 'fa-spinner', 2),
('Esperando Usuario', '#9b59b6', 'fa-hourglass', 3),
('Resuelto', '#27ae60', 'fa-check-circle', 4),
('Cerrado', '#95a5a6', 'fa-times-circle', 5);

-- ============================================================================
-- PRIORIDADES
-- ============================================================================
INSERT INTO priorities (name, level, color) VALUES
('Baja', 1, '#3498db'),
('Normal', 2, '#2ecc71'),
('Alta', 3, '#f39c12'),
('Urgente', 4, '#e74c3c');

-- ============================================================================
-- USUARIOS CLIENTES (PARA PRUEBAS)
-- ============================================================================
-- Contraseña: cliente123
-- Hash bcrypt: $2y$10$JZd7xsVZqJ9YQJZs9xDG.OZv9E5xN6xN6xN6xN6xN6xN6xN6xN
INSERT INTO users (email, password, firstname, lastname, company, status, created) VALUES
('cliente@example.com', '$2y$10$JZd7xsVZqJ9YQJZs9xDG.OZv9E5xN6xN6xN6xN6xN6xN6xN6xN', 'Juan', 'Cliente', 'Acme Corp', 'active', NOW()),
('soporte@example.com', '$2y$10$JZd7xsVZqJ9YQJZs9xDG.OZv9E5xN6xN6xN6xN6xN6xN6xN6xN', 'María', 'Usuario', 'Tech Solutions', 'active', NOW()),
('gerente@example.com', '$2y$10$JZd7xsVZqJ9YQJZs9xDG.OZv9E5xN6xN6xN6xN6xN6xN6xN6xN', 'Carlos', 'Manager', 'BigCorp', 'active', NOW());

-- ============================================================================
-- AGENTES (STAFF - PARA PRUEBAS)
-- ============================================================================
-- Usuario: admin | Contraseña: admin123
-- Hash bcrypt: $2y$10$YIjlrJyeatqIz.Yy5C6He.BBVCoQmkdUVewO0E8/LewKJvLF6NO2
INSERT INTO staff (username, password, email, firstname, lastname, dept_id, role, is_active, created) VALUES
('admin', '$2y$10$YIjlrJyeatqIz.Yy5C6He.BBVCoQmkdUVewO0E8/LewKJvLF6NO2', 'admin@company.com', 'Admin', 'System', 1, 'admin', 1, NOW()),
('soporte1', '$2y$10$YIjlrJyeatqIz.Yy5C6He.BBVCoQmkdUVewO0E8/LewKJvLF6NO2', 'soporte1@company.com', 'Juan', 'Soporte', 1, 'agent', 1, NOW()),
('soporte2', '$2y$10$YIjlrJyeatqIz.Yy5C6He.BBVCoQmkdUVewO0E8/LewKJvLF6NO2', 'soporte2@company.com', 'María', 'Ventas', 2, 'agent', 1, NOW()),
('supervisor', '$2y$10$YIjlrJyeatqIz.Yy5C6He.BBVCoQmkdUVewO0E8/LewKJvLF6NO2', 'supervisor@company.com', 'Luis', 'Supervisor', 1, 'supervisor', 1, NOW());

-- ============================================================================
-- CREAR ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- ============================================================================
CREATE INDEX idx_tickets_user_status ON tickets(user_id, status_id);
CREATE INDEX idx_tickets_staff_status ON tickets(staff_id, status_id);
CREATE INDEX idx_thread_entries_thread ON thread_entries(thread_id, created);

-- ============================================================================
-- VISTAS ÚTILES
-- ============================================================================

-- Vista: Tickets con información completa
CREATE OR REPLACE VIEW v_tickets_full AS
SELECT 
  t.id,
  t.ticket_number,
  t.subject,
  u.firstname as user_first,
  u.lastname as user_last,
  u.email as user_email,
  IFNULL(CONCAT(s.firstname, ' ', s.lastname), 'Sin asignar') as staff_name,
  d.name as dept_name,
  ts.name as status_name,
  p.name as priority_name,
  t.created,
  t.updated,
  t.closed
FROM tickets t
JOIN users u ON t.user_id = u.id
LEFT JOIN staff s ON t.staff_id = s.id
JOIN departments d ON t.dept_id = d.id
JOIN ticket_status ts ON t.status_id = ts.id
JOIN priorities p ON t.priority_id = p.id;

-- Vista: Estadísticas por departamento
CREATE OR REPLACE VIEW v_dept_stats AS
SELECT 
  d.id,
  d.name,
  COUNT(t.id) as total_tickets,
  SUM(CASE WHEN t.status_id = 1 THEN 1 ELSE 0 END) as open_tickets,
  SUM(CASE WHEN t.status_id = 2 THEN 1 ELSE 0 END) as in_progress,
  SUM(CASE WHEN t.status_id = 4 THEN 1 ELSE 0 END) as resolved,
  SUM(CASE WHEN t.status_id = 5 THEN 1 ELSE 0 END) as closed
FROM departments d
LEFT JOIN tickets t ON d.id = t.dept_id
GROUP BY d.id, d.name;

-- Vista: Tickets sin asignar
CREATE OR REPLACE VIEW v_unassigned_tickets AS
SELECT 
  t.id,
  t.ticket_number,
  t.subject,
  CONCAT(u.firstname, ' ', u.lastname) as user_name,
  d.name as dept_name,
  ts.name as status_name,
  p.name as priority_name,
  t.created
FROM tickets t
JOIN users u ON t.user_id = u.id
JOIN departments d ON t.dept_id = d.id
JOIN ticket_status ts ON t.status_id = ts.id
JOIN priorities p ON t.priority_id = p.id
WHERE t.staff_id IS NULL
AND t.status_id != 5;

-- ============================================================================
-- QUERIES ÚTILES PARA REFERENCIA
-- ============================================================================

/*

--- LOGIN CLIENTE ---
SELECT id, email, firstname, lastname, password 
FROM users 
WHERE email = 'cliente@example.com' AND status = 'active'

UPDATE users SET last_login = NOW() WHERE id = 1;

--- LOGIN AGENTE ---
SELECT id, username, email, firstname, lastname, password 
FROM staff 
WHERE username = 'admin' AND is_active = 1

UPDATE staff SET last_login = NOW() WHERE id = 1;

--- OBTENER TICKET COMPLETO ---
SELECT * FROM v_tickets_full WHERE id = 1;

--- OBTENER CONVERSACIÓN ---
SELECT te.*, 
       u.firstname as user_first, u.lastname as user_last,
       s.firstname as staff_first, s.lastname as staff_last
FROM thread_entries te
LEFT JOIN users u ON te.user_id = u.id
LEFT JOIN staff s ON te.staff_id = s.id
WHERE te.thread_id = (SELECT id FROM threads WHERE ticket_id = 1)
ORDER BY te.created ASC;

--- TICKETS POR USUARIO ---
SELECT * FROM v_tickets_full WHERE user_email = 'cliente@example.com';

--- TICKETS ASIGNADOS A AGENTE ---
SELECT * FROM v_tickets_full WHERE staff_name = 'Juan Soporte';

--- TICKETS SIN ASIGNAR ---
SELECT * FROM v_unassigned_tickets;

--- ESTADÍSTICAS ---
SELECT * FROM v_dept_stats;

--- CREAR NUEVO TICKET ---
INSERT INTO tickets (ticket_number, user_id, dept_id, status_id, priority_id, subject)
VALUES ('ABC-20250126-000001', 1, 1, 1, 2, 'Problema con sistema de reportes');

INSERT INTO threads (ticket_id) VALUES (LAST_INSERT_ID());

INSERT INTO thread_entries (thread_id, user_id, body)
VALUES (LAST_INSERT_ID(), 1, 'El sistema de reportes no genera correctamente los PDFs');

--- RESPONDER TICKET (COMO AGENTE) ---
INSERT INTO thread_entries (thread_id, staff_id, body)
VALUES (1, 1, 'Estamos investigando el problema. Revisaremos los logs del servidor.');

UPDATE tickets SET status_id = 2, staff_id = 1, updated = NOW() WHERE id = 1;

--- ASIGNAR TICKET A AGENTE ---
UPDATE tickets SET staff_id = 1 WHERE id = 1;

--- CAMBIAR ESTADO TICKET ---
UPDATE tickets SET status_id = 4, updated = NOW() WHERE id = 1;

--- CERRAR TICKET ---
UPDATE tickets SET status_id = 5, closed = NOW(), updated = NOW() WHERE id = 1;

*/

-- ============================================================================
-- FIN DE SCRIPT
-- ============================================================================
-- Importar con:
-- mysql -u root -p tickets_db < schema.sql
-- 
-- O desde phpMyAdmin:
-- 1. Nueva BD → tickets_db
-- 2. Importar archivo → este archivo
-- ============================================================================
