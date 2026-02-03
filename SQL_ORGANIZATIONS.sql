-- ============================================================================
-- TABLA ORGANIZACIONES Y MIGRACIÓN DESDE users.company
-- Ejecutar en tu base de datos (tickets_db o la que uses)
-- ============================================================================

-- 1) Crear tabla organizations si no existe
CREATE TABLE IF NOT EXISTS organizations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL COMMENT 'Nombre de la organización',
    address TEXT COMMENT 'Dirección',
    phone VARCHAR(50) COMMENT 'Teléfono',
    phone_ext VARCHAR(20) COMMENT 'Extensión',
    website VARCHAR(255) COMMENT 'Sitio web',
    notes TEXT COMMENT 'Notas internas',
    created DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) INSERTs de organizaciones (edita nombres y datos a tu gusto)
INSERT IGNORE INTO organizations (name, address, phone, phone_ext, website, notes) VALUES
('Empresa ABC S.A.', 'Av. Principal 123, Ciudad', '600-0001', '101', 'https://www.empresaabc.com', 'Cliente preferente'),
('TecnoSoluciones', 'Calle Tecnología 45', '600-0002', NULL, 'https://www.tecnosoluciones.com', NULL),
('Servicios Generales López', 'Zona Industrial, Bodega 7', '600-0003', '200', NULL, 'Notas internas aquí'),
('Francisco', 'COCLE, AGUADULCE CABECERA', '67298084', NULL, NULL, 'hols');

-- Para añadir más, copia una línea y cambia los valores:
-- INSERT INTO organizations (name, address, phone, phone_ext, website, notes) VALUES
-- ('Otra Organización', 'Dirección', 'Teléfono', 'Ext', 'https://web.com', 'Notas');
