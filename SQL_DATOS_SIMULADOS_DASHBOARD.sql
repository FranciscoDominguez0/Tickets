-- ============================================================================
-- SQL PARA SIMULAR DATOS REALES DEL DASHBOARD
-- Genera tickets con fechas distribuidas en el último mes
-- Basado en la imagen del dashboard con datos realistas
-- Compatible con la estructura de tickets_db
-- ============================================================================

USE tickets_db;

-- Limpiar datos anteriores (opcional - comentar si no quieres borrar)
-- DELETE FROM thread_entries WHERE id > 0;
-- DELETE FROM threads WHERE id > 0;
-- DELETE FROM tickets WHERE id > 0;

-- ============================================================================
-- OBTENER IDs NECESARIOS
-- ============================================================================

SET @dept_soporte = (SELECT id FROM departments WHERE name = 'Soporte Técnico' LIMIT 1);
SET @dept_ventas = (SELECT id FROM departments WHERE name = 'Ventas' LIMIT 1);
SET @dept_facturacion = (SELECT id FROM departments WHERE name = 'Facturación' LIMIT 1);
SET @status_abierto = (SELECT id FROM ticket_status WHERE name = 'Abierto' LIMIT 1);
SET @status_cerrado = (SELECT id FROM ticket_status WHERE name = 'Cerrado' LIMIT 1);
SET @status_resuelto = (SELECT id FROM ticket_status WHERE name = 'Resuelto' LIMIT 1);
SET @priority_normal = (SELECT id FROM priorities WHERE name = 'Normal' LIMIT 1);
SET @priority_alta = (SELECT id FROM priorities WHERE name = 'Alta' LIMIT 1);
SET @priority_urgente = (SELECT id FROM priorities WHERE name = 'Urgente' LIMIT 1);

-- Obtener usuarios y staff existentes
SET @user_id = (SELECT id FROM users LIMIT 1);
SET @user_id2 = (SELECT id FROM users ORDER BY id LIMIT 1 OFFSET 1);
SET @user_id3 = (SELECT id FROM users ORDER BY id LIMIT 1 OFFSET 2);
SET @staff_id = (SELECT id FROM staff WHERE role = 'agent' LIMIT 1);
SET @staff_id2 = (SELECT id FROM staff WHERE role = 'agent' ORDER BY id LIMIT 1 OFFSET 1);

-- Si no hay usuarios o staff suficientes, usar el primero disponible
SET @user_id = IFNULL(@user_id, (SELECT id FROM users LIMIT 1));
SET @user_id2 = IFNULL(@user_id2, @user_id);
SET @user_id3 = IFNULL(@user_id3, @user_id);
SET @staff_id = IFNULL(@staff_id, (SELECT id FROM staff LIMIT 1));
SET @staff_id2 = IFNULL(@staff_id2, @staff_id);

-- ============================================================================
-- INSERTAR TICKETS CON FECHAS DISTRIBUIDAS
-- ============================================================================

