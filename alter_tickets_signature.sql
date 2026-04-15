-- ============================================================
-- Migración: Agregar campos de firma y cierre a tabla tickets
-- ============================================================

ALTER TABLE tickets
  ADD COLUMN client_signature VARCHAR(255) NULL COMMENT 'Ruta del archivo PNG de firma del cliente',
  ADD COLUMN close_message TEXT NULL COMMENT 'Motivo de cierre del ticket',
  ADD COLUMN closed_at DATETIME NULL COMMENT 'Fecha y hora de cierre del ticket';
