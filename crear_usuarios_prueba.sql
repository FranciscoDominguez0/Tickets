-- ============================================================================
-- CREAR USUARIOS DE PRUEBA
-- Un cliente y un agente para probar el sistema
-- ============================================================================

USE tickets_db;

-- ============================================================================
-- 1. CREAR UN CLIENTE (USUARIO)
-- ============================================================================
-- Email: cliente@example.com
-- Contraseña: cliente123

INSERT INTO users (email, password, firstname, lastname, company, status, created) 
VALUES (
  'cliente@example.com',
  '$2y$10$G2APFipEEn5B04Umsxgktea.acHyhA6ceiZbSXmP0o3bdvNxJk2cm', -- bcrypt de "cliente123"
  'Juan',
  'Cliente',
  'Acme Corp',
  'active',
  NOW()
);

-- ============================================================================
-- 2. CREAR UN AGENTE (STAFF)
-- ============================================================================
-- Usuario: admin
-- Contraseña: admin123

-- Primero asegurarse de que existe el departamento con id=1
INSERT INTO departments (id, name, description, is_active) 
VALUES (1, 'Soporte Técnico', 'Departamento de soporte', 1)
ON DUPLICATE KEY UPDATE name=name;

-- Crear el agente
INSERT INTO staff (username, password, email, firstname, lastname, dept_id, role, is_active, created) 
VALUES (
  'admin',
  '$2y$10$5YWqOkYHOwHji4spST4BzO0rstt4J9ENHyEAMPVuVbQ.KjVCS8dQS', -- bcrypt de "admin123"
  'admin@company.com',
  'Admin',
  'System',
  1,
  'admin',
  1,
  NOW()
);

-- ============================================================================
-- CREDENCIALES DE PRUEBA:
-- ============================================================================
-- CLIENTE:
--   Email: cliente@example.com
--   Contraseña: cliente123
--
-- AGENTE:
--   Usuario: admin
--   Contraseña: admin123
-- ============================================================================
--
-- Si los hashes no funcionan, genera nuevos con:
-- php -r "echo password_hash('cliente123', PASSWORD_BCRYPT);"
-- php -r "echo password_hash('admin123', PASSWORD_BCRYPT);"
-- ============================================================================