-- Tickets creados desde diciembre 2025 hasta enero 2026
INSERT INTO tickets (ticket_number, user_id, staff_id, dept_id, status_id, priority_id, subject, created, updated, closed) VALUES
-- 12-27-2025
('TKT-20251227-001', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Problema con conexión a internet', '2025-12-27 09:15:00', '2025-12-27 14:30:00', '2025-12-27 14:30:00'),
('TKT-20251227-002', @user_id2, @staff_id, @dept_soporte, @status_cerrado, @priority_alta, 'Error en sistema de facturación', '2025-12-27 10:20:00', '2025-12-27 16:45:00', '2025-12-27 16:45:00'),
('TKT-20251227-003', @user_id3, NULL, @dept_ventas, @status_abierto, @priority_normal, 'Consulta sobre producto nuevo', '2025-12-27 11:30:00', '2025-12-27 11:30:00', NULL),

-- 12-28-2025
('TKT-20251228-001', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Solicitud de cambio de contraseña', '2025-12-28 08:00:00', '2025-12-28 12:00:00', '2025-12-28 12:00:00'),
('TKT-20251228-002', @user_id2, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Problema con impresora', '2025-12-28 09:30:00', '2025-12-28 15:20:00', '2025-12-28 15:20:00'),
('TKT-20251228-003', @user_id3, NULL, @dept_facturacion, @status_abierto, @priority_alta, 'Error en factura emitida', '2025-12-28 10:45:00', '2025-12-28 10:45:00', NULL),
('TKT-20251228-004', @user_id, @staff_id2, @dept_ventas, @status_cerrado, @priority_normal, 'Cotización solicitada', '2025-12-28 14:20:00', '2025-12-28 17:00:00', '2025-12-28 17:00:00'),

-- 12-29-2025
('TKT-20251229-001', @user_id2, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Instalación de software', '2025-12-29 08:30:00', '2025-12-29 13:15:00', '2025-12-29 13:15:00'),
('TKT-20251229-002', @user_id3, NULL, @dept_soporte, @status_abierto, @priority_alta, 'Sistema lento', '2025-12-29 09:45:00', '2025-12-29 09:45:00', NULL),
('TKT-20251229-003', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Configuración de email', '2025-12-29 11:00:00', '2025-12-29 16:30:00', '2025-12-29 16:30:00'),

-- 12-30-2025 (pico de creación según imagen)
('TKT-20251230-001', @user_id2, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Problema con VPN', '2025-12-30 08:00:00', '2025-12-30 12:30:00', '2025-12-30 12:30:00'),
('TKT-20251230-002', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_alta, 'Error crítico en aplicación', '2025-12-30 09:15:00', '2025-12-30 14:00:00', '2025-12-30 14:00:00'),
('TKT-20251230-003', @user_id3, @staff_id2, @dept_ventas, @status_cerrado, @priority_normal, 'Solicitud de información', '2025-12-30 10:30:00', '2025-12-30 15:45:00', '2025-12-30 15:45:00'),
('TKT-20251230-004', @user_id2, NULL, @dept_soporte, @status_abierto, @priority_normal, 'Consulta técnica', '2025-12-30 11:45:00', '2025-12-30 11:45:00', NULL),

-- 12-31-2025
('TKT-20251231-001', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Mantenimiento preventivo', '2025-12-31 08:30:00', '2025-12-31 13:00:00', '2025-12-31 13:00:00'),
('TKT-20251231-002', @user_id3, @staff_id2, @dept_facturacion, @status_cerrado, @priority_normal, 'Corrección de factura', '2025-12-31 10:00:00', '2025-12-31 15:30:00', '2025-12-31 15:30:00'),

-- Enero 2026
-- 01-01-2026
('TKT-20260101-001', @user_id2, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Soporte día festivo', '2026-01-01 09:00:00', '2026-01-01 14:00:00', '2026-01-01 14:00:00'),

-- 01-02-2026
('TKT-20260102-001', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Actualización de sistema', '2026-01-02 08:15:00', '2026-01-02 12:45:00', '2026-01-02 12:45:00'),
('TKT-20260102-002', @user_id3, NULL, @dept_ventas, @status_abierto, @priority_normal, 'Nueva consulta', '2026-01-02 10:30:00', '2026-01-02 10:30:00', NULL),

-- 01-03-2026
('TKT-20260103-001', @user_id2, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Problema con base de datos', '2026-01-03 09:00:00', '2026-01-03 15:00:00', '2026-01-03 15:00:00'),
('TKT-20260103-002', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_alta, 'Error en servidor', '2026-01-03 11:20:00', '2026-01-03 16:30:00', '2026-01-03 16:30:00'),

-- 01-04-2026
('TKT-20260104-001', @user_id3, @staff_id2, @dept_soporte, @status_cerrado, @priority_normal, 'Configuración de red', '2026-01-04 08:30:00', '2026-01-04 13:15:00', '2026-01-04 13:15:00'),

-- 01-05-2026
('TKT-20260105-001', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Instalación de actualización', '2026-01-05 09:00:00', '2026-01-05 14:00:00', '2026-01-05 14:00:00'),
('TKT-20260105-002', @user_id2, NULL, @dept_facturacion, @status_abierto, @priority_normal, 'Consulta de facturación', '2026-01-05 10:45:00', '2026-01-05 10:45:00', NULL),

-- 01-06-2026
('TKT-20260106-001', @user_id3, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Problema con acceso remoto', '2026-01-06 08:15:00', '2026-01-06 12:30:00', '2026-01-06 12:30:00'),

-- 01-07-2026
('TKT-20260107-001', @user_id, @staff_id2, @dept_soporte, @status_cerrado, @priority_normal, 'Solicitud de permisos', '2026-01-07 09:30:00', '2026-01-07 14:45:00', '2026-01-07 14:45:00'),
('TKT-20260107-002', @user_id2, @staff_id, @dept_ventas, @status_cerrado, @priority_normal, 'Información de producto', '2026-01-07 11:00:00', '2026-01-07 16:00:00', '2026-01-07 16:00:00'),

-- 01-08-2026
('TKT-20260108-001', @user_id3, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Error en aplicación web', '2026-01-08 08:00:00', '2026-01-08 13:20:00', '2026-01-08 13:20:00'),

-- 01-09-2026
('TKT-20260109-001', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Problema con backup', '2026-01-09 09:15:00', '2026-01-09 14:00:00', '2026-01-09 14:00:00'),
('TKT-20260109-002', @user_id2, NULL, @dept_soporte, @status_abierto, @priority_alta, 'Sistema no responde', '2026-01-09 10:30:00', '2026-01-09 10:30:00', NULL),

-- 01-10-2026
('TKT-20260110-001', @user_id3, @staff_id2, @dept_soporte, @status_cerrado, @priority_normal, 'Configuración de firewall', '2026-01-10 08:30:00', '2026-01-10 12:45:00', '2026-01-10 12:45:00'),

-- 01-11-2026
('TKT-20260111-001', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Actualización de antivirus', '2026-01-11 09:00:00', '2026-01-11 15:30:00', '2026-01-11 15:30:00'),
('TKT-20260111-002', @user_id2, @staff_id2, @dept_facturacion, @status_cerrado, @priority_normal, 'Corrección de datos', '2026-01-11 11:15:00', '2026-01-11 16:00:00', '2026-01-11 16:00:00'),

-- 01-12-2026
('TKT-20260112-001', @user_id3, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Problema con impresora de red', '2026-01-12 08:15:00', '2026-01-12 13:00:00', '2026-01-12 13:00:00'),

-- 01-13-2026 (pico según imagen)
('TKT-20260113-001', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Error en login', '2026-01-13 08:00:00', '2026-01-13 12:30:00', '2026-01-13 12:30:00'),
('TKT-20260113-002', @user_id2, @staff_id, @dept_soporte, @status_cerrado, @priority_alta, 'Problema crítico', '2026-01-13 09:15:00', '2026-01-13 14:45:00', '2026-01-13 14:45:00'),
('TKT-20260113-003', @user_id3, @staff_id2, @dept_ventas, @status_cerrado, @priority_normal, 'Consulta urgente', '2026-01-13 10:30:00', '2026-01-13 15:00:00', '2026-01-13 15:00:00'),
('TKT-20260113-004', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Soporte técnico', '2026-01-13 11:45:00', '2026-01-13 16:15:00', '2026-01-13 16:15:00'),
('TKT-20260113-005', @user_id2, @staff_id2, @dept_soporte, @status_cerrado, @priority_normal, 'Configuración de usuario', '2026-01-13 13:00:00', '2026-01-13 17:30:00', '2026-01-13 17:30:00'),
('TKT-20260113-006', @user_id3, @staff_id, @dept_facturacion, @status_cerrado, @priority_normal, 'Revisión de factura', '2026-01-13 14:15:00', '2026-01-13 18:00:00', '2026-01-13 18:00:00'),

-- 01-14-2026
('TKT-20260114-001', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Mantenimiento programado', '2026-01-14 08:30:00', '2026-01-14 13:00:00', '2026-01-14 13:00:00'),

-- 01-15-2026
('TKT-20260115-001', @user_id2, @staff_id2, @dept_soporte, @status_cerrado, @priority_normal, 'Problema con correo', '2026-01-15 09:00:00', '2026-01-15 14:30:00', '2026-01-15 14:30:00'),
('TKT-20260115-002', @user_id3, NULL, @dept_ventas, @status_abierto, @priority_normal, 'Nueva solicitud', '2026-01-15 10:45:00', '2026-01-15 10:45:00', NULL),

-- 01-16-2026
('TKT-20260116-001', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Actualización de drivers', '2026-01-16 08:15:00', '2026-01-16 12:45:00', '2026-01-16 12:45:00'),

-- 01-17-2026
('TKT-20260117-001', @user_id2, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Configuración de red WiFi', '2026-01-17 09:30:00', '2026-01-17 14:00:00', '2026-01-17 14:00:00'),
('TKT-20260117-002', @user_id3, @staff_id2, @dept_facturacion, @status_cerrado, @priority_normal, 'Consulta de pago', '2026-01-17 11:00:00', '2026-01-17 16:15:00', '2026-01-17 16:15:00'),

-- 01-18-2026
('TKT-20260118-001', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Problema con scanner', '2026-01-18 08:00:00', '2026-01-18 13:30:00', '2026-01-18 13:30:00'),

-- 01-19-2026
('TKT-20260119-001', @user_id2, @staff_id2, @dept_soporte, @status_cerrado, @priority_normal, 'Instalación de software nuevo', '2026-01-19 09:15:00', '2026-01-19 15:00:00', '2026-01-19 15:00:00'),

-- 01-20-2026 (pico de deleted según imagen - estos se marcarán como "deleted")
('TKT-20260120-001', @user_id3, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Ticket duplicado', '2026-01-20 08:30:00', '2026-01-20 12:00:00', '2026-01-20 12:00:00'),
('TKT-20260120-002', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Ticket de prueba', '2026-01-20 09:45:00', '2026-01-20 13:15:00', '2026-01-20 13:15:00'),
('TKT-20260120-003', @user_id2, @staff_id2, @dept_ventas, @status_cerrado, @priority_normal, 'Consulta resuelta', '2026-01-20 11:00:00', '2026-01-20 14:30:00', '2026-01-20 14:30:00'),
('TKT-20260120-004', @user_id3, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Error corregido', '2026-01-20 12:15:00', '2026-01-20 15:45:00', '2026-01-20 15:45:00'),

-- 01-21-2026
('TKT-20260121-001', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Soporte regular', '2026-01-21 08:00:00', '2026-01-21 13:00:00', '2026-01-21 13:00:00'),

-- 01-22-2026
('TKT-20260122-001', @user_id2, @staff_id2, @dept_soporte, @status_cerrado, @priority_normal, 'Problema con monitor', '2026-01-22 09:30:00', '2026-01-22 14:15:00', '2026-01-22 14:15:00'),
('TKT-20260122-002', @user_id3, NULL, @dept_soporte, @status_abierto, @priority_alta, 'Sistema bloqueado', '2026-01-22 10:45:00', '2026-01-22 10:45:00', NULL),

-- 01-23-2026
('TKT-20260123-001', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Configuración de seguridad', '2026-01-23 08:15:00', '2026-01-23 12:45:00', '2026-01-23 12:45:00'),

-- 01-24-2026 (pico de deleted según imagen)
('TKT-20260124-001', @user_id2, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Ticket cancelado', '2026-01-24 08:30:00', '2026-01-24 11:00:00', '2026-01-24 11:00:00'),
('TKT-20260124-002', @user_id3, @staff_id2, @dept_soporte, @status_cerrado, @priority_normal, 'Duplicado eliminado', '2026-01-24 09:45:00', '2026-01-24 12:15:00', '2026-01-24 12:15:00'),
('TKT-20260124-003', @user_id, @staff_id, @dept_ventas, @status_cerrado, @priority_normal, 'Consulta antigua', '2026-01-24 11:00:00', '2026-01-24 13:30:00', '2026-01-24 13:30:00'),
('TKT-20260124-004', @user_id2, @staff_id2, @dept_soporte, @status_cerrado, @priority_normal, 'Ticket de prueba', '2026-01-24 12:15:00', '2026-01-24 14:45:00', '2026-01-24 14:45:00'),
('TKT-20260124-005', @user_id3, @staff_id, @dept_facturacion, @status_cerrado, @priority_normal, 'Error corregido', '2026-01-24 13:30:00', '2026-01-24 16:00:00', '2026-01-24 16:00:00'),

-- 01-25-2026
('TKT-20260125-001', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Mantenimiento preventivo', '2026-01-25 08:00:00', '2026-01-25 13:00:00', '2026-01-25 13:00:00'),

-- 01-26-2026 (pico máximo según imagen)
('TKT-20260126-001', @user_id2, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Problema con servidor', '2026-01-26 08:00:00', '2026-01-26 12:30:00', '2026-01-26 12:30:00'),
('TKT-20260126-002', @user_id3, @staff_id, @dept_soporte, @status_cerrado, @priority_alta, 'Error crítico sistema', '2026-01-26 09:15:00', '2026-01-26 14:00:00', '2026-01-26 14:00:00'),
('TKT-20260126-003', @user_id, @staff_id2, @dept_ventas, @status_cerrado, @priority_normal, 'Consulta múltiple', '2026-01-26 10:30:00', '2026-01-26 15:15:00', '2026-01-26 15:15:00'),
('TKT-20260126-004', @user_id2, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Soporte técnico', '2026-01-26 11:45:00', '2026-01-26 16:30:00', '2026-01-26 16:30:00'),
('TKT-20260126-005', @user_id3, @staff_id2, @dept_soporte, @status_cerrado, @priority_normal, 'Configuración avanzada', '2026-01-26 13:00:00', '2026-01-26 17:45:00', '2026-01-26 17:45:00'),
('TKT-20260126-006', @user_id, @staff_id, @dept_facturacion, @status_cerrado, @priority_normal, 'Revisión completa', '2026-01-26 14:15:00', '2026-01-26 18:00:00', '2026-01-26 18:00:00'),
('TKT-20260126-007', @user_id2, @staff_id2, @dept_soporte, @status_cerrado, @priority_alta, 'Problema urgente', '2026-01-26 15:30:00', '2026-01-26 19:15:00', '2026-01-26 19:15:00'),
('TKT-20260126-008', @user_id3, @staff_id, @dept_ventas, @status_cerrado, @priority_normal, 'Información detallada', '2026-01-26 16:45:00', '2026-01-26 20:00:00', '2026-01-26 20:00:00'),
('TKT-20260126-009', @user_id, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Mantenimiento completo', '2026-01-26 18:00:00', '2026-01-26 21:30:00', '2026-01-26 21:30:00'),
('TKT-20260126-010', @user_id2, @staff_id2, @dept_soporte, @status_cerrado, @priority_normal, 'Actualización final', '2026-01-26 19:15:00', '2026-01-26 22:00:00', '2026-01-26 22:00:00'),

-- 01-27-2026
('TKT-20260127-001', @user_id3, @staff_id, @dept_soporte, @status_cerrado, @priority_normal, 'Soporte continuo', '2026-01-27 08:30:00', '2026-01-27 13:00:00', '2026-01-27 13:00:00'),

-- 01-28-2026
('TKT-20260128-001', @user_id, @staff_id2, @dept_soporte, @status_cerrado, @priority_normal, 'Consulta final', '2026-01-28 09:00:00', '2026-01-28 14:30:00', '2026-01-28 14:30:00'),
('TKT-20260128-002', @user_id2, NULL, @dept_ventas, @status_abierto, @priority_normal, 'Nueva consulta', '2026-01-28 10:15:00', '2026-01-28 10:15:00', NULL);

-- ============================================================================
-- CREAR THREADS PARA CADA TICKET
-- ============================================================================

INSERT INTO threads (ticket_id, created)
SELECT id, created FROM tickets
WHERE id NOT IN (SELECT ticket_id FROM threads);

-- ============================================================================
-- CREAR THREAD_ENTRIES (MENSAJES) PARA SIMULAR CONVERSACIONES
-- ============================================================================

-- Para cada ticket, crear un mensaje inicial del usuario y una respuesta del agente (si está asignado)
INSERT INTO thread_entries (thread_id, user_id, staff_id, body, is_internal, created)
SELECT 
    t.id as thread_id,
    tk.user_id,
    NULL as staff_id,
    CONCAT('Mensaje inicial del ticket: ', tk.subject) as body,
    0 as is_internal,
    tk.created
FROM threads t
JOIN tickets tk ON t.ticket_id = tk.id
WHERE NOT EXISTS (
    SELECT 1 FROM thread_entries te WHERE te.thread_id = t.id AND te.user_id IS NOT NULL
);

-- Crear respuestas de agentes para tickets asignados
INSERT INTO thread_entries (thread_id, user_id, staff_id, body, is_internal, created)
SELECT 
    t.id as thread_id,
    NULL as user_id,
    tk.staff_id,
    'Hemos recibido su solicitud y estamos trabajando en resolverla. Le mantendremos informado.' as body,
    0 as is_internal,
    DATE_ADD(tk.created, INTERVAL FLOOR(RAND() * 2 + 1) HOUR) as created
FROM threads t
JOIN tickets tk ON t.ticket_id = tk.id
WHERE tk.staff_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM thread_entries te WHERE te.thread_id = t.id AND te.staff_id IS NOT NULL
);

-- ============================================================================
-- SIMULAR TICKETS "DELETED" - Actualizar updated muy cerca de closed
-- ============================================================================

-- Para tickets en fechas específicas (01-20 y 01-24), simular que fueron "eliminados"
-- actualizando el campo updated muy cerca del closed (dentro de 1 hora)
UPDATE tickets 
SET updated = DATE_ADD(closed, INTERVAL FLOOR(RAND() * 60) MINUTE)
WHERE DATE(closed) IN ('2026-01-20', '2026-01-24')
AND status_id = @status_cerrado
AND closed IS NOT NULL;

-- ============================================================================
-- VERIFICAR DATOS INSERTADOS
-- ============================================================================

SELECT 
    DATE(created) as fecha,
    COUNT(*) as creados,
    SUM(CASE WHEN status_id = @status_cerrado THEN 1 ELSE 0 END) as cerrados,
    SUM(CASE WHEN status_id = @status_abierto THEN 1 ELSE 0 END) as abiertos
FROM tickets
WHERE DATE(created) >= '2025-12-27'
GROUP BY DATE(created)
ORDER BY fecha;

-- Estadísticas por departamento
SELECT 
    d.name as departamento,
    COUNT(t.id) as total,
    SUM(CASE WHEN t.status_id = @status_abierto THEN 1 ELSE 0 END) as abiertos,
    SUM(CASE WHEN t.status_id = @status_cerrado THEN 1 ELSE 0 END) as cerrados
FROM departments d
LEFT JOIN tickets t ON d.id = t.dept_id
WHERE t.created >= '2025-12-28' OR t.created IS NULL
GROUP BY d.id, d.name;
