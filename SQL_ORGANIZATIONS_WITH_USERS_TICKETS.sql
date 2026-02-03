-- ============================================================================
-- SQL PARA INSERTAR ORGANIZACIONES CON USUARIOS Y TICKETS VINCULADOS
-- Ejecutar en tu base de datos (tickets_db o la que uses)
-- ============================================================================

USE tickets_db;

-- 1) Insertar organizaciones (si no existen)
INSERT IGNORE INTO organizations (name, address, phone, phone_ext, website, notes) VALUES
('Empresa ABC S.A.', 'Av. Principal 123, Ciudad', '600-0001', '101', 'https://www.empresaabc.com', 'Cliente preferente'),
('TecnoSoluciones', 'Calle Tecnología 45', '600-0002', NULL, 'https://www.tecnosoluciones.com', NULL),
('Servicios Generales López', 'Zona Industrial, Bodega 7', '600-0003', '200', NULL, 'Notas internas aquí'),
('Francisco', 'COCLE, AGUADULCE CABECERA', '67298084', NULL, NULL, 'hols');

-- 2) Obtener IDs necesarios
SET @org1_id = (SELECT id FROM organizations WHERE name = 'Empresa ABC S.A.' LIMIT 1);
SET @org2_id = (SELECT id FROM organizations WHERE name = 'TecnoSoluciones' LIMIT 1);
SET @org3_id = (SELECT id FROM organizations WHERE name = 'Servicios Generales López' LIMIT 1);
SET @org4_id = (SELECT id FROM organizations WHERE name = 'Francisco' LIMIT 1);

SET @dept_soporte = (SELECT id FROM departments WHERE name = 'Soporte Técnico' LIMIT 1);
SET @status_abierto = (SELECT id FROM ticket_status WHERE name = 'Abierto' LIMIT 1);
SET @priority_normal = (SELECT id FROM priorities WHERE name = 'Normal' LIMIT 1);
SET @staff_id = (SELECT id FROM staff WHERE role = 'agent' LIMIT 1);

-- Obtener algunos usuarios existentes para asignar a organizaciones
SET @user1 = (SELECT id FROM users LIMIT 1);
SET @user2 = (SELECT id FROM users ORDER BY id LIMIT 1 OFFSET 1);
SET @user3 = (SELECT id FROM users ORDER BY id LIMIT 1 OFFSET 2);
SET @user4 = (SELECT id FROM users ORDER BY id LIMIT 1 OFFSET 3);

-- 3) Actualizar usuarios para vincularlos a organizaciones
UPDATE users SET company = 'Empresa ABC S.A.' WHERE id = @user1;
UPDATE users SET company = 'TecnoSoluciones' WHERE id = @user2;
UPDATE users SET company = 'Servicios Generales López' WHERE id = @user3;
UPDATE users SET company = 'Francisco' WHERE id = @user4;

-- 4) Crear algunos tickets de ejemplo para estas organizaciones
INSERT INTO tickets (ticket_number, user_id, staff_id, dept_id, status_id, priority_id, subject, created, updated) VALUES
('TKT-ORG-001', @user1, @staff_id, @dept_soporte, @status_abierto, @priority_normal, 'Consulta sobre servicios - Empresa ABC', NOW(), NOW()),
('TKT-ORG-002', @user2, @staff_id, @dept_soporte, @status_abierto, @priority_normal, 'Soporte técnico - TecnoSoluciones', NOW(), NOW()),
('TKT-ORG-003', @user3, @staff_id, @dept_soporte, @status_abierto, @priority_normal, 'Problema con facturación - Servicios Generales', NOW(), NOW()),
('TKT-ORG-004', @user4, @staff_id, @dept_soporte, @status_abierto, @priority_normal, 'Consulta general - Francisco', NOW(), NOW());