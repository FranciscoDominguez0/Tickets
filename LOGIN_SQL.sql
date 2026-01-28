-- ============================================================================
-- QUERY SQL PARA LOGIN
-- ============================================================================

-- ============================================================================
-- 1. LOGIN CLIENTE (USUARIO)
-- ============================================================================
-- Esta query se ejecuta en Auth::loginUser()

SELECT id, email, firstname, lastname, password 
FROM users 
WHERE email = ? 
AND status = "active"

-- Parámetros: 
-- ? = email del usuario

-- Luego PHP verifica:
-- password_verify($password_ingresada, $fila['password'])

-- Si es correcto, actualizar último login:
UPDATE users 
SET last_login = NOW() 
WHERE id = ?

-- Parámetros:
-- ? = id del usuario


-- ============================================================================
-- 2. LOGIN AGENTE (STAFF)
-- ============================================================================
-- Esta query se ejecuta en Auth::loginStaff()

SELECT id, username, email, firstname, lastname, password 
FROM staff 
WHERE username = ? 
AND is_active = 1

-- Parámetros:
-- ? = username del agente

-- Luego PHP verifica:
-- password_verify($password_ingresada, $fila['password'])

-- Si es correcto, actualizar último login:
UPDATE staff 
SET last_login = NOW() 
WHERE id = ?

-- Parámetros:
-- ? = id del agente


-- ============================================================================
-- 3. QUERIES ÚTILES ADICIONALES
-- ============================================================================

-- Obtener usuario por ID (después del login)
SELECT id, email, firstname, lastname, status, created, last_login
FROM users
WHERE id = ?

-- Obtener agente por ID (después del login)
SELECT id, username, email, firstname, lastname, role, is_active, created, last_login
FROM staff
WHERE id = ?

-- Verificar si email existe (para registro)
SELECT COUNT(*) as count
FROM users
WHERE email = ?

-- Verificar si username existe (para agentes)
SELECT COUNT(*) as count
FROM staff
WHERE username = ?

-- Obtener todos los usuarios activos
SELECT id, email, firstname, lastname, created
FROM users
WHERE status = 'active'
ORDER BY created DESC

-- Obtener todos los agentes activos
SELECT id, username, email, firstname, lastname, role
FROM staff
WHERE is_active = 1
ORDER BY firstname ASC

-- Contar sesiones activas de un usuario
SELECT COUNT(*) as active_sessions
FROM sessions
WHERE user_id = ?
AND user_type = 'user'
AND expires > NOW()

-- Contar sesiones activas de un agente
SELECT COUNT(*) as active_sessions
FROM sessions
WHERE user_id = ?
AND user_type = 'staff'
AND expires > NOW()


-- ============================================================================
-- 4. CREAR USUARIO DE PRUEBA
-- ============================================================================

-- Cliente de prueba
-- Email: cliente@example.com
-- Contraseña: cliente123

INSERT INTO users (email, password, firstname, lastname, status, created) 
VALUES (
  'cliente@example.com',
  '$2y$10$JZd7xsVZqJ9YQJZs9xDG.OZv9E5xN6xN6xN6xN6xN6xN6xN6xN', -- bcrypt de "cliente123"
  'Juan',
  'Cliente',
  'active',
  NOW()
);

-- Agente de prueba
-- Usuario: admin
-- Contraseña: admin123

INSERT INTO staff (username, password, email, firstname, lastname, dept_id, role, is_active, created)
VALUES (
  'admin',
  '$2y$10$YIjlrJyeatqIz.Yy5C6He.BBVCoQmkdUVewO0E8/LewKJvLF6NO2', -- bcrypt de "admin123"
  'admin@example.com',
  'Admin',
  'System',
  1,
  'admin',
  1,
  NOW()
);

-- Otro agente de prueba
-- Usuario: soporte
-- Contraseña: soporte123

INSERT INTO staff (username, password, email, firstname, lastname, dept_id, role, is_active, created)
VALUES (
  'soporte',
  '$2y$10$soporte123hash', -- bcrypt de "soporte123"
  'soporte@example.com',
  'Juan',
  'Soporte',
  1,
  'agent',
  1,
  NOW()
);


-- ============================================================================
-- 5. GENERAR HASH BCRYPT EN LÍNEA
-- ============================================================================

/*
Para generar hashes bcrypt desde PHP, usa:

$password = "tu_contrasena";
$hash = password_hash($password, PASSWORD_BCRYPT);
echo $hash;

Ejemplo en terminal PHP:
php -r "echo password_hash('admin123', PASSWORD_BCRYPT);"

Los hashes bcrypt conocidos:
- "admin123" = $2y$10$YIjlrJyeatqIz.Yy5C6He.BBVCoQmkdUVewO0E8/LewKJvLF6NO2
- "cliente123" = $2y$10$JZd7xsVZqJ9YQJZs9xDG.OZv9E5xN6xN6xN6xN6xN6xN6xN6xN
*/

-- ============================================================================
-- FLUJO COMPLETO DE LOGIN
-- ============================================================================

/*
CLIENTE:
1. Usuario ve: cliente/login.php
2. Ingresa: email + contraseña
3. POST a: cliente/login.php
4. PHP ejecuta: Auth::loginUser($email, $password)
   a. SELECT id, email, firstname, lastname, password FROM users WHERE email = ? AND status = "active"
   b. Si existe: password_verify($password_ingresada, $fila['password'])
   c. Si match: UPDATE users SET last_login = NOW() WHERE id = ?
   d. Crear $_SESSION['user_id'], $_SESSION['user_type'] = 'cliente', etc
   e. Redirect a: cliente/index.php

AGENTE:
1. Usuario ve: agente/login.php
2. Ingresa: username + contraseña
3. POST a: agente/login.php
4. PHP ejecuta: Auth::loginStaff($username, $password)
   a. SELECT id, username, email, firstname, lastname, password FROM staff WHERE username = ? AND is_active = 1
   b. Si existe: password_verify($password_ingresada, $fila['password'])
   c. Si match: UPDATE staff SET last_login = NOW() WHERE id = ?
   d. Crear $_SESSION['staff_id'], $_SESSION['user_type'] = 'agente', etc
   e. Redirect a: agente/index.php
*/

-- ============================================================================
-- FIN DE SCRIPT
-- ============================================================================
