-- ============================================================================
-- SISTEMA DE TICKETS (LIMPIO)
-- Estructura + datos esenciales para montar el sistema desde cero
-- Incluye SOLO un usuario administrador (STAFF)
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
-- 11. TABLA: CONFIGURACIÓN (LEGACY)
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
-- 12. TABLA: app_settings (USADA POR LA APP)
-- ============================================================================
CREATE TABLE IF NOT EXISTS app_settings (
  `key` VARCHAR(191) NOT NULL,
  `value` LONGTEXT NULL,
  `updated` DATETIME NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- DATOS INICIALES (ESENCIALES)
-- ============================================================================

INSERT INTO departments (name, description, is_active) VALUES
('Soporte Técnico', 'Problemas técnicos y troubleshooting', 1),
('Ventas', 'Información sobre productos y presupuestos', 1),
('Facturación', 'Facturas, pagos y cobros', 1),
('Recursos Humanos', 'Consultas de personal', 1),
('General', 'Otros asuntos', 1);

INSERT INTO ticket_status (name, color, icon, order_by) VALUES
('Abierto', '#3498db', 'fa-folder-open', 1),
('En Progreso', '#f39c12', 'fa-spinner', 2),
('Esperando Usuario', '#9b59b6', 'fa-hourglass', 3),
('Resuelto', '#27ae60', 'fa-check-circle', 4),
('Cerrado', '#95a5a6', 'fa-times-circle', 5);

INSERT INTO priorities (name, level, color) VALUES
('Baja', 1, '#3498db'),
('Normal', 2, '#2ecc71'),
('Alta', 3, '#f39c12'),
('Urgente', 4, '#e74c3c');

-- ============================================================================
-- ADMIN ÚNICO (STAFF)
-- correo: dominguezf225@gmail.com
-- clave: vigitec2026
-- Hash bcrypt (PHP password_hash): $2y$12$CZTZiYaol0oH7h95J8QH8uwhVA00wYoQb2L9e/ymY9zC8/NtXnNK2
-- ============================================================================
INSERT INTO staff (username, password, email, firstname, lastname, dept_id, role, is_active, created)
VALUES ('admin', '$2y$12$CZTZiYaol0oH7h95J8QH8uwhVA00wYoQb2L9e/ymY9zC8/NtXnNK2', 'dominguezf225@gmail.com', 'Admin', 'System', 1, 'admin', 1, NOW());

-- Índices útiles
CREATE INDEX idx_tickets_user_status ON tickets(user_id, status_id);
CREATE INDEX idx_tickets_staff_status ON tickets(staff_id, status_id);
CREATE INDEX idx_thread_entries_thread ON thread_entries(thread_id, created);
