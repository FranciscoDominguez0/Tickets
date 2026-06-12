<?php
// Módulo: Solicitudes (tickets) — Bootstrap compartido
// a=open: abrir nuevo ticket (uid= preselecciona usuario). id=X: vista detallada.
// ── Migraciones de Facturación ──────────────────────────────────────────────
// Asegurar columna billing_status en ticket_reports (usa caché de sesión via dbColumnExists)
if (isset($mysqli) && $mysqli && !dbColumnExists('ticket_reports', 'billing_status')) {
    $mysqli->query("ALTER TABLE ticket_reports ADD COLUMN billing_status ENUM('pending', 'confirmed') NOT NULL DEFAULT 'pending' AFTER final_price");
    // Limpiar caché para que el próximo request vea la columna
    unset($_SESSION['_dbmeta_cache']['col:ticket_reports:billing_status']);
}

$ticketView = null;
$reply_errors = [];
$reply_success = false;

// Determinar si estamos en modo listado (seenIds solo se necesitan ahí)
$_isListView = !isset($_GET['id'])
    && !(isset($_GET['a']) && $_GET['a'] === 'open')
    && !isset($_GET['action']);

$seenKey = 'tickets_seen_' . (int)($_SESSION['staff_id'] ?? 0);
if (!isset($_SESSION[$seenKey]) || !is_array($_SESSION[$seenKey])) {
    $_SESSION[$seenKey] = [];
}
$seenIds = [];
foreach ($_SESSION[$seenKey] as $v) {
    if (is_numeric($v)) $seenIds[(int)$v] = true;
}

$sidNewSince = (int)($_SESSION['staff_id'] ?? 0);
if ($sidNewSince > 0) {
    $sinceKey = 'tickets_new_since_' . $sidNewSince;
    if (!isset($_SESSION[$sinceKey]) || !is_numeric($_SESSION[$sinceKey])) {
        // Usar time() de PHP en lugar de SELECT UNIX_TIMESTAMP a BD
        $_SESSION[$sinceKey] = time();
    }
}

// Cargar seenIds desde BD solo en listado (no en vista individual ni AJAX)
$sidSeenDb = (int)($_SESSION['staff_id'] ?? 0);
if ($_isListView && $sidSeenDb > 0 && isset($mysqli) && $mysqli && dbTableExists('staff_ticket_seen')) {
    $stmtSeenLoad = $mysqli->prepare('SELECT ticket_id FROM staff_ticket_seen WHERE staff_id = ? ORDER BY seen_at DESC LIMIT 500');
    if ($stmtSeenLoad) {
        $stmtSeenLoad->bind_param('i', $sidSeenDb);
        if ($stmtSeenLoad->execute()) {
            $rs = $stmtSeenLoad->get_result();
            while ($rs && ($r = $rs->fetch_assoc())) {
                $tidSeen = (int)($r['ticket_id'] ?? 0);
                if ($tidSeen > 0) $seenIds[$tidSeen] = true;
            }
        }
    }
    if (!empty($seenIds)) {
        $_SESSION[$seenKey] = array_values(array_slice(array_unique(array_map('intval', array_keys($seenIds))), -500));
    }
}

$eid = empresaId();

// Usar dbTableExists() con caché de sesión (TTL 300s) en lugar de SHOW TABLES directo
$hasStaffDepartmentsTable = dbTableExists('staff_departments');

