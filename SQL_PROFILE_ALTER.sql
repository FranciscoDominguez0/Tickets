-- ============================================================================
-- PERFIL DE AGENTE: columna firma (signature)
-- Ejecutar una vez para habilitar la pestaña "Firma" en Mi perfil
-- ============================================================================
USE tickets_db;

-- Si la columna ya existe, omitir esta línea o comentarla
ALTER TABLE staff
  ADD COLUMN signature TEXT NULL COMMENT 'Firma opcional en respuestas'
  AFTER last_login;
