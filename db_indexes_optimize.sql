-- ============================================================
-- Índices de rendimiento — Sistema de Tickets
-- Compatible con MySQL 5.7 y 8.0 — MySQL Workbench
-- SOLO agrega índices si NO existen (NO toca datos ni tablas)
-- ============================================================

-- Procedimiento auxiliar temporal para crear índice solo si no existe
DROP PROCEDURE IF EXISTS _add_idx;

DELIMITER $$
CREATE PROCEDURE _add_idx(tbl VARCHAR(100), idx VARCHAR(100), cols TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = tbl
          AND INDEX_NAME   = idx
        LIMIT 1
    ) THEN
        SET @_sql = CONCAT('ALTER TABLE `', tbl, '` ADD INDEX `', idx, '` (', cols, ')');
        PREPARE _st FROM @_sql;
        EXECUTE _st;
        DEALLOCATE PREPARE _st;
    END IF;
END $$
DELIMITER ;

-- ── tickets ───────────────────────────────────────────────────
CALL _add_idx('tickets', 'idx_emp_closed',   'empresa_id, closed');
CALL _add_idx('tickets', 'idx_emp_staff_cl', 'empresa_id, staff_id, closed');
CALL _add_idx('tickets', 'idx_emp_updated',  'empresa_id, updated');
CALL _add_idx('tickets', 'idx_emp_status',   'empresa_id, status_id');
CALL _add_idx('tickets', 'idx_emp_dept',     'empresa_id, dept_id');
CALL _add_idx('tickets', 'idx_emp_user',     'empresa_id, user_id');

-- ── ticket_reports ────────────────────────────────────────────
CALL _add_idx('ticket_reports', 'idx_tr_ticket_id', 'ticket_id');
CALL _add_idx('ticket_reports', 'idx_tr_billing',   'billing_status');

-- ── ticket_approvals ──────────────────────────────────────────
CALL _add_idx('ticket_approvals', 'idx_ta_ticket_id', 'ticket_id, id');

-- ── users ─────────────────────────────────────────────────────
CALL _add_idx('users', 'idx_u_emp_email',   'empresa_id, email');
CALL _add_idx('users', 'idx_u_emp_status',  'empresa_id, status');
CALL _add_idx('users', 'idx_u_emp_created', 'empresa_id, created');

-- ── departments ───────────────────────────────────────────────
CALL _add_idx('departments', 'idx_d_emp_requires', 'empresa_id, requires_report');

-- Limpiar procedimiento auxiliar
DROP PROCEDURE IF EXISTS _add_idx;

-- ── Verificar índices creados ─────────────────────────────────
-- SHOW INDEX FROM tickets;
-- SHOW INDEX FROM ticket_reports;
-- SHOW INDEX FROM ticket_approvals;