$staffBelongsToDept = function (int $staffId, int $deptId, int $generalDeptId) use ($mysqli, $eid, $hasStaffDepartmentsTable): bool {
    if ($deptId <= 0) return true;
    if ($staffId <= 0) return false;
    if (!isset($mysqli) || !$mysqli) return false;

    // Use staff_departments table (new multi-department model)
    if ($hasStaffDepartmentsTable) {
        $stmt = $mysqli->prepare('SELECT 1 FROM staff s JOIN staff_departments sd ON sd.staff_id = s.id WHERE s.empresa_id = ? AND s.id = ? AND s.is_active = 1 AND sd.dept_id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('iii', $eid, $staffId, $deptId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) return true;
        }
        return false;
    }

    // Legacy fallback: staff.dept_id (temporary compatibility)
    $stmt = $mysqli->prepare('SELECT COALESCE(NULLIF(dept_id, 0), ?) AS dept_id FROM staff WHERE empresa_id = ? AND id = ? AND is_active = 1 LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('iii', $generalDeptId, $eid, $staffId);
    $stmt->execute();
    $sdept = (int)($stmt->get_result()->fetch_assoc()['dept_id'] ?? 0);
    return ($sdept === $deptId);
};

// Ticket status ids (best-effort mapping by name)
$statusIdOpen = 0;
$statusIdInProgress = 0;
$statusIdResolved = 0;
$statusIdClosed = 0;
try {
    if (isset($mysqli) && $mysqli) {
        $rsSt = @$mysqli->query('SELECT id, name FROM ticket_status');
        if ($rsSt) {
            while ($rsSt && ($st = $rsSt->fetch_assoc())) {
                $sid = (int)($st['id'] ?? 0);
                $sname = strtolower(trim((string)($st['name'] ?? '')));
                if ($sid <= 0 || $sname === '') continue;
                if ($statusIdOpen === 0 && (str_contains($sname, 'abiert') || str_contains($sname, 'open'))) {
                    $statusIdOpen = $sid;
                    continue;
                }
                if ($statusIdInProgress === 0 && (str_contains($sname, 'progres') || str_contains($sname, 'progress') || str_contains($sname, 'en curso') || str_contains($sname, 'working'))) {
                    $statusIdInProgress = $sid;
                    continue;
                }
                if ($statusIdResolved === 0 && (str_contains($sname, 'resuelt') || str_contains($sname, 'resolved'))) {
                    $statusIdResolved = $sid;
                    continue;
                }
                if ($statusIdClosed === 0 && (str_contains($sname, 'cerrad') || str_contains($sname, 'closed'))) {
                    $statusIdClosed = $sid;
                    continue;
                }
            }
        }
    }
} catch (Throwable $e) {
}

// Auto-close: (DESHABILITADO por solicitud del usuario para que todo el cierre sea manual)
/*
try {
    if (isset($mysqli) && $mysqli && $statusIdResolved > 0 && $statusIdClosed > 0) {
        $hasSigReqCol = false;
        try {
            $hasSigReqCol = dbColumnExists('tickets', 'signature_requested');
        } catch (Throwable $e) {}

        $extraWhere = $hasSigReqCol ? ' AND (signature_requested IS NULL OR signature_requested = 0)' : '';

        $stmtAutoClose = $mysqli->prepare(
            'UPDATE tickets '
            . 'SET status_id = ?, closed = NOW(), updated = NOW() '
            . 'WHERE empresa_id = ? AND status_id = ? AND (closed IS NULL)' . $extraWhere . ' AND updated <= (NOW() - INTERVAL 1 DAY)'
        );
        if ($stmtAutoClose) {
            $stmtAutoClose->bind_param('iii', $statusIdClosed, $eid, $statusIdResolved);
            $stmtAutoClose->execute();
        }
    }
} catch (Throwable $e) {
}
*/

// Usar dbColumnExists() con caché de sesión en lugar de SHOW COLUMNS directo
$threadsHasEmpresa = dbColumnExists('threads', 'empresa_id');
$entriesHasEmpresa = dbColumnExists('thread_entries', 'empresa_id');

// Tabla de tickets vinculados (si no existe, se crea bajo demanda)
$ensureTicketLinksTable = function () use ($mysqli) {
    if (!isset($mysqli) || !$mysqli) return false;
    $exists = @$mysqli->query("SHOW TABLES LIKE 'ticket_links'");
    if ($exists && $exists->num_rows > 0) return true;
    $sql = "CREATE TABLE IF NOT EXISTS ticket_links (\n"
        . "  ticket_id INT NOT NULL,\n"
        . "  linked_ticket_id INT NOT NULL,\n"
        . "  created DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
        . "  UNIQUE KEY uq_ticket_link (ticket_id, linked_ticket_id),\n"
        . "  KEY idx_ticket (ticket_id),\n"
        . "  KEY idx_linked (linked_ticket_id)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    return (bool)@$mysqli->query($sql);
};

// Departamento "General" (fallback). Si no existe, se usará 0 y se omite la excepción.
$generalDeptId = 0;
$stmtGd = $mysqli->prepare("SELECT id FROM departments WHERE empresa_id = ? AND LOWER(name) LIKE ? LIMIT 1");
if ($stmtGd) {
    $likeGeneral = '%general%';
    $stmtGd->bind_param('is', $eid, $likeGeneral);
    if ($stmtGd->execute()) {
        $rgd = $stmtGd->get_result();
        if ($rgd && ($row = $rgd->fetch_assoc())) {
            $generalDeptId = (int) ($row['id'] ?? 0);
        }
    }
}
