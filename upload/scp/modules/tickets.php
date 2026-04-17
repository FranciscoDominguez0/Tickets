<?php
// Módulo: Solicitudes (tickets)
// a=open: abrir nuevo ticket (uid= preselecciona usuario). id=X: vista detallada.

$ticketView = null;
$reply_errors = [];
$reply_success = false;

$seenKey = 'tickets_seen_' . (int)($_SESSION['staff_id'] ?? 0);
if (!isset($_SESSION[$seenKey]) || !is_array($_SESSION[$seenKey])) {
    $_SESSION[$seenKey] = [];
}
$seenIds = [];
foreach (($_SESSION[$seenKey] ?? []) as $v) {
    if (is_numeric($v)) $seenIds[(int)$v] = true;
}

$sidNewSince = (int)($_SESSION['staff_id'] ?? 0);
if ($sidNewSince > 0) {
    $sinceKey = 'tickets_new_since_' . $sidNewSince;
    if (!isset($_SESSION[$sinceKey]) || !is_numeric($_SESSION[$sinceKey])) {
        $since = time();
        if (isset($mysqli) && $mysqli) {
            $q = @$mysqli->query('SELECT UNIX_TIMESTAMP(NOW()) ts');
            if ($q && ($r = $q->fetch_assoc()) && is_numeric($r['ts'] ?? null)) {
                $since = (int)$r['ts'];
            }
        }
        $_SESSION[$sinceKey] = $since;
    }
}

$sidSeenDb = (int)($_SESSION['staff_id'] ?? 0);
if ($sidSeenDb > 0 && isset($mysqli) && $mysqli) {
    @$mysqli->query(
        "CREATE TABLE IF NOT EXISTS staff_ticket_seen (\n"
        . "  staff_id INT NOT NULL,\n"
        . "  ticket_id INT NOT NULL,\n"
        . "  seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  PRIMARY KEY (staff_id, ticket_id),\n"
        . "  KEY idx_ticket (ticket_id),\n"
        . "  KEY idx_staff_seen_at (staff_id, seen_at)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    );

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

$hasStaffDepartmentsTable = false;
if (isset($mysqli) && $mysqli) {
    try {
        $rt = $mysqli->query("SHOW TABLES LIKE 'staff_departments'");
        $hasStaffDepartmentsTable = ($rt && $rt->num_rows > 0);
    } catch (Throwable $e) {
        $hasStaffDepartmentsTable = false;
    }
}

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

// Auto-close: after 24h in Resolved, move to Closed
try {
    if (isset($mysqli) && $mysqli && $statusIdResolved > 0 && $statusIdClosed > 0) {
        $stmtAutoClose = $mysqli->prepare(
            'UPDATE tickets '
            . 'SET status_id = ?, closed = NOW(), updated = NOW() '
            . 'WHERE empresa_id = ? AND status_id = ? AND (closed IS NULL) AND updated <= (NOW() - INTERVAL 1 DAY)'
        );
        if ($stmtAutoClose) {
            $stmtAutoClose->bind_param('iii', $statusIdClosed, $eid, $statusIdResolved);
            $stmtAutoClose->execute();
        }
    }
} catch (Throwable $e) {
}

$threadsHasEmpresa = false;
$entriesHasEmpresa = false;
if (isset($mysqli) && $mysqli) {
    $colTh = $mysqli->query("SHOW COLUMNS FROM threads LIKE 'empresa_id'");
    $threadsHasEmpresa = ($colTh && $colTh->num_rows > 0);
    $colTe = $mysqli->query("SHOW COLUMNS FROM thread_entries LIKE 'empresa_id'");
    $entriesHasEmpresa = ($colTe && $colTe->num_rows > 0);
}

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

// AJAX: búsqueda de usuarios (para cambiar propietario sin listar todos)
if (isset($_GET['action']) && $_GET['action'] === 'user_search') {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['staff_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
    if (!roleHasPermission('ticket.edit')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        exit;
    }

    $q = trim((string)($_GET['q'] ?? ''));
    if ($q === '' || mb_strlen($q) < 2) {
        echo json_encode(['ok' => true, 'items' => []]);
        exit;
    }

    $like = '%' . $q . '%';
    $items = [];
    $stmtU = $mysqli->prepare(
        "SELECT id, firstname, lastname, email\n"
        . "FROM users\n"
        . "WHERE empresa_id = ? AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR CONCAT(firstname, ' ', lastname) LIKE ?)\n"
        . "ORDER BY firstname, lastname\n"
        . "LIMIT 20"
    );
    if ($stmtU) {
        $stmtU->bind_param('issss', $eid, $like, $like, $like, $like);
        if ($stmtU->execute()) {
            $res = $stmtU->get_result();
            while ($res && ($u = $res->fetch_assoc())) {
                $items[] = [
                    'id' => (int)($u['id'] ?? 0),
                    'name' => trim((string)($u['firstname'] ?? '') . ' ' . (string)($u['lastname'] ?? '')),
                    'email' => (string)($u['email'] ?? ''),
                ];
            }
        }
    }

    echo json_encode(['ok' => true, 'items' => $items]);
    exit;
}

// AJAX: vista previa de un ticket (último mensaje)
if (isset($_GET['action']) && $_GET['action'] === 'ticket_preview') {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['staff_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $tid = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($tid <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid ticket']);
        exit;
    }

    $stmt = $mysqli->prepare(
        "SELECT t.id, t.ticket_number, t.subject, t.updated, t.created,\n"
        . " u.firstname AS user_first, u.lastname AS user_last, u.email AS user_email\n"
        . "FROM tickets t\n"
        . "JOIN users u ON u.id = t.user_id\n"
        . "WHERE t.id = ? AND t.empresa_id = ?\n"
        . "LIMIT 1"
    );
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'DB error']);
        exit;
    }
    $stmt->bind_param('ii', $tid, $eid);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Not found']);
        exit;
    }

    $threadId = 0;
    $stmtTh = $mysqli->prepare('SELECT th.id FROM threads th INNER JOIN tickets t ON t.id = th.ticket_id WHERE th.ticket_id = ? AND t.empresa_id = ? LIMIT 1');
    if ($stmtTh) {
        $stmtTh->bind_param('ii', $tid, $eid);
        if ($stmtTh->execute()) {
            $row = $stmtTh->get_result()->fetch_assoc();
            $threadId = (int)($row['id'] ?? 0);
        }
    }

    $previewWhen = $ticket['updated'] ?: $ticket['created'];
    $previewIsInternal = 0;
    $previewAuthor = '';
    $entriesOut = [];

    if ($threadId > 0) {
        $stmtE = $mysqli->prepare(
            "SELECT te.id, te.staff_id, te.user_id, te.is_internal, te.body, te.created,\n"
            . " s.firstname AS staff_first, s.lastname AS staff_last,\n"
            . " u.firstname AS user_first, u.lastname AS user_last\n"
            . "FROM thread_entries te\n"
            . "LEFT JOIN staff s ON s.id = te.staff_id\n"
            . "LEFT JOIN users u ON u.id = te.user_id\n"
            . "WHERE te.thread_id = ?\n"
            . "ORDER BY te.created DESC, te.id DESC\n"
            . "LIMIT 8"
        );
        if ($stmtE) {
            $stmtE->bind_param('i', $threadId);
            if ($stmtE->execute()) {
                $res = $stmtE->get_result();
                $raw = [];
                while ($res && ($e = $res->fetch_assoc())) {
                    $raw[] = $e;
                }
                $raw = array_reverse($raw);

                foreach ($raw as $e) {
                    $author = '';
                    $isStaff = false;
                    if (!empty($e['staff_id'])) {
                        $author = trim((string)($e['staff_first'] ?? '') . ' ' . (string)($e['staff_last'] ?? ''));
                        $isStaff = true;
                    } elseif (!empty($e['user_id'])) {
                        $author = trim((string)($e['user_first'] ?? '') . ' ' . (string)($e['user_last'] ?? ''));
                    }

                    $bodyHtml = (string)($e['body'] ?? '');
                    $text = trim(html_entity_decode(strip_tags($bodyHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if (mb_strlen($text) > 900) {
                        $text = mb_substr($text, 0, 900) . '…';
                    }

                    $entriesOut[] = [
                        'id' => (int)($e['id'] ?? 0),
                        'author' => $author,
                        'when' => (string)($e['created'] ?? ''),
                        'is_internal' => (int)($e['is_internal'] ?? 0),
                        'is_staff' => $isStaff ? 1 : 0,
                        'text' => $text,
                    ];
                }

                if (!empty($raw)) {
                    $last = $raw[count($raw) - 1];
                    $previewWhen = $last['created'] ?: $previewWhen;
                    $previewIsInternal = (int)($last['is_internal'] ?? 0);
                    $author = '';
                    if (!empty($last['staff_id'])) {
                        $author = trim((string)($last['staff_first'] ?? '') . ' ' . (string)($last['staff_last'] ?? ''));
                    } elseif (!empty($last['user_id'])) {
                        $author = trim((string)($last['user_first'] ?? '') . ' ' . (string)($last['user_last'] ?? ''));
                    }
                    $previewAuthor = $author;
                }
            }
        }
    }

    $clientName = trim((string)($ticket['user_first'] ?? '') . ' ' . (string)($ticket['user_last'] ?? ''));
    if ($clientName === '') $clientName = (string)($ticket['user_email'] ?? '');

    echo json_encode([
        'ok' => true,
        'ticket' => [
            'id' => (int)$ticket['id'],
            'ticket_number' => (string)($ticket['ticket_number'] ?? ''),
            'subject' => (string)($ticket['subject'] ?? ''),
            'client' => $clientName,
            'when' => (string)$previewWhen,
            'author' => (string)$previewAuthor,
            'is_internal' => (int)$previewIsInternal,
            'entries' => $entriesOut,
        ]
    ]);
    exit;
}

// Abrir nuevo ticket (tickets.php?a=open&uid=X)
if (isset($_GET['a']) && $_GET['a'] === 'open' && isset($_SESSION['staff_id'])) {
    $open_uid = isset($_GET['uid']) && is_numeric($_GET['uid']) ? (int) $_GET['uid'] : 0;
    $open_errors = [];
    $preSelectedUser = null;

    if (!roleHasPermission('ticket.create')) {
        $open_errors[] = 'No tienes permisos para crear tickets.';
    }
    if ($open_uid > 0) {
        $stmt = $mysqli->prepare("SELECT id, firstname, lastname, email FROM users WHERE empresa_id = ? AND id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('ii', $eid, $open_uid);
            $stmt->execute();
            $res = $stmt->get_result();
            $preSelectedUser = $res ? $res->fetch_assoc() : null;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'open') {
        if (!roleHasPermission('ticket.create')) {
            $open_errors[] = 'No tienes permisos para crear tickets.';
        }
        if (!isset($_POST['csrf_token']) || !Auth::validateCSRF($_POST['csrf_token'])) {
            $open_errors[] = 'Token de seguridad inválido.';
        } else {
            $user_id = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
            $subject = trim($_POST['subject'] ?? '');
            $body = trim($_POST['body'] ?? '');
            $dept_id = isset($_POST['dept_id']) && is_numeric($_POST['dept_id']) ? (int) $_POST['dept_id'] : 0;
            $priority_id = isset($_POST['priority_id']) && is_numeric($_POST['priority_id']) ? (int) $_POST['priority_id'] : 2;
            $staff_id = isset($_POST['staff_id']) && is_numeric($_POST['staff_id']) ? (int) $_POST['staff_id'] : null;
            if ($staff_id === 0) $staff_id = null;
            $topic_id = isset($_POST['topic_id']) && is_numeric($_POST['topic_id']) ? (int) $_POST['topic_id'] : 0;

            $open_hasTopics = false;
            $open_topicsCount = 0;
            $checkTopics = $mysqli->query("SHOW TABLES LIKE 'help_topics'");
            if ($checkTopics && $checkTopics->num_rows > 0) {
                $open_hasTopics = true;
                $stmtCntTopics = $mysqli->prepare('SELECT COUNT(*) AS c FROM help_topics WHERE empresa_id = ? AND is_active = 1');
                if ($stmtCntTopics) {
                    $stmtCntTopics->bind_param('i', $eid);
                    if ($stmtCntTopics->execute()) {
                        $rc = $stmtCntTopics->get_result();
                        if ($rc && ($rr = $rc->fetch_assoc())) {
                            $open_topicsCount = (int)($rr['c'] ?? 0);
                        }
                    }
                }
            }

            // Si se seleccionó un tema, el departamento se toma del tema (si es válido)
            if ($topic_id > 0) {
                $stmtTopicDept = $mysqli->prepare('SELECT dept_id FROM help_topics WHERE empresa_id = ? AND id = ? AND is_active = 1 LIMIT 1');
                if ($stmtTopicDept) {
                    $stmtTopicDept->bind_param('ii', $eid, $topic_id);
                    if ($stmtTopicDept->execute()) {
                        $tr = $stmtTopicDept->get_result()->fetch_assoc();
                        $deptFromTopic = (int) ($tr['dept_id'] ?? 0);
                        if ($deptFromTopic > 0) {
                            $dept_id = $deptFromTopic;
                        }
                    }
                }
            }

            // Si no se determinó dept, usar General (fallback) para evitar errores en el alta.
            if ($dept_id <= 0 && $generalDeptId > 0) {
                $dept_id = $generalDeptId;
            }

            // Asignación automática por departamento (si no se eligió agente)
            if ($staff_id === null && $dept_id > 0) {
                $hasDeptDefaultStaff = false;
                $chkCol = $mysqli->query("SHOW COLUMNS FROM departments LIKE 'default_staff_id'");
                if ($chkCol && $chkCol->num_rows > 0) $hasDeptDefaultStaff = true;

                if ($hasDeptDefaultStaff) {
                    $defaultStaffId = 0;
                    $stmtDef = $mysqli->prepare('SELECT default_staff_id FROM departments WHERE empresa_id = ? AND id = ? AND is_active = 1 LIMIT 1');
                    if ($stmtDef) {
                        $stmtDef->bind_param('ii', $eid, $dept_id);
                        if ($stmtDef->execute()) {
                            $defaultStaffId = (int)($stmtDef->get_result()->fetch_assoc()['default_staff_id'] ?? 0);
                        }
                    }

                    if ($defaultStaffId > 0) {
                        $allowed = $staffBelongsToDept((int)$defaultStaffId, (int)$dept_id, (int)$generalDeptId);
                        if ($allowed) {
                            $staff_id = $defaultStaffId;
                            // Forzar que se ejecute el bloque de notificación más abajo (simula asignación manual)
                            $forceNotifyDeptDefault = true;
                        } else {
                            addLog('ticket_dept_default_not_allowed', 'El agente por defecto no pertenece al departamento', 'ticket', null, 'staff', $defaultStaffId);
                        }
                    }
                }
            }

            if ($user_id <= 0) $open_errors[] = 'Seleccione un usuario.';
            if ($subject === '') $open_errors[] = 'El asunto es obligatorio.';
            if ($open_hasTopics && $open_topicsCount > 0 && $topic_id <= 0) $open_errors[] = 'Seleccione un tema.';
            if ($dept_id <= 0) $open_errors[] = 'No se pudo determinar el departamento del ticket.';

            $maxOpenTicketsSetting = (int)getAppSetting('tickets.max_open_tickets', '0');
            if ($maxOpenTicketsSetting > 0 && $user_id > 0) {
                $hasClosedCol = false;
                $c = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'closed'");
                if ($c && $c->num_rows > 0) $hasClosedCol = true;

                if ($hasClosedCol) {
                    $stmtCnt = $mysqli->prepare('SELECT COUNT(*) AS cnt FROM tickets WHERE empresa_id = ? AND user_id = ? AND closed IS NULL');
                } else {
                    $stmtCnt = $mysqli->prepare('SELECT COUNT(*) AS cnt FROM tickets WHERE empresa_id = ? AND user_id = ?');
                }
                if ($stmtCnt) {
                    $stmtCnt->bind_param('ii', $eid, $user_id);
                    $stmtCnt->execute();
                    $cntRow = $stmtCnt->get_result()->fetch_assoc();
                    $openCount = (int)($cntRow['cnt'] ?? 0);
                    if ($openCount >= $maxOpenTicketsSetting) {
                        $open_errors[] = 'Este usuario alcanzó el máximo de tickets abiertos.';
                    }
                }
            }

            // Validar asignación inicial por departamento (regla exacta)
            if ($dept_id > 0 && $staff_id !== null) {
                $allowed = $staffBelongsToDept((int)$staff_id, (int)$dept_id, (int)$generalDeptId);
                if (!$allowed) {
                    $open_errors[] = 'El agente seleccionado no pertenece al departamento del ticket.';
                }
            }

            if (empty($open_errors)) {
                $generateTicketNumberFromFormat = function ($format) use ($mysqli) {
                    $format = trim((string)$format);
                    if ($format === '') $format = '######';

                    $build = function ($fmt) {
                        $out = '';
                        $len = strlen($fmt);
                        for ($i = 0; $i < $len; $i++) {
                            $ch = $fmt[$i];
                            if ($ch === '#') {
                                $out .= (string)random_int(0, 9);
                            } else {
                                $out .= $ch;
                            }
                        }
                        return $out;
                    };

                    for ($i = 0; $i < 30; $i++) {
                        $num = $build($format);
                        $stmtChk = $mysqli->prepare('SELECT id FROM tickets WHERE empresa_id = ? AND ticket_number = ? LIMIT 1');
                        if (!$stmtChk) return $num;
                        $stmtChk->bind_param('is', $eid, $num);
                        $stmtChk->execute();
                        $row = $stmtChk->get_result()->fetch_assoc();
                        if (!$row) return $num;
                    }

                    return 'TKT-' . date('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                };

                $generateTicketNumberFromSequence = function ($sequenceId) use ($mysqli) {
                    $sequenceId = (int)$sequenceId;
                    if ($sequenceId <= 0) return null;

                    $chkSeq = $mysqli->query("SHOW TABLES LIKE 'sequences'");
                    if (!$chkSeq || $chkSeq->num_rows === 0) return null;

                    $mysqli->query('START TRANSACTION');
                    $stmtSeq = $mysqli->prepare('SELECT next, increment, padding FROM sequences WHERE id = ? FOR UPDATE');
                    if (!$stmtSeq) {
                        $mysqli->query('ROLLBACK');
                        return null;
                    }
                    $stmtSeq->bind_param('i', $sequenceId);
                    $stmtSeq->execute();
                    $seqData = $stmtSeq->get_result()->fetch_assoc();
                    if (!$seqData) {
                        $mysqli->query('ROLLBACK');
                        return null;
                    }

                    $current = (int)($seqData['next'] ?? 1);
                    $increment = (int)($seqData['increment'] ?? 1);
                    $padding = (int)($seqData['padding'] ?? 0);
                    $next = $current + $increment;

                    $stmtUpd = $mysqli->prepare('UPDATE sequences SET next = ?, updated = NOW() WHERE id = ?');
                    if (!$stmtUpd) {
                        $mysqli->query('ROLLBACK');
                        return null;
                    }
                    $stmtUpd->bind_param('ii', $next, $sequenceId);
                    $stmtUpd->execute();
                    $mysqli->query('COMMIT');

                    if ($padding > 0) {
                        return str_pad((string)$current, $padding, '0', STR_PAD_LEFT);
                    }
                    return (string)$current;
                };

                $ticketSequenceId = (string)getAppSetting('tickets.ticket_sequence_id', '0');
                $ticket_number = null;

                if ($ticketSequenceId !== '0') {
                    $ticket_number = $generateTicketNumberFromSequence((int)$ticketSequenceId);
                }

                if ($ticket_number === null) {
                    $ticketNumberFormat = (string)getAppSetting('tickets.ticket_number_format', '######');
                    $ticket_number = $generateTicketNumberFromFormat($ticketNumberFormat);
                }
                error_log('[tickets] INSERT tickets via scp/modules/tickets.php open uri=' . ($_SERVER['REQUEST_URI'] ?? '') . ' staff_session=' . (string)($_SESSION['staff_id'] ?? '') . ' user_id=' . (string)$user_id . ' dept_id=' . (string)$dept_id);
                $hasTopicCol = false;
                $hasTopicsTable = false;
                $c = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'topic_id'");
                if ($c && $c->num_rows > 0) $hasTopicCol = true;
                $t = $mysqli->query("SHOW TABLES LIKE 'help_topics'");
                if ($t && $t->num_rows > 0) $hasTopicsTable = true;

                $defaultStatusId = (int)getAppSetting('tickets.default_ticket_status_id', '1');
                if ($defaultStatusId <= 0) $defaultStatusId = 1;

                if ($hasTopicCol && $hasTopicsTable && $topic_id > 0) {
                    $stmt = $mysqli->prepare("INSERT INTO tickets (empresa_id, ticket_number, user_id, staff_id, dept_id, topic_id, status_id, priority_id, subject, created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    // tipos: s (ticket_number), i (user_id), i (staff_id), i (dept_id), i (topic_id), i (status_id), i (priority_id), s (subject)
                    $stmt->bind_param('isiiiiiis', $eid, $ticket_number, $user_id, $staff_id, $dept_id, $topic_id, $defaultStatusId, $priority_id, $subject);
                } else {
                    $stmt = $mysqli->prepare("INSERT INTO tickets (empresa_id, ticket_number, user_id, staff_id, dept_id, status_id, priority_id, subject, created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    // tipos: s (ticket_number), i (user_id), i (staff_id), i (dept_id), i (status_id), i (priority_id), s (subject)
                    $stmt->bind_param('isiiiiiis', $eid, $ticket_number, $user_id, $staff_id, $dept_id, $defaultStatusId, $priority_id, $subject);
                }
                if ($stmt->execute()) {
                    $new_tid = (int) $mysqli->insert_id;
                    if ($threadsHasEmpresa) {
                        $stmtThread = $mysqli->prepare('INSERT INTO threads (empresa_id, ticket_id, created) VALUES (?, ?, NOW())');
                    } else {
                        $stmtThread = $mysqli->prepare('INSERT INTO threads (ticket_id, created) VALUES (?, NOW())');
                    }
                    if ($stmtThread) {
                        if ($threadsHasEmpresa) {
                            $stmtThread->bind_param('ii', $eid, $new_tid);
                        } else {
                            $stmtThread->bind_param('i', $new_tid);
                        }
                        $stmtThread->execute();
                    }
                    $thread_id = (int) $mysqli->insert_id;
                    if ($body !== '') {
                        $staff_id_entry = (int) ($_SESSION['staff_id'] ?? 0);
                        if ($entriesHasEmpresa) {
                            $stmtE = $mysqli->prepare("INSERT INTO thread_entries (empresa_id, thread_id, staff_id, body, is_internal, created) VALUES (?, ?, ?, ?, 0, NOW())");
                            $stmtE->bind_param('iiis', $eid, $thread_id, $staff_id_entry, $body);
                        } else {
                            $stmtE = $mysqli->prepare("INSERT INTO thread_entries (thread_id, staff_id, body, is_internal, created) VALUES (?, ?, ?, 0, NOW())");
                            $stmtE->bind_param('iis', $thread_id, $staff_id_entry, $body);
                        }
                        $stmtE->execute();
                    }

                    // Notificación + correo al agente asignado (si se creó con asignación o por departamento por defecto)
                    if (($staff_id !== null && (int)$staff_id > 0) || ($forceNotifyDeptDefault ?? false)) {
                        $val = (int) ($staff_id ?? 0);
                        if ($forceNotifyDeptDefault && $val <= 0) $val = (int)($defaultStaffId ?? 0);
                        if ($val <= 0) {
                            // No se puede notificar, omitir
                        } else {
                            addLog('ticket_assigned', 'Asignación inicial en creación de ticket' . ($forceNotifyDeptDefault ? ' (dept default)' : ''), 'ticket', $new_tid, 'staff', $val);

                            // Notificación en BD
                            $message = 'Se te asignó el ticket ' . (string)$ticket_number . ': ' . (string)$subject;
                            $type = 'ticket_assigned';
                            $stmtN = $mysqli->prepare('INSERT INTO notifications (staff_id, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
                            if ($stmtN) {
                                $stmtN->bind_param('issi', $val, $message, $type, $new_tid);
                                $stmtN->execute();
                            } else {
                                addLog('ticket_assign_notification_failed', 'No se pudo preparar INSERT notifications', 'ticket', $new_tid, 'staff', $val);
                            }

                            // Correo al agente usando el mismo formato que la asignación manual
                            $stmtE = $mysqli->prepare('SELECT firstname, lastname, email FROM staff WHERE empresa_id = ? AND id = ? AND is_active = 1 LIMIT 1');
                            if ($stmtE) {
                                $stmtE->bind_param('ii', $eid, $val);
                                if ($stmtE->execute()) {
                                    $r = $stmtE->get_result()->fetch_assoc();
                                    if ($r && !empty($r['email'])) {
                                        $emailEnabled = ((string)getAppSetting('staff.' . (int)$val . '.email_ticket_assigned', '1') === '1');
                                        if ($emailEnabled) {
                                            $to = trim((string)$r['email']);
                                            if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                                                $staffName = trim((string)($r['firstname'] ?? '') . ' ' . (string)($r['lastname'] ?? ''));
                                                if ($staffName === '') $staffName = 'Agente';
                                                $viewUrl = (defined('APP_URL') ? APP_URL : '') . '/upload/scp/tickets.php?id=' . (int)$new_tid;
                                                $deptLabel = '';
                                                foreach ($open_departments as $openDept) {
                                                    if ((int)($openDept['id'] ?? 0) === (int)$dept_id) {
                                                        $deptLabel = (string)($openDept['name'] ?? '');
                                                        break;
                                                    }
                                                }
                                                $subj = '[Asignación] ' . (string)$ticket_number . ' - ' . (string)$subject;
                                                $bodyHtml = '<div style="font-family: Segoe UI, sans-serif; max-width: 700px; margin: 0 auto;">'
                                                    . '<h2 style="color:#1e3a5f; margin: 0 0 8px;">Se te asignó un ticket</h2>'
                                                    . '<p style="color:#475569; margin: 0 0 12px;">Hola <strong>' . htmlspecialchars($staffName) . '</strong>, se te asignó el siguiente ticket:</p>'
                                                    . '<table style="width:100%; border-collapse: collapse; margin: 12px 0;">'
                                                    . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee;"><strong>Número:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">' . htmlspecialchars((string)$ticket_number) . '</td></tr>'
                                                    . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee;"><strong>Asunto:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">' . htmlspecialchars((string)$subject) . '</td></tr>'
                                                    . '<tr><td style="padding: 6px 0;"><strong>Departamento:</strong></td><td style="padding: 6px 0;">' . htmlspecialchars($deptLabel) . '</td></tr>'
                                                    . '</table>'
                                                    . '<p style="margin: 14px 0 0;"><a href="' . htmlspecialchars($viewUrl) . '" style="display:inline-block; background:#2563eb; color:#fff; padding:10px 16px; text-decoration:none; border-radius:8px;">Ver ticket</a></p>'
                                                    . '<p style="color:#94a3b8; font-size:12px; margin-top: 14px;">' . htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets') . '</p>'
                                                    . '</div>';
                                                $bodyText = 'Se te asignó un ticket: ' . (string)$ticket_number . "\n" . 'Asunto: ' . (string)$subject . "\n" . 'Ver: ' . $viewUrl;
                                                $mailOk = Mailer::send($to, $subj, $bodyHtml, $bodyText);
                                                if (!$mailOk) {
                                                    $err = (string)(Mailer::$lastError ?? 'Error desconocido');
                                                    addLog('ticket_assign_email_failed', $err, 'ticket', $new_tid, 'staff', $val);
                                                }
                                            }
                                        }
                                    } else {
                                        addLog('ticket_assign_email_missing', 'Agente sin email válido', 'ticket', $new_tid, 'staff', $val);
                                    }
                                } else {
                                    addLog('ticket_assign_email_lookup_failed', 'No se pudo ejecutar SELECT staff(email)', 'ticket', $new_tid, 'staff', $val);
                                }
                            } else {
                                addLog('ticket_assign_email_lookup_failed', 'No se pudo preparar SELECT staff(email)', 'ticket', $new_tid, 'staff', $val);
                            }
                        }
                    }

                    header('Location: tickets.php?id=' . $new_tid . '&msg=created');
                    exit;
                }
                $open_errors[] = 'Error al crear el ticket.';
            }
        }
    }

    $open_departments = [];
    $stmtOpenDept = $mysqli->prepare("SELECT id, name FROM departments WHERE empresa_id = ? AND is_active = 1 ORDER BY name");
    if ($stmtOpenDept) {
        $stmtOpenDept->bind_param('i', $eid);
        if ($stmtOpenDept->execute()) {
            $r = $stmtOpenDept->get_result();
            if ($r) while ($row = $r->fetch_assoc()) $open_departments[] = $row;
        }
    }
    $open_priorities = [];
    $r = $mysqli->query("SELECT id, name FROM priorities ORDER BY level");
    if ($r) while ($row = $r->fetch_assoc()) $open_priorities[] = $row;
    $open_staff = [];
    // Use staff_departments if available, otherwise fallback to legacy dept_id
    if ($hasStaffDepartmentsTable) {
        $stmtStaff = $mysqli->prepare(
            'SELECT DISTINCT s.id, s.firstname, s.lastname, COALESCE(NULLIF(s.dept_id, 0), ?) AS dept_id
             FROM staff s
             WHERE s.empresa_id = ? AND s.is_active = 1 
             ORDER BY s.firstname, s.lastname'
        );
    } else {
        $stmtStaff = $mysqli->prepare('SELECT id, firstname, lastname, COALESCE(NULLIF(dept_id, 0), ?) AS dept_id FROM staff WHERE empresa_id = ? AND is_active = 1 ORDER BY firstname, lastname');
    }
    if ($stmtStaff) {
        if ($hasStaffDepartmentsTable) {
            $stmtStaff->bind_param('ii', $generalDeptId, $eid);
        } else {
            $stmtStaff->bind_param('ii', $generalDeptId, $eid);
        }
        if ($stmtStaff->execute()) {
            $r = $stmtStaff->get_result();
            if ($r) while ($row = $r->fetch_assoc()) $open_staff[] = $row;
        }
    }

    // Temas (opcional)
    $open_hasTopics = false;
    $open_topics = [];
    $checkTopics = $mysqli->query("SHOW TABLES LIKE 'help_topics'");
    if ($checkTopics && $checkTopics->num_rows > 0) {
        $open_hasTopics = true;
        $stmt = $mysqli->prepare('SELECT id, name, dept_id FROM help_topics WHERE empresa_id = ? AND is_active = 1 ORDER BY name');
        if ($stmt) {
            $stmt->bind_param('i', $eid);
            if ($stmt->execute()) {
                $r = $stmt->get_result();
                while ($r && ($row = $r->fetch_assoc())) {
                    $open_topics[] = $row;
                }
            }
        }
    }

    // Búsqueda de usuario (como osTicket): no listar todos por defecto
    $open_user_query = trim($_GET['uq'] ?? '');
    $open_user_results = [];
    if ($open_user_query !== '') {
        $term = '%' . $open_user_query . '%';
        $stmt = $mysqli->prepare(
            "SELECT id, firstname, lastname, email, phone
             FROM users
             WHERE empresa_id = ? AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR phone LIKE ?)
             ORDER BY firstname, lastname
             LIMIT 25"
        );
        $stmt->bind_param('issss', $eid, $term, $term, $term, $term);
        $stmt->execute();
        $open_user_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    require __DIR__ . '/ticket-open.inc.php';
    return;
}

// Vista de un ticket concreto (tickets.php?id=X)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $tid = (int) $_GET['id'];

    // Firma del staff (si existe columna signature)
    $staff_signature = '';
    $staff_has_signature = false;
    $current_staff_id = (int) ($_SESSION['staff_id'] ?? 0);
    if ($current_staff_id > 0) {
        $stmtSig = $mysqli->prepare('SELECT * FROM staff WHERE empresa_id = ? AND id = ? LIMIT 1');
        $stmtSig->bind_param('ii', $eid, $current_staff_id);
        $stmtSig->execute();
        $staffRow = $stmtSig->get_result()->fetch_assoc();
        if ($staffRow && array_key_exists('signature', $staffRow)) {
            $staff_signature = trim((string)($staffRow['signature'] ?? ''));
            $staff_has_signature = $staff_signature !== '';
        }
    }

    // Cargar ticket con usuario, estado, prioridad, departamento, asignado
    $hasTopicCol = false;
    $hasTopicsTable = false;
    $c = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'topic_id'");
    if ($c && $c->num_rows > 0) $hasTopicCol = true;
    $t = $mysqli->query("SHOW TABLES LIKE 'help_topics'");
    if ($t && $t->num_rows > 0) $hasTopicsTable = true;

    $topicSelect = $hasTopicCol && $hasTopicsTable
        ? ", ht.name AS topic_name"
        : "";
    $topicJoin = $hasTopicCol && $hasTopicsTable
        ? " LEFT JOIN help_topics ht ON ht.id = t.topic_id"
        : "";

    $stmt = $mysqli->prepare(
        "SELECT t.*, u.firstname AS user_first, u.lastname AS user_last, u.email AS user_email,
         s.firstname AS staff_first, s.lastname AS staff_last, s.email AS staff_email,
         d.name AS dept_name, ts.name AS status_name, ts.color AS status_color,
         p.name AS priority_name, p.color AS priority_color"
         . $topicSelect .
        " FROM tickets t
         JOIN users u ON t.user_id = u.id
         LEFT JOIN staff s ON t.staff_id = s.id"
         . $topicJoin .
        " JOIN departments d ON t.dept_id = d.id
         JOIN ticket_status ts ON t.status_id = ts.id
         JOIN priorities p ON t.priority_id = p.id
         WHERE t.id = ? AND t.empresa_id = ?"
    );
    $stmt->bind_param('ii', $tid, $eid);
    $stmt->execute();
    $res = $stmt->get_result();
    $ticketView = $res ? $res->fetch_assoc() : null;

    if ($ticketView) {
        $sidForSeen = (int)($_SESSION['staff_id'] ?? 0);
        if ($sidForSeen > 0) {
            $tk = 'tickets_seen_' . $sidForSeen;
            if (!isset($_SESSION[$tk]) || !is_array($_SESSION[$tk])) $_SESSION[$tk] = [];
            $_SESSION[$tk][] = (int)$tid;
            $_SESSION[$tk] = array_values(array_slice(array_unique(array_map('intval', $_SESSION[$tk])), -500));
            $seenIds[(int)$tid] = true;

            if (isset($mysqli) && $mysqli) {
                $stmtSeenIns = $mysqli->prepare('INSERT INTO staff_ticket_seen (staff_id, ticket_id, seen_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE seen_at = VALUES(seen_at)');
                if ($stmtSeenIns) {
                    $stmtSeenIns->bind_param('ii', $sidForSeen, $tid);
                    $stmtSeenIns->execute();
                }
            }
        }

        // Asegurar que exista un thread para este ticket
        $stmt = $mysqli->prepare("SELECT id FROM threads WHERE ticket_id = ?" . ($threadsHasEmpresa ? " AND empresa_id = ?" : ""));
        if ($threadsHasEmpresa) {
            $stmt->bind_param('ii', $tid, $eid);
        } else {
            $stmt->bind_param('i', $tid);
        }
        $stmt->execute();
        $threadRow = $stmt->get_result()->fetch_assoc();
        if (!$threadRow) {
            if ($threadsHasEmpresa) {
                $stmtThread = $mysqli->prepare('INSERT INTO threads (empresa_id, ticket_id, created) VALUES (?, ?, NOW())');
            } else {
                $stmtThread = $mysqli->prepare('INSERT INTO threads (ticket_id, created) VALUES (?, NOW())');
            }
            if ($stmtThread) {
                if ($threadsHasEmpresa) {
                    $stmtThread->bind_param('ii', $eid, $tid);
                } else {
                    $stmtThread->bind_param('i', $tid);
                }
                $stmtThread->execute();
            }
            $threadRow = ['id' => $mysqli->insert_id];
        }
        $thread_id = (int) $threadRow['id'];

        // Descarga de adjuntos
        if (isset($_GET['download']) && is_numeric($_GET['download'])) {
            $aid = (int) $_GET['download'];
            $stmtA = $mysqli->prepare(
                "SELECT a.id, a.original_filename, a.mimetype, a.path\n"
                . "FROM attachments a\n"
                . "JOIN thread_entries te ON te.id = a.thread_entry_id\n"
                . "WHERE a.id = ? AND te.thread_id = ?\n"
                . "LIMIT 1"
            );
            $stmtA->bind_param('ii', $aid, $thread_id);
            $stmtA->execute();
            $att = $stmtA->get_result()->fetch_assoc();
            if (!$att) {
                http_response_code(404);
                exit('Archivo no encontrado');
            }
            $rel = (string) ($att['path'] ?? '');
            $baseUpload = dirname(__DIR__, 2); // upload/scp
            $baseRoot = dirname(__DIR__, 3);   // upload
            $full = $baseRoot . '/' . ltrim($rel, '/');
            if (($rel === '' || !is_file($full)) && $rel !== '') {
                $legacy = $baseUpload . '/' . ltrim($rel, '/');
                if (is_file($legacy)) {
                    $full = $legacy;
                }
            }
            if ($rel === '' || !is_file($full)) {
                http_response_code(404);
                exit('Archivo no encontrado');
            }

            $filename = (string) ($att['original_filename'] ?? 'archivo');
            $mime = (string) ($att['mimetype'] ?? 'application/octet-stream');
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . (string) filesize($full));
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
            header('X-Content-Type-Options: nosniff');
            readfile($full);
            exit;
        }

        // Info de cierre (para mostrar debajo del hilo)
        $ticket_closed_info = null;
        if (!empty($ticketView['closed'])) {
            $closed_by = '';
            $closed_at = $ticketView['closed'];
            // Tomar el último staff que escribió en el hilo antes (o cerca) del cierre
            $stmtCb = $mysqli->prepare(
                "SELECT te.staff_id, s.firstname, s.lastname\n"
                . "FROM thread_entries te\n"
                . "JOIN staff s ON s.id = te.staff_id\n"
                . "WHERE te.thread_id = ? AND te.staff_id IS NOT NULL AND te.created <= ?\n"
                . "ORDER BY te.created DESC\n"
                . "LIMIT 1"
            );
            if ($stmtCb) {
                $stmtCb->bind_param('is', $thread_id, $closed_at);
                if ($stmtCb->execute()) {
                    $r = $stmtCb->get_result()->fetch_assoc();
                    if ($r) {
                        $closed_by = trim(($r['firstname'] ?? '') . ' ' . ($r['lastname'] ?? ''));
                    }
                }
            }
            if ($closed_by === '') {
                $closed_by = trim((string)($ticketView['staff_first'] ?? '') . ' ' . (string)($ticketView['staff_last'] ?? ''));
            }
            if ($closed_by === '') {
                $closed_by = 'Agente';
            }
            $ticket_closed_info = [
                'by' => $closed_by,
                'at' => $closed_at,
                'status' => (string)($ticketView['status_name'] ?? 'Cerrado'),
            ];
        }

        // Acciones rápidas: estado, asignar, eliminar, etc.
        $action = $_GET['action'] ?? $_POST['action'] ?? null;
        $csrfOk = true;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['owner', 'block_email', 'delete', 'merge', 'link', 'collab_add', 'transfer'], true)) {
            $csrfOk = isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token']);
        }
        if ($action !== null && isset($_SESSION['staff_id']) && $csrfOk) {
            $ok = false;
            $msg = '';
            if ($action === 'status' && isset($_GET['status_id']) && is_numeric($_GET['status_id'])) {
                $sid = (int) $_GET['status_id'];
                
                // Verificar si es un estado de cerrado
                $isClosingStatus = false;
                $statusLabel = '';
                $stmtSt = $mysqli->prepare('SELECT name FROM ticket_status WHERE id = ? LIMIT 1');
                if ($stmtSt) {
                    $stmtSt->bind_param('i', $sid);
                    if ($stmtSt->execute()) {
                        $stRow = $stmtSt->get_result()->fetch_assoc();
                        $stName = strtolower(trim((string)($stRow['name'] ?? '')));
                        $statusLabel = trim((string)($stRow['name'] ?? ''));
                        if ($stName !== '' && (str_contains($stName, 'cerrad') || str_contains($stName, 'resuelt') || str_contains($stName, 'closed') || str_contains($stName, 'resolved'))) {
                            $isClosingStatus = true;
                        }
                    }
                }
                
                if ($isClosingStatus) {
                    requireRolePermission('ticket.close', 'tickets.php?id=' . $tid);
                    $stmt = $mysqli->prepare("UPDATE tickets SET status_id = ?, closed = NOW(), updated = NOW() WHERE id = ? AND empresa_id = ?");
                } else {
                    requireRolePermission('ticket.edit', 'tickets.php?id=' . $tid);
                    $stmt = $mysqli->prepare("UPDATE tickets SET status_id = ?, closed = NULL, updated = NOW() WHERE id = ? AND empresa_id = ?");
                }
                $stmt->bind_param('iii', $sid, $tid, $eid);
                $ok = $stmt->execute();
                $msg = 'updated';

                // Si cambió a En Camino (id=2), notificar al usuario
                if ($ok && $sid === 2 && (int)($ticketView['status_id'] ?? 0) !== 2) {
                    $toClient = trim((string)($ticketView['user_email'] ?? ''));
                    if ($toClient !== '' && filter_var($toClient, FILTER_VALIDATE_EMAIL)) {
                        $ticketNo = (string)($ticketView['ticket_number'] ?? ('#' . $tid));
                        $subjClient = '[Ticket En Camino] ' . $ticketNo;
                        $bodyHtmlClient = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
                            . '<h2 style="margin:0 0 10px;color:#1e3a5f;">Técnicos en camino</h2>'
                            . '<p>Estimado usuario, los técnicos ya van en camino para atender su solicitud.</p>'
                            . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . html((string)(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets')) . '</p>'
                            . '</div>';
                        $bodyTextClient = "Estimado usuario, los técnicos ya van en camino para atender su solicitud.";
                        if (function_exists('enqueueEmailJob')) {
                            enqueueEmailJob($toClient, $subjClient, $bodyHtmlClient, $bodyTextClient, [
                                'empresa_id' => (int)$eid,
                                'context_type' => 'ticket_en_camino_client',
                                'context_id' => (int)$tid,
                            ]);
                            if (function_exists('triggerEmailQueueWorkerAsync')) {
                                triggerEmailQueueWorkerAsync();
                            }
                        } else {
                            Mailer::send($toClient, $subjClient, $bodyHtmlClient, $bodyTextClient);
                        }
                        addLog('ticket_en_camino_email', 'Notificación En Camino encolada/enviada al usuario ' . $toClient, 'ticket', $tid);
                    }
                }

                // Si se cierra desde cambio de estado (sin firma), notificar por correo
                if ($ok && $isClosingStatus && empty($ticketView['closed'])) {
                    $ticketNo = (string)($ticketView['ticket_number'] ?? ('#' . $tid));
                    $ticketSubject = trim((string)($ticketView['subject'] ?? 'Ticket'));
                    if ($statusLabel === '') $statusLabel = 'Cerrado';
                    $companyName = (string)(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets');
                    
                    $token = hash_hmac('sha256', (string)$tid, defined('SECRET_KEY') ? SECRET_KEY : 'default-secret');
                    $ticketPdfUrl = rtrim((string)(defined('APP_URL') ? APP_URL : ''), '/') . '/upload/scp/ticket_pdf.php?id=' . (int)$tid . '&t=' . $token;

                    // Generar PDF para adjuntar al correo del cliente
                    $pdfBytesClient = null;
                    try {
                        $projectRootForPdf = realpath(dirname(__DIR__, 3));
                        if ($projectRootForPdf !== false) {
                            if (!class_exists('TicketPdfGenerator')) {
                                $tpgFile = $projectRootForPdf . '/includes/TicketPdfGenerator.php';
                                if (is_file($tpgFile)) require_once $tpgFile;
                            }
                            if (class_exists('TicketPdfGenerator')) {
                                $pdfBytesClient = TicketPdfGenerator::generate((int)$tid, $mysqli, $projectRootForPdf);
                            }
                        }
                    } catch (Throwable $e) {
                        $pdfBytesClient = null;
                    }

                    // Correo al cliente
                    $clientName = trim((string)($ticketView['user_first'] ?? '') . ' ' . (string)($ticketView['user_last'] ?? ''));
                    if ($clientName === '') $clientName = 'Cliente';
                    $clientEmail = strtolower(trim((string)($ticketView['user_email'] ?? '')));
                    $clientPdfUrl = rtrim((string)(defined('APP_URL') ? APP_URL : ''), '/') . '/upload/ticket_pdf.php?id=' . (int)$tid . '&t=' . $token;
                    
                    if ($clientEmail !== '' && filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                        $safeTicketNo = preg_replace('~[^A-Za-z0-9_-]+~', '_', $ticketNo);
                        $clientSubj = '[Ticket cerrado] ' . $ticketNo . ' - ' . $ticketSubject;
                        $clientBodyHtml = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
                            . '<h2 style="margin:0 0 10px;color:#1e3a5f;">Tu ticket fue cerrado</h2>'
                            . '<p>Hola ' . html($clientName) . ',</p>'
                            . '<p>Te informamos que tu ticket <strong>' . html($ticketNo) . '</strong> fue marcado como <strong>' . html($statusLabel) . '</strong>.</p>'
                            . '<p><strong>Asunto:</strong> ' . html($ticketSubject) . '</p>'
                            . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . html($companyName) . '</p>'
                            . '</div>';
                        $clientBodyText = "Hola " . $clientName . ",\n\n"
                            . "Tu ticket " . $ticketNo . " fue marcado como " . $statusLabel . ".\n"
                            . "Asunto: " . $ticketSubject . "\n\n"
                            . $companyName;
                        $clientMailOpts = [
                            'empresa_id'   => (int)$eid,
                            'context_type' => 'ticket_closed_client',
                            'context_id'   => (int)$tid,
                        ];
                        if ($pdfBytesClient !== null) {
                            Mailer::sendWithOptions($clientEmail, $clientSubj, $clientBodyHtml, $clientBodyText, [
                                'attachments' => [[
                                    'filename'    => 'Ticket_' . $safeTicketNo . '.pdf',
                                    'contentType' => 'application/pdf',
                                    'content'     => $pdfBytesClient,
                                ]],
                            ]);
                        } elseif (function_exists('enqueueEmailJob')) {
                            enqueueEmailJob($clientEmail, $clientSubj, $clientBodyHtml, $clientBodyText, $clientMailOpts);
                        } else {
                            Mailer::send($clientEmail, $clientSubj, $clientBodyHtml, $clientBodyText);
                        }
                    }

                    // Correo a agentes configurados en notificaciones
                    $adminRecipients = [];
                    $hasRecipientsTable = $mysqli->query("SHOW TABLES LIKE 'notification_recipients'");
                    if ($hasRecipientsTable && $hasRecipientsTable->num_rows > 0) {
                        $staffHasEmpresa = false;
                        try {
                            $chk = $mysqli->query("SHOW COLUMNS FROM staff LIKE 'empresa_id'");
                            $staffHasEmpresa = ($chk && $chk->num_rows > 0);
                        } catch (Throwable $e) {
                            $staffHasEmpresa = false;
                        }

                        $sqlAdmin = "SELECT s.email FROM notification_recipients nr INNER JOIN staff s ON s.id = nr.staff_id WHERE nr.empresa_id = ? AND s.is_active = 1";
                        if ($staffHasEmpresa) {
                            $sqlAdmin .= " AND s.empresa_id = ?";
                        }
                        $stmtAdmin = $mysqli->prepare($sqlAdmin);
                        if ($stmtAdmin) {
                            if ($staffHasEmpresa) {
                                $stmtAdmin->bind_param('ii', $eid, $eid);
                            } else {
                                $stmtAdmin->bind_param('i', $eid);
                            }
                            if ($stmtAdmin->execute()) {
                                $rsAdmin = $stmtAdmin->get_result();
                                while ($rsAdmin && ($rowAdmin = $rsAdmin->fetch_assoc())) {
                                    $em = strtolower(trim((string)($rowAdmin['email'] ?? '')));
                                    if ($em === '' || !filter_var($em, FILTER_VALIDATE_EMAIL)) continue;
                                    $adminRecipients[$em] = true;
                                }
                            }
                        }
                    }

                    if (!empty($adminRecipients)) {
                        $adminSubj = '[Ticket cerrado] ' . $ticketNo . ' - ' . $ticketSubject;
                        $adminBodyHtml = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
                            . '<h2 style="margin:0 0 10px;color:#1e3a5f;">Ticket cerrado</h2>'
                            . '<p>Un ticket fue cerrado en el sistema.</p>'
                            . '<table style="width:100%;border-collapse:collapse;margin:10px 0 14px;">'
                            . '<tr><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;"><strong>Número:</strong></td><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;">' . html($ticketNo) . '</td></tr>'
                            . '<tr><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;"><strong>Asunto:</strong></td><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;">' . html($ticketSubject) . '</td></tr>'
                            . '<tr><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;"><strong>Estado:</strong></td><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;">' . html($statusLabel) . '</td></tr>'
                            . '<tr><td style="padding:6px 0;"><strong>Firma cliente:</strong></td><td style="padding:6px 0;">No</td></tr>'
                            . '</table>'
                            . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . html($companyName) . '</p>'
                            . '</div>';
                        $adminBodyText = "Ticket cerrado\n\n"
                            . "Numero: " . $ticketNo . "\n"
                            . "Asunto: " . $ticketSubject . "\n"
                            . "Estado: " . $statusLabel . "\n"
                            . "Firma cliente: No\n\n"
                            . $companyName;

                        $pdfAttachment = null;
                        try {
                            $projectRoot = realpath(dirname(__DIR__, 3));
                            if ($projectRoot !== false) {
                                $autoload = $projectRoot . '/vendor/autoload.php';
                                if (is_file($autoload)) {
                                    require_once $autoload;
                                    if (class_exists('Dompdf\\Dompdf')) {
                                        if (!defined('TICKET_PDF_RENDER')) {
                                            define('TICKET_PDF_RENDER', true);
                                        }
                                        $_GET['id'] = (int)$tid;
                                        ob_start();
                                        require __DIR__ . '/../print_ticket.php';
                                        $pdfHtml = (string)ob_get_clean();

                                        $dompdf = new Dompdf\Dompdf([
                                            'isRemoteEnabled' => true,
                                            'isHtml5ParserEnabled' => true,
                                        ]);
                                        $dompdf->loadHtml($pdfHtml, 'UTF-8');
                                        $dompdf->setPaper('A4', 'portrait');
                                        $dompdf->render();
                                        $pdfBin = $dompdf->output();
                                        if (is_string($pdfBin) && $pdfBin !== '') {
                                            $safeTicketNo = preg_replace('~[^A-Za-z0-9_-]+~', '_', (string)$ticketNo);
                                            $pdfAttachment = [
                                                'filename' => 'Ticket_' . $safeTicketNo . '.pdf',
                                                'contentType' => 'application/pdf',
                                                'content' => $pdfBin,
                                            ];
                                        }
                                    }
                                }
                            }
                        } catch (Throwable $e) {
                            $pdfAttachment = null;
                        }

                        foreach (array_keys($adminRecipients) as $adminEmail) {
                            if ($pdfAttachment !== null) {
                                Mailer::sendWithOptions($adminEmail, $adminSubj, $adminBodyHtml, $adminBodyText, [
                                    'attachments' => [$pdfAttachment],
                                ]);
                            } elseif (function_exists('enqueueEmailJob')) {
                                enqueueEmailJob($adminEmail, $adminSubj, $adminBodyHtml, $adminBodyText, [
                                    'empresa_id' => (int)$eid,
                                    'context_type' => 'ticket_closed_admin',
                                    'context_id' => (int)$tid,
                                ]);
                            } else {
                                Mailer::send($adminEmail, $adminSubj, $adminBodyHtml, $adminBodyText);
                            }
                        }
                    }

                    if (function_exists('triggerEmailQueueWorkerAsync')) {
                        triggerEmailQueueWorkerAsync(40);
                    }
                }
            } elseif ($action === 'assign') {
                requireRolePermission('ticket.assign', 'tickets.php?id=' . $tid);
                $staff_id = isset($_GET['staff_id']) ? (int) $_GET['staff_id'] : (isset($_POST['staff_id']) ? (int) $_POST['staff_id'] : null);
                if ($staff_id !== null) {
                    $val = $staff_id === 0 ? null : $staff_id;
                    $previousStaffId = $ticketView['staff_id'] ?? null;

                    // Validar que el agente pertenezca al mismo departamento del ticket (o sea General)
                    $allowed = true;
                    if ($val !== null) {
                        $tdept = (int) ($ticketView['dept_id'] ?? 0);
                        if ($tdept > 0) {
                            $allowed = $staffBelongsToDept((int)$val, $tdept, (int)$generalDeptId);
                        }
                    }

                    if ($allowed) {
                        $stmt = $mysqli->prepare("UPDATE tickets SET staff_id = ?, updated = NOW() WHERE id = ? AND empresa_id = ?");
                        $stmt->bind_param('iii', $val, $tid, $eid);
                        $ok = $stmt->execute();
                        $msg = 'assigned';
                    } else {
                        $ok = false;
                        $msg = 'assigned';
                    }

                    // Notificación en BD (solo si se asignó a alguien y cambió)
                    if ($ok && $val !== null && (string)$previousStaffId !== (string)$val) {
                        $ticketNo = (string)($ticketView['ticket_number'] ?? ('#' . $tid));
                        $message = 'Se te asignó el ticket ' . $ticketNo . ': ' . (string)($ticketView['subject'] ?? '');
                        $type = 'ticket_assigned';
                        $stmtN = $mysqli->prepare('INSERT INTO notifications (staff_id, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
                        if ($stmtN) {
                            $stmtN->bind_param('issi', $val, $message, $type, $tid);
                            $stmtN->execute();
                        }
                    }

                    // Enviar email al agente asignado (solo si se asignó a alguien y cambió)
                    if ($ok && $val !== null && (string)$previousStaffId !== (string)$val) {
                        $stmtS = $mysqli->prepare('SELECT email, firstname, lastname FROM staff WHERE empresa_id = ? AND id = ? AND is_active = 1 LIMIT 1');
                        $stmtS->bind_param('ii', $eid, $val);
                        if ($stmtS->execute()) {
                            $srow = $stmtS->get_result()->fetch_assoc();
                            $to = trim((string)($srow['email'] ?? ''));
                            $emailEnabled = ((string)getAppSetting('staff.' . (int)$val . '.email_ticket_assigned', '1') === '1');
                            if ($emailEnabled && $to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                                $ticketNo = (string)($ticketView['ticket_number'] ?? ('#' . $tid));
                                $subj = '[Asignación] ' . $ticketNo . ' - ' . (string)($ticketView['subject'] ?? 'Ticket');
                                $staffName = trim((string)($srow['firstname'] ?? '') . ' ' . (string)($srow['lastname'] ?? ''));
                                if ($staffName === '') $staffName = 'Agente';
                                $viewUrl = (defined('APP_URL') ? APP_URL : '') . '/upload/scp/tickets.php?id=' . (int) $tid;
                                $bodyHtml = '<div style="font-family: Segoe UI, sans-serif; max-width: 700px; margin: 0 auto;">'
                                    . '<h2 style="color:#1e3a5f; margin: 0 0 8px;">Se te asignó un ticket</h2>'
                                    . '<p style="color:#475569; margin: 0 0 12px;">Hola <strong>' . htmlspecialchars($staffName) . '</strong>, se te asignó el siguiente ticket:</p>'
                                    . '<table style="width:100%; border-collapse: collapse; margin: 12px 0;">'
                                    . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee;"><strong>Número:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">' . htmlspecialchars($ticketNo) . '</td></tr>'
                                    . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee;"><strong>Asunto:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">' . htmlspecialchars((string)($ticketView['subject'] ?? '')) . '</td></tr>'
                                    . '<tr><td style="padding: 6px 0;"><strong>Departamento:</strong></td><td style="padding: 6px 0;">' . htmlspecialchars((string)($ticketView['dept_name'] ?? '')) . '</td></tr>'
                                    . '</table>'
                                    . '<p style="margin: 14px 0 0;"><a href="' . htmlspecialchars($viewUrl) . '" style="display:inline-block; background:#2563eb; color:#fff; padding:10px 16px; text-decoration:none; border-radius:8px;">Ver ticket</a></p>'
                                    . '<p style="color:#94a3b8; font-size:12px; margin-top: 14px;">' . htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets') . '</p>'
                                    . '</div>';

                                $bodyText = 'Se te asignó un ticket: ' . $ticketNo . "\n" . 'Asunto: ' . (string)($ticketView['subject'] ?? '') . "\n" . 'Ver: ' . $viewUrl;
                                Mailer::send($to, $subj, $bodyHtml, $bodyText);
                            }
                        }
                    }
                }
            } elseif ($action === 'mark_answered') {
                requireRolePermission('ticket.markanswered', 'tickets.php?id=' . $tid);
                $resolved_id = 4;
                $stmt = $mysqli->prepare("SELECT id FROM ticket_status WHERE LOWER(name) LIKE ? OR LOWER(name) LIKE ? LIMIT 1");
                if ($stmt) {
                    $like1 = '%resuelto%';
                    $like2 = '%contestado%';
                    $stmt->bind_param('ss', $like1, $like2);
                    if ($stmt->execute()) {
                        $row = $stmt->get_result()->fetch_assoc();
                        if ($row) {
                            $resolved_id = (int) ($row['id'] ?? 4);
                        }
                    }
                }
                $stmt = $mysqli->prepare("UPDATE tickets SET status_id = ?, updated = NOW() WHERE id = ? AND empresa_id = ?");
                $stmt->bind_param('iii', $resolved_id, $tid, $eid);
                $ok = $stmt->execute();
                $msg = 'marked';
            } elseif ($action === 'transfer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                requireRolePermission('ticket.transfer', 'tickets.php?id=' . $tid);
                $newDeptId = isset($_POST['dept_id']) && is_numeric($_POST['dept_id']) ? (int) $_POST['dept_id'] : 0;
                if ($newDeptId > 0) {
                    // Validar departamento destino
                    $deptOk = false;
                    $stmtD = $mysqli->prepare('SELECT id FROM departments WHERE empresa_id = ? AND id = ? AND is_active = 1 LIMIT 1');
                    if ($stmtD) {
                        $stmtD->bind_param('ii', $eid, $newDeptId);
                        if ($stmtD->execute()) {
                            $deptOk = (bool) $stmtD->get_result()->fetch_assoc();
                        }
                    }

                    if ($deptOk) {
                        // Si el ticket está asignado, validar que el agente siga siendo válido para el nuevo dept
                        $currentStaffId = isset($ticketView['staff_id']) && is_numeric($ticketView['staff_id']) ? (int) $ticketView['staff_id'] : 0;
                        $unassign = false;
                        if ($currentStaffId > 0) {
                            $unassign = !$staffBelongsToDept((int)$currentStaffId, (int)$newDeptId, (int)$generalDeptId);
                        }

                        if ($unassign) {
                            $stmtUp = $mysqli->prepare('UPDATE tickets SET dept_id = ?, staff_id = NULL, updated = NOW() WHERE id = ? AND empresa_id = ?');
                            if ($stmtUp) {
                                $stmtUp->bind_param('iii', $newDeptId, $tid, $eid);
                                $ok = $stmtUp->execute();
                            }
                        } else {
                            $stmtUp = $mysqli->prepare('UPDATE tickets SET dept_id = ?, updated = NOW() WHERE id = ? AND empresa_id = ?');
                            if ($stmtUp) {
                                $stmtUp->bind_param('iii', $newDeptId, $tid, $eid);
                                $ok = $stmtUp->execute();
                            }
                        }
                        $msg = 'transferred';
                    }
                }
            } elseif ($action === 'owner' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
                requireRolePermission('ticket.edit', 'tickets.php?id=' . $tid);
                $uid = (int) $_POST['user_id'];
                $stmt = $mysqli->prepare("UPDATE tickets SET user_id = ?, updated = NOW() WHERE id = ? AND empresa_id = ?");
                $stmt->bind_param('iii', $uid, $tid, $eid);
                $ok = $stmt->execute();
                $msg = 'owner';
            } elseif ($action === 'block_email' && ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['confirm']) && $_GET['confirm'] === '1')) {
                requireRolePermission('ticket.edit', 'tickets.php?id=' . $tid);
                $email = $ticketView['user_email'] ?? '';
                if ($email) {
                    $stmt = $mysqli->prepare("UPDATE users SET status = 'banned', updated = NOW() WHERE empresa_id = ? AND id = ?");
                    $uid = (int)($ticketView['user_id'] ?? 0);
                    $stmt->bind_param('ii', $eid, $uid);
                    $ok = $stmt->execute();

                    $mysqli->query("CREATE TABLE IF NOT EXISTS banlist (\n"
                        . "  id INT PRIMARY KEY AUTO_INCREMENT,\n"
                        . "  email VARCHAR(255) NOT NULL,\n"
                        . "  domain VARCHAR(255) NULL,\n"
                        . "  notes TEXT NULL,\n"
                        . "  is_active TINYINT(1) NOT NULL DEFAULT 0,\n"
                        . "  created DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
                        . "  updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
                        . "  KEY idx_email (email),\n"
                        . "  KEY idx_domain (domain),\n"
                        . "  KEY idx_active (is_active)\n"
                        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                    $emailNorm = strtolower(trim((string)$email));
                    if ($emailNorm !== '' && filter_var($emailNorm, FILTER_VALIDATE_EMAIL)) {
                        $existsB = false;
                        $stmtB = $mysqli->prepare('SELECT id FROM banlist WHERE email = ? LIMIT 1');
                        if ($stmtB) {
                            $stmtB->bind_param('s', $emailNorm);
                            if ($stmtB->execute()) {
                                $existsB = (bool)$stmtB->get_result()->fetch_assoc();
                            }
                        }

                        if (!$existsB) {
                            $note = 'Bloqueado desde ticket #' . (string)$tid;
                            $stmtI = $mysqli->prepare('INSERT INTO banlist (email, domain, notes, is_active, created, updated) VALUES (?, NULL, ?, 0, NOW(), NOW())');
                            if ($stmtI) {
                                $stmtI->bind_param('ss', $emailNorm, $note);
                                $stmtI->execute();
                            }
                        }
                    }

                    $msg = 'blocked';
                }
            } elseif ($action === 'merge' && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($_POST['target_ticket_id'] ?? ''))) {
                requireRolePermission('ticket.merge', 'tickets.php?id=' . $tid);
                $target_input = trim($_POST['target_ticket_id']);
                $target_id = is_numeric($target_input) ? (int) $target_input : 0;
                if ($target_id === 0) {
                    $stmt = $mysqli->prepare("SELECT id FROM tickets WHERE empresa_id = ? AND ticket_number = ? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param('is', $eid, $target_input);
                        $stmt->execute();
                        $r = $stmt->get_result()->fetch_assoc();
                        if ($r) $target_id = (int) $r['id'];
                    }
                }
                if ($target_id <= 0) {
                    $_SESSION['flash_error'] = 'Ticket destino no encontrado.';
                    header('Location: tickets.php?id=' . $tid);
                    exit;
                }
                if ($target_id === $tid) {
                    $_SESSION['flash_error'] = 'No puedes unir un ticket consigo mismo.';
                    header('Location: tickets.php?id=' . $tid);
                    exit;
                }

                // Unir: mover mensajes de este ticket al ticket destino (sin alterar el número del destino)
                $ok = false;
                try {
                    if (method_exists($mysqli, 'begin_transaction')) {
                        $mysqli->begin_transaction();
                    }

                    // Obtener/crear thread del ticket destino
                    $stmtT = $mysqli->prepare('SELECT id FROM threads WHERE ticket_id = ? LIMIT 1');
                    $stmtT->bind_param('i', $target_id);
                    $stmtT->execute();
                    $targetThreadRow = $stmtT->get_result()->fetch_assoc();
                    $target_thread_id = (int)($targetThreadRow['id'] ?? 0);
                    if ($target_thread_id <= 0) {
                        $stmtNewTh = $mysqli->prepare('INSERT INTO threads (ticket_id, created) VALUES (?, NOW())');
                        if ($stmtNewTh) {
                            $stmtNewTh->bind_param('i', $target_id);
                            $stmtNewTh->execute();
                            $target_thread_id = (int)$mysqli->insert_id;
                        }
                    }

                    // Copiar entradas del thread origen al thread destino (sin borrar las originales)
                    $origin_thread_id = (int)($thread_id ?? 0);
                    if ($origin_thread_id > 0 && $target_thread_id > 0) {
                        if ($entriesHasEmpresa) {
                            $stmtCopy = $mysqli->prepare('INSERT INTO thread_entries (empresa_id, thread_id, user_id, staff_id, body, is_internal, created) SELECT ?, ?, user_id, staff_id, body, is_internal, created FROM thread_entries WHERE thread_id = ?');
                            $stmtCopy->bind_param('iii', $eid, $target_thread_id, $origin_thread_id);
                        } else {
                            $stmtCopy = $mysqli->prepare('INSERT INTO thread_entries (thread_id, user_id, staff_id, body, is_internal, created) SELECT ?, user_id, staff_id, body, is_internal, created FROM thread_entries WHERE thread_id = ?');
                            $stmtCopy->bind_param('ii', $target_thread_id, $origin_thread_id);
                        }
                        $stmtCopy->execute();
                    }

                    // Cerrar este ticket
                    $closed_id = 5;
                    $stmtClosed = $mysqli->prepare('SELECT id FROM ticket_status WHERE LOWER(name) LIKE ? LIMIT 1');
                    if ($stmtClosed) {
                        $likeClosed = '%cerrado%';
                        $stmtClosed->bind_param('s', $likeClosed);
                        if ($stmtClosed->execute()) {
                            $r = $stmtClosed->get_result()->fetch_assoc();
                            if ($r) $closed_id = (int)($r['id'] ?? 5);
                        }
                    }
                    $stmtUp = $mysqli->prepare('UPDATE tickets SET status_id = ?, closed = NOW(), updated = NOW() WHERE id = ? AND empresa_id = ?');
                    $stmtUp->bind_param('iii', $closed_id, $tid, $eid);
                    $stmtUp->execute();

                    // Crear vínculo entre origen y destino
                    if (!$ensureTicketLinksTable()) {
                        throw new Exception('ticket_links');
                    }
                    $stmtL = $mysqli->prepare('INSERT IGNORE INTO ticket_links (ticket_id, linked_ticket_id) VALUES (?, ?), (?, ?)');
                    if ($stmtL) {
                        $stmtL->bind_param('iiii', $tid, $target_id, $target_id, $tid);
                        $stmtL->execute();
                    }

                    if (method_exists($mysqli, 'commit')) {
                        $mysqli->commit();
                    }
                    $ok = true;
                } catch (Throwable $e) {
                    if (method_exists($mysqli, 'rollback')) {
                        $mysqli->rollback();
                    }
                    $_SESSION['flash_error'] = 'No se pudo unir el ticket.';
                    header('Location: tickets.php?id=' . $tid);
                    exit;
                }

                if ($ok) {
                    $_SESSION['flash_msg'] = 'Ticket unido: se copiaron los mensajes al ticket destino y este ticket se cerró.';
                    header('Location: tickets.php');
                    exit;
                }
            } elseif ($action === 'link' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['linked_ticket_id']) && is_numeric($_POST['linked_ticket_id'])) {
                requireRolePermission('ticket.link', 'tickets.php?id=' . $tid);
                $linked_id = (int) $_POST['linked_ticket_id'];
                if ($linked_id !== $tid && $linked_id > 0) {
                    if (!$ensureTicketLinksTable()) {
                        $_SESSION['flash_error'] = 'No se pudo habilitar la tabla de tickets vinculados.';
                        header('Location: tickets.php?id=' . $tid);
                        exit;
                    }
                    $stmt = $mysqli->prepare("INSERT IGNORE INTO ticket_links (ticket_id, linked_ticket_id) VALUES (?, ?), (?, ?)");
                    $stmt->bind_param('iiii', $tid, $linked_id, $linked_id, $tid);
                    $ok = $stmt->execute();
                    $msg = 'linked';
                }
            } elseif ($action === 'link' && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim((string)($_POST['linked_ticket_id'] ?? '')))) {
                requireRolePermission('ticket.link', 'tickets.php?id=' . $tid);
                $input = trim((string)$_POST['linked_ticket_id']);
                $linked_id = is_numeric($input) ? (int)$input : 0;
                if ($linked_id === 0) {
                    $stmtFind = $mysqli->prepare('SELECT id FROM tickets WHERE empresa_id = ? AND ticket_number = ? LIMIT 1');
                    if ($stmtFind) {
                        $stmtFind->bind_param('is', $eid, $input);
                        if ($stmtFind->execute()) {
                            $r = $stmtFind->get_result()->fetch_assoc();
                            if ($r) $linked_id = (int)($r['id'] ?? 0);
                        }
                    }
                }

                if ($linked_id <= 0) {
                    $_SESSION['flash_error'] = 'Ticket a vincular no encontrado.';
                    header('Location: tickets.php?id=' . $tid);
                    exit;
                }
                if ($linked_id === $tid) {
                    $_SESSION['flash_error'] = 'No puedes vincular un ticket consigo mismo.';
                    header('Location: tickets.php?id=' . $tid);
                    exit;
                }
                if (!$ensureTicketLinksTable()) {
                    $_SESSION['flash_error'] = 'No se pudo habilitar la tabla de tickets vinculados.';
                    header('Location: tickets.php?id=' . $tid);
                    exit;
                }
                $stmt = $mysqli->prepare("INSERT IGNORE INTO ticket_links (ticket_id, linked_ticket_id) VALUES (?, ?), (?, ?)");
                $stmt->bind_param('iiii', $tid, $linked_id, $linked_id, $tid);
                $ok = $stmt->execute();
                $msg = 'linked';
            } elseif ($action === 'unlink' && isset($_GET['linked_id']) && is_numeric($_GET['linked_id'])) {
                requireRolePermission('ticket.link', 'tickets.php?id=' . $tid);
                $linked_id = (int) $_GET['linked_id'];
                if (!$ensureTicketLinksTable()) {
                    $_SESSION['flash_error'] = 'No se pudo habilitar la tabla de tickets vinculados.';
                    header('Location: tickets.php?id=' . $tid);
                    exit;
                }
                $stmt = $mysqli->prepare("DELETE FROM ticket_links WHERE (ticket_id = ? AND linked_ticket_id = ?) OR (ticket_id = ? AND linked_ticket_id = ?)");
                $stmt->bind_param('iiii', $tid, $linked_id, $linked_id, $tid);
                $ok = $stmt->execute();
                $msg = 'unlinked';
            } elseif ($action === 'collab_add' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
                requireRolePermission('ticket.edit', 'tickets.php?id=' . $tid);
                $uid = (int) $_POST['user_id'];
                $exists = $mysqli->query("SHOW TABLES LIKE 'ticket_collaborators'");
                if ($exists && $exists->num_rows > 0) {
                    $userOk = false;
                    if ($uid > 0) {
                        $stmtU = $mysqli->prepare('SELECT id FROM users WHERE empresa_id = ? AND id = ? LIMIT 1');
                        if ($stmtU) {
                            $stmtU->bind_param('ii', $eid, $uid);
                            if ($stmtU->execute()) {
                                $userOk = (bool)$stmtU->get_result()->fetch_assoc();
                            }
                        }
                    }
                    if ($userOk) {
                        $stmt = $mysqli->prepare("INSERT IGNORE INTO ticket_collaborators (ticket_id, user_id) VALUES (?, ?)");
                        $stmt->bind_param('ii', $tid, $uid);
                        $ok = $stmt->execute();
                        $msg = 'collab_added';
                    }
                }
            } elseif ($action === 'collab_remove' && isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
                requireRolePermission('ticket.edit', 'tickets.php?id=' . $tid);
                $uid = (int) $_GET['user_id'];
                $exists = $mysqli->query("SHOW TABLES LIKE 'ticket_collaborators'");
                if ($exists && $exists->num_rows > 0) {
                    $stmt = $mysqli->prepare("DELETE FROM ticket_collaborators WHERE ticket_id = ? AND user_id = ?");
                    $stmt->bind_param('ii', $tid, $uid);
                    $ok = $stmt->execute();
                    $msg = 'collab_removed';
                }
            } elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                // Borrar ticket (desde modal)
                requireRolePermission('ticket.delete', 'tickets.php?id=' . $tid);
                $confirm = (string)($_POST['confirm'] ?? '');
                if ($confirm !== '1') {
                    $_SESSION['flash_error'] = 'Confirmación inválida.';
                    header('Location: tickets.php?id=' . $tid);
                    exit;
                }

                $deleted = false;
                try {
                    if (method_exists($mysqli, 'begin_transaction')) {
                        $mysqli->begin_transaction();
                    }

                    // Si existen tablas de threads/entries, borrarlas explícitamente para instalaciones sin FKs
                    $hasThreads = $mysqli->query("SHOW TABLES LIKE 'threads'");
                    $hasEntries = $mysqli->query("SHOW TABLES LIKE 'thread_entries'");
                    $threadsOk = $hasThreads && $hasThreads->num_rows > 0;
                    $entriesOk = $hasEntries && $hasEntries->num_rows > 0;

                    if ($threadsOk && $entriesOk) {
                        $stmtDelEntries = $mysqli->prepare(
                            'DELETE te FROM thread_entries te JOIN threads th ON th.id = te.thread_id WHERE th.ticket_id = ?'
                        );
                        if ($stmtDelEntries) {
                            $stmtDelEntries->bind_param('i', $tid);
                            $stmtDelEntries->execute();
                        }
                    }

                    if ($threadsOk) {
                        $stmtDelThreads = $mysqli->prepare('DELETE FROM threads WHERE ticket_id = ?');
                        if ($stmtDelThreads) {
                            $stmtDelThreads->bind_param('i', $tid);
                            $stmtDelThreads->execute();
                        }
                    }

                    $stmtDelTicket = $mysqli->prepare('DELETE FROM tickets WHERE id = ? AND empresa_id = ?');
                    if (!$stmtDelTicket) {
                        throw new Exception('No se pudo preparar la eliminación.');
                    }
                    $stmtDelTicket->bind_param('ii', $tid, $eid);
                    $deleted = (bool)$stmtDelTicket->execute();

                    if (!$deleted) {
                        throw new Exception('No se pudo eliminar el ticket.');
                    }

                    if (method_exists($mysqli, 'commit')) {
                        $mysqli->commit();
                    }
                } catch (Throwable $e) {
                    if (method_exists($mysqli, 'rollback')) {
                        $mysqli->rollback();
                    }
                    $deleted = false;
                }

                if ($deleted) {
                    $_SESSION['flash_msg'] = 'Ticket eliminado correctamente.';
                    header('Location: tickets.php');
                    exit;
                }

                $_SESSION['flash_error'] = 'No se pudo eliminar el ticket.';
                header('Location: tickets.php?id=' . $tid);
                exit;
            }
            if ($ok && $msg && !in_array($action, ['delete', 'merge'], true)) {
                header('Location: tickets.php?id=' . $tid . '&msg=' . $msg);
                exit;
            }
        }

        // Procesar respuesta o nota interna (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do'])) {
            if (!isset($_POST['csrf_token']) || !Auth::validateCSRF($_POST['csrf_token'])) {
                $reply_errors[] = 'Token de seguridad inválido.';
            } else {
                $body = trim($_POST['body'] ?? '');
                $is_internal = isset($_POST['do']) && $_POST['do'] === 'internal';
                if ($is_internal) {
                    requireRolePermission('ticket.post', 'tickets.php?id=' . $tid);
                } else {
                    requireRolePermission('ticket.reply', 'tickets.php?id=' . $tid);
                }
                error_log('[tickets] reply POST scp/modules/tickets.php uri=' . ($_SERVER['REQUEST_URI'] ?? '') . ' tid=' . (string)$tid . ' staff_session=' . (string)($_SESSION['staff_id'] ?? '') . ' internal=' . ($is_internal ? '1' : '0'));
                $new_status_id = isset($_POST['status_id']) && is_numeric($_POST['status_id']) ? (int) $_POST['status_id'] : (int) $ticketView['status_id'];
                $signature_mode = trim($_POST['signature'] ?? 'none');
                if ($body === '') {
                    $reply_errors[] = 'El mensaje no puede estar vacío.';
                } else {
                    $staff_id = (int) ($_SESSION['staff_id'] ?? 0);

                    if ($entriesHasEmpresa) {
                        $stmt = $mysqli->prepare(
                            "INSERT INTO thread_entries (empresa_id, thread_id, staff_id, body, is_internal, created) VALUES (?, ?, ?, ?, ?, NOW())"
                        );
                        $stmt->bind_param('iiisi', $eid, $thread_id, $staff_id, $body, $is_internal);
                    } else {
                        $stmt = $mysqli->prepare(
                            "INSERT INTO thread_entries (thread_id, staff_id, body, is_internal, created) VALUES (?, ?, ?, ?, NOW())"
                        );
                        $stmt->bind_param('iisi', $thread_id, $staff_id, $body, $is_internal);
                    }
                    if ($stmt->execute()) {
                        $entry_id = (int) $mysqli->insert_id;

                        // Auto status: when staff replies publicly and ticket is Open, move to In Progress
                        // Only if staff didn't change the status manually in the form.
                        try {
                            $currentStatusId = (int)($ticketView['status_id'] ?? 0);
                            if (!$is_internal
                                && $statusIdOpen > 0
                                && $statusIdInProgress > 0
                                && $currentStatusId === $statusIdOpen
                                && $new_status_id === $currentStatusId
                            ) {
                                // $new_status_id = $statusIdInProgress; // DESHABILITADO PARA QUE NO SEA AUTOMÁTICO
                            }
                        } catch (Throwable $e) {
                        }

                        // Notificar al cliente (solo respuestas públicas)
                        if (!$is_internal) {
                            try {
                                $hasUserNotifs = false;
                                $chkT = @$mysqli->query("SHOW TABLES LIKE 'user_notifications'");
                                $hasUserNotifs = ($chkT && $chkT->num_rows > 0);
                                if ($hasUserNotifs) {
                                    $uidOwner = (int)($ticketView['user_id'] ?? 0);
                                    if ($uidOwner > 0) {
                                        $msgNotif = 'Respuesta nueva · Ticket #' . (string)($ticketView['ticket_number'] ?? '');
                                        $typeNotif = 'ticket_reply';
                                        $stmtUn = $mysqli->prepare('INSERT INTO user_notifications (empresa_id, user_id, type, message, ticket_id, thread_entry_id, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())');
                                        if ($stmtUn) {
                                            $stmtUn->bind_param('iissii', $eid, $uidOwner, $typeNotif, $msgNotif, $tid, $entry_id);
                                            $stmtUn->execute();
                                        }
                                    }
                                }
                            } catch (Throwable $e) {
                            }
                        }

                        // Actualizar estado del ticket
                        $isClosingStatus = false;
                        $stmtSt = $mysqli->prepare('SELECT name FROM ticket_status WHERE id = ? LIMIT 1');
                        if ($stmtSt) {
                            $stmtSt->bind_param('i', $new_status_id);
                            if ($stmtSt->execute()) {
                                $stRow = $stmtSt->get_result()->fetch_assoc();
                                $stName = strtolower(trim((string)($stRow['name'] ?? '')));
                                if ($stName !== '' && (str_contains($stName, 'cerrad') || str_contains($stName, 'closed'))) {
                                    $isClosingStatus = true;
                                }
                            }
                        }

                        if ($isClosingStatus) {
                            $stmtU = $mysqli->prepare("UPDATE tickets SET status_id = ?, closed = NOW(), updated = NOW() WHERE id = ? AND empresa_id = ?");
                        } else {
                            $stmtU = $mysqli->prepare("UPDATE tickets SET status_id = ?, closed = NULL, updated = NOW() WHERE id = ? AND empresa_id = ?");
                        }
                        $stmtU->bind_param('iii', $new_status_id, $tid, $eid);
                        $stmtU->execute();
                        
                        // Si cambió a En Camino (id=2), notificar al usuario
                        if ($new_status_id === 2 && (int)($ticketView['status_id'] ?? 0) !== 2) {
                            $toClient = trim((string)($ticketView['user_email'] ?? ''));
                            if ($toClient !== '' && filter_var($toClient, FILTER_VALIDATE_EMAIL)) {
                                $ticketNo = (string)($ticketView['ticket_number'] ?? ('#' . $tid));
                                $subjClient = '[Ticket En Camino] ' . $ticketNo;
                                $bodyHtmlClient = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
                                    . '<h2 style="margin:0 0 10px;color:#1e3a5f;">Técnicos en camino</h2>'
                                    . '<p>Estimado usuario, los técnicos ya van en camino para atender su solicitud.</p>'
                                    . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . html((string)(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets')) . '</p>'
                                    . '</div>';
                                $bodyTextClient = "Estimado usuario, los técnicos ya van en camino para atender su solicitud.";
                                if (function_exists('enqueueEmailJob')) {
                                    enqueueEmailJob($toClient, $subjClient, $bodyHtmlClient, $bodyTextClient, [
                                        'empresa_id' => (int)$eid,
                                        'context_type' => 'ticket_en_camino_client',
                                        'context_id' => (int)$tid,
                                    ]);
                                    if (function_exists('triggerEmailQueueWorkerAsync')) {
                                        triggerEmailQueueWorkerAsync();
                                    }
                                } else {
                                    Mailer::send($toClient, $subjClient, $bodyHtmlClient, $bodyTextClient);
                                }
                                addLog('ticket_en_camino_email', 'Notificación En Camino encolada/enviada al usuario ' . $toClient, 'ticket', $tid);
                            }
                        }
                        if (!$is_internal && $ticketView['staff_id'] === null) {
                            $stmtAssign = $mysqli->prepare('UPDATE tickets SET staff_id = ? WHERE id = ? AND empresa_id = ?');
                            if ($stmtAssign) {
                                $stmtAssign->bind_param('iii', $staff_id, $tid, $eid);
                                $stmtAssign->execute();
                            }
                        }
                        // Adjuntos: guardar archivos y registrar en BD
                        $uploadDir = dirname(__DIR__, 3) . '/uploads/attachments';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0755, true);
                        }
                        $allowedExt = [
                            'jpg' => 'image/jpeg',
                            'jpeg' => 'image/jpeg',
                            'png' => 'image/png',
                            'gif' => 'image/gif',
                            'webp' => 'image/webp',
                            'pdf' => 'application/pdf',
                            'doc' => 'application/msword',
                            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'txt' => 'text/plain',
                        ];
                        $maxSize = 10 * 1024 * 1024; // 10 MB
                        if (!empty($_FILES['attachments']['name'][0])) {
                            $files = $_FILES['attachments'];
                            $n = is_array($files['name']) ? count($files['name']) : 1;
                            if ($n === 1 && !is_array($files['name'])) {
                                $files = ['name' => [$files['name']], 'type' => [$files['type']], 'tmp_name' => [$files['tmp_name']], 'error' => [$files['error']], 'size' => [$files['size']]];
                            }
                            for ($i = 0; $i < $n; $i++) {
                                $err = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                                if ($err !== UPLOAD_ERR_OK) continue;
                                $size = (int) ($files['size'][$i] ?? 0);
                                if ($size > $maxSize) continue;
                                $mime = (string) ($files['type'][$i] ?? '');
                                $orig = (string) ($files['name'][$i] ?? 'file');
                                $ext = strtolower((string) (pathinfo($orig, PATHINFO_EXTENSION) ?: ''));
                                if ($ext === '' || !isset($allowedExt[$ext])) continue;
                                if (class_exists('finfo') && !empty($files['tmp_name'][$i])) {
                                    $finfoObj = new finfo(FILEINFO_MIME_TYPE);
                                    $detected = @$finfoObj->file($files['tmp_name'][$i]);
                                    if (is_string($detected) && $detected !== '') $mime = $detected;
                                }
                                $safeName = bin2hex(random_bytes(8)) . '_' . time() . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
                                $path = $uploadDir . '/' . $safeName;
                                if (move_uploaded_file($files['tmp_name'][$i], $path)) {
                                    $relPath = 'uploads/attachments/' . $safeName;
                                    $hash = @hash_file('sha256', $path) ?: '';
                                    $stmtA = $mysqli->prepare("INSERT INTO attachments (thread_entry_id, filename, original_filename, mimetype, size, path, hash, created) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                                    $stmtA->bind_param('isssiss', $entry_id, $safeName, $orig, $mime, $size, $relPath, $hash);
                                    $stmtA->execute();
                                }
                            }
                        }

                        // Notificación por correo al cliente (solo respuestas públicas)
                        if (!$is_internal && (!defined('SEND_CLIENT_UPDATE_EMAIL') || SEND_CLIENT_UPDATE_EMAIL)) {
                            $to = trim((string)($ticketView['user_email'] ?? ''));
                            if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                                $ticketNo = (string)($ticketView['ticket_number'] ?? ('#' . $tid));
                                $subj = '[Respuesta] ' . $ticketNo . ' - ' . (string)($ticketView['subject'] ?? 'Ticket');

                                $sigText = '';
                                if ($signature_mode === 'staff' && $staff_has_signature) {
                                    $sigText = $staff_signature;
                                }

                                $isHtml = strpos($body, '<') !== false;
                                $msgHtml = $isHtml
                                    ? strip_tags($body, '<p><br><strong><em><b><i><u><s><ul><ol><li><a><span><div>')
                                    : nl2br(html($body));
                                $sigHtml = $sigText !== '' ? '<br><br>' . nl2br(html($sigText)) : '';

                                $viewUrl = (defined('APP_URL') ? APP_URL : '') . '/upload/tickets.php?id=' . (int) $tid;
                                $bodyHtml = '<div style="font-family: Segoe UI, sans-serif; max-width: 700px; margin: 0 auto;">'
                                    . '<h2 style="color:#1e3a5f; margin: 0 0 8px;">Actualización de su ticket</h2>'
                                    . '<p style="color:#64748b; margin: 0 0 12px;">Ticket: <strong>' . html($ticketNo) . '</strong></p>'
                                    . '<div style="background:#f8fafc; border:1px solid #e2e8f0; padding:14px; border-radius:10px;">' . $msgHtml . $sigHtml . '</div>'
                                    . '<p style="margin: 14px 0 0;"><a href="' . html($viewUrl) . '" style="display:inline-block; background:#2563eb; color:#fff; padding:10px 16px; text-decoration:none; border-radius:8px;">Ver ticket</a></p>'
                                    . '<p style="color:#94a3b8; font-size:12px; margin-top: 14px;">' . html(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets') . '</p>'
                                    . '</div>';

                                $bodyText = strip_tags($body) . ($sigText !== '' ? "\n\n" . $sigText : '');
                                Mailer::send($to, $subj, $bodyHtml, $bodyText);
                            }
                        }

                        $reply_success = true;
                        header('Location: tickets.php?id=' . $tid . '&msg=reply_sent');
                        exit;
                    }
                    $reply_errors[] = 'Error al guardar la respuesta.';
                }
            }
        }

        // Lista de estados para el desplegable
        $ticket_status_list = [];
        $resSt = $mysqli->query("SELECT id, name FROM ticket_status ORDER BY order_by, id");
        if ($resSt) while ($row = $resSt->fetch_assoc()) $ticket_status_list[] = $row;

        // Cargar mensajes del hilo (solo no internos para cliente; en SCP mostramos todos)
        $stmt = $mysqli->prepare(
            "SELECT te.id, te.thread_id, te.user_id, te.staff_id, te.body, te.is_internal, te.created,
             u.firstname AS user_first, u.lastname AS user_last,
             s.firstname AS staff_first, s.lastname AS staff_last, s.email AS staff_email
             FROM thread_entries te
             LEFT JOIN users u ON te.user_id = u.id
             LEFT JOIN staff s ON te.staff_id = s.id
             WHERE te.thread_id = ?
             ORDER BY te.created ASC"
        );
        $stmt->bind_param('i', $thread_id);
        $stmt->execute();
        $ticketView['thread_entries'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Adjuntos por entrada
        $attachmentsByEntry = [];
        if (!empty($ticketView['thread_entries'])) {
            $entryIds = array_map(static fn($e) => (int) $e['id'], $ticketView['thread_entries']);
            $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
            $typesA = str_repeat('i', count($entryIds));
            $sqlA = "SELECT id, thread_entry_id, original_filename, mimetype, size FROM attachments WHERE thread_entry_id IN ($placeholders) ORDER BY id";
            $stmtAtt = $mysqli->prepare($sqlA);
            $stmtAtt->bind_param($typesA, ...$entryIds);
            $stmtAtt->execute();
            $resAtt = $stmtAtt->get_result();
            while ($a = $resAtt->fetch_assoc()) {
                $eid = (int) $a['thread_entry_id'];
                if (!isset($attachmentsByEntry[$eid])) $attachmentsByEntry[$eid] = [];
                $attachmentsByEntry[$eid][] = $a;
            }
        }

        // Último mensaje y última respuesta (agente)
        $lastMsg = null;
        $lastReply = null;
        foreach ($ticketView['thread_entries'] as $e) {
            if ($e['is_internal'] != 1) {
                $lastMsg = $e['created'];
                if ($e['staff_id']) $lastReply = $e['created'];
            }
        }
        $ticketView['last_message'] = $lastMsg;
        $ticketView['last_response'] = $lastReply;

        $ticketView['user_name'] = trim($ticketView['user_first'] . ' ' . $ticketView['user_last']) ?: $ticketView['user_email'];
        $ticketView['staff_name'] = ($ticketView['staff_first'] || $ticketView['staff_last'])
            ? trim($ticketView['staff_first'] . ' ' . $ticketView['staff_last'])
            : '— Sin asignar —';

        // Tickets vinculados y colaboradores (si existen tablas)
        $ticketView['linked_tickets'] = [];
        $ticketView['collaborators'] = [];
        $resLinks = @$mysqli->query("SHOW TABLES LIKE 'ticket_links'");
        if ($resLinks && $resLinks->num_rows > 0) {
            $stmt = $mysqli->prepare("SELECT tl.linked_ticket_id AS id, t.ticket_number, t.subject FROM ticket_links tl JOIN tickets t ON t.id = tl.linked_ticket_id WHERE tl.ticket_id = ? AND t.empresa_id = ?");
            $stmt->bind_param('ii', $tid, $eid);
            $stmt->execute();
            $ticketView['linked_tickets'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        $resCollab = @$mysqli->query("SHOW TABLES LIKE 'ticket_collaborators'");
        if ($resCollab && $resCollab->num_rows > 0) {
            $stmt = $mysqli->prepare("SELECT tc.user_id, u.firstname, u.lastname, u.email FROM ticket_collaborators tc JOIN users u ON u.id = tc.user_id WHERE tc.ticket_id = ? AND u.empresa_id = ?");
            $stmt->bind_param('ii', $tid, $eid);
            $stmt->execute();
            $ticketView['collaborators'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        require __DIR__ . '/ticket-view.inc.php';
        return;
    }
}

// Sin id o ticket no encontrado: listado creativo de solicitudes
?>
<?php
$filters = [
    'open' => ['label' => 'Abiertos', 'where' => 't.closed IS NULL'],
    'closed' => ['label' => 'Cerrados', 'where' => 't.closed IS NOT NULL'],
    'mine' => ['label' => 'Asignados a mí', 'where' => 't.staff_id = ?'],
    'unassigned' => ['label' => 'Sin asignar', 'where' => 't.staff_id IS NULL'],
    'all' => ['label' => 'Todos', 'where' => '1=1'],
];
$filterKey = $_GET['filter'] ?? 'open';
if (!isset($filters[$filterKey])) $filterKey = 'open';
$query = trim($_GET['q'] ?? '');

// Filtro por tema (help topic) - opcional según esquema
$topicFilterAvailable = false;
$topicOptions = [];
$selectedTopicId = isset($_GET['topic_id']) && is_numeric($_GET['topic_id']) ? (int)$_GET['topic_id'] : 0;
$selectedTopicName = '';
$countSelectedTopic = 0;

$hasTopicsTable = false;
$hasTopicCol = false;
$t = $mysqli->query("SHOW TABLES LIKE 'help_topics'");
if ($t && $t->num_rows > 0) $hasTopicsTable = true;
$c = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'topic_id'");
if ($c && $c->num_rows > 0) $hasTopicCol = true;

if ($hasTopicsTable && $hasTopicCol) {
    $topicFilterAvailable = true;
    $stmtTp = $mysqli->prepare('SELECT id, name FROM help_topics WHERE empresa_id = ? AND is_active = 1 ORDER BY name');
    if ($stmtTp) {
        $stmtTp->bind_param('i', $eid);
        if ($stmtTp->execute()) {
            $r = $stmtTp->get_result();
            if ($r) {
                while ($row = $r->fetch_assoc()) {
                    $topicOptions[] = $row;
                    if ($selectedTopicId > 0 && (int)($row['id'] ?? 0) === $selectedTopicId) {
                        $selectedTopicName = (string)($row['name'] ?? '');
                    }
                }
            }
        }
    }
}

if ($topicFilterAvailable && $selectedTopicId > 0) {
    $stmtTc = $mysqli->prepare('SELECT COUNT(*) AS c FROM tickets WHERE empresa_id = ? AND topic_id = ?');
    if ($stmtTc) {
        $stmtTc->bind_param('ii', $eid, $selectedTopicId);
        if ($stmtTc->execute()) {
            $countSelectedTopic = (int)($stmtTc->get_result()->fetch_assoc()['c'] ?? 0);
        }
    }
}

// Contadores rápidos
$countOpen = 0;
$countClosed = 0;
$countUnassigned = 0;
$stmtCo = $mysqli->prepare('SELECT COUNT(*) c FROM tickets WHERE empresa_id = ? AND closed IS NULL');
if ($stmtCo) {
    $stmtCo->bind_param('i', $eid);
    if ($stmtCo->execute()) $countOpen = (int)($stmtCo->get_result()->fetch_assoc()['c'] ?? 0);
}
$stmtCc = $mysqli->prepare('SELECT COUNT(*) c FROM tickets WHERE empresa_id = ? AND closed IS NOT NULL');
if ($stmtCc) {
    $stmtCc->bind_param('i', $eid);
    if ($stmtCc->execute()) $countClosed = (int)($stmtCc->get_result()->fetch_assoc()['c'] ?? 0);
}
$stmtCu = $mysqli->prepare('SELECT COUNT(*) c FROM tickets WHERE empresa_id = ? AND staff_id IS NULL AND closed IS NULL');
if ($stmtCu) {
    $stmtCu->bind_param('i', $eid);
    if ($stmtCu->execute()) $countUnassigned = (int)($stmtCu->get_result()->fetch_assoc()['c'] ?? 0);
}
$countMine = 0;
if (!empty($_SESSION['staff_id'])) {
    $sid = (int) $_SESSION['staff_id'];
    $stmtCm = $mysqli->prepare('SELECT COUNT(*) c FROM tickets WHERE empresa_id = ? AND staff_id = ? AND closed IS NULL');
    if ($stmtCm) {
        $stmtCm->bind_param('ii', $eid, $sid);
        if ($stmtCm->execute()) $countMine = (int)($stmtCm->get_result()->fetch_assoc()['c'] ?? 0);
    }
}

// Query listado
$whereClauses = [];
$types = '';
$params = [];
$whereClauses[] = 't.empresa_id = ?';
$types .= 'i';
$params[] = $eid;
if ($filterKey === 'mine') {
    $whereClauses[] = 't.staff_id = ?';
    $types .= 'i';
    $params[] = (int) ($_SESSION['staff_id'] ?? 0);
} elseif ($filterKey !== 'all') {
    $whereClauses[] = $filters[$filterKey]['where'];
}
if ($topicFilterAvailable && $selectedTopicId > 0) {
    $whereClauses[] = 't.topic_id = ?';
    $types .= 'i';
    $params[] = $selectedTopicId;
}
if ($query !== '') {
    $like = '%' . $query . '%';
    $whereClauses[] = '(t.ticket_number LIKE ? OR t.subject LIKE ? OR u.email LIKE ? OR CONCAT(u.firstname, " ", u.lastname) LIKE ?)';
    $types .= 'ssss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Paginación en bloques de 10
$perPage = 10;
$page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

$totalTickets = 0;
$sqlTotal = "SELECT COUNT(*) AS c
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        $whereSql";
if ($types !== '') {
    $stmtTotal = $mysqli->prepare($sqlTotal);
    if ($stmtTotal) {
        $bindTotal = [$types];
        foreach ($params as $k => $v) { $bindTotal[] = &$params[$k]; }
        call_user_func_array([$stmtTotal, 'bind_param'], $bindTotal);
        if ($stmtTotal->execute()) {
            $totalTickets = (int)($stmtTotal->get_result()->fetch_assoc()['c'] ?? 0);
        }
    }
} else {
    $rsTotal = $mysqli->query($sqlTotal);
    if ($rsTotal) {
        $totalTickets = (int)($rsTotal->fetch_assoc()['c'] ?? 0);
    }
}

$totalPages = max(1, (int)ceil($totalTickets / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Conteo del tema seleccionado dentro de la vista actual (sin LIMIT)
if ($topicFilterAvailable && $selectedTopicId > 0) {
    $sqlCnt = "SELECT COUNT(*) AS c\n"
        . "FROM tickets t\n"
        . "JOIN users u ON t.user_id = u.id\n"
        . "$whereSql";
    if ($types !== '') {
        $stmtCnt = $mysqli->prepare($sqlCnt);
        if ($stmtCnt) {
            $bindCnt = [$types];
            foreach ($params as $k => $v) { $bindCnt[] = &$params[$k]; }
            call_user_func_array([$stmtCnt, 'bind_param'], $bindCnt);
            if ($stmtCnt->execute()) {
                $countSelectedTopic = (int)($stmtCnt->get_result()->fetch_assoc()['c'] ?? 0);
            }
        }
    } else {
        $rc = $mysqli->query($sqlCnt);
        if ($rc) {
            $countSelectedTopic = (int)($rc->fetch_assoc()['c'] ?? 0);
        }
    }
}

$sql = "SELECT t.id, t.ticket_number, t.subject, t.dept_id, t.created, t.updated, t.closed,
               ts.name AS status_name, ts.color AS status_color,
               p.name AS priority_name, p.color AS priority_color,
               u.firstname AS user_first, u.lastname AS user_last, u.email AS user_email,
               s.firstname AS staff_first, s.lastname AS staff_last
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN staff s ON t.staff_id = s.id
        JOIN ticket_status ts ON t.status_id = ts.id
        JOIN priorities p ON t.priority_id = p.id
        $whereSql
        ORDER BY t.updated DESC
        LIMIT ?, ?";

$tickets = [];
if ($types !== '') {
    $stmt = $mysqli->prepare($sql);
    $typesWithPage = $types . 'ii';
    $bind = [$typesWithPage];
    foreach ($params as $k => $v) { $bind[] = &$params[$k]; }
    $bind[] = &$offset;
    $bind[] = &$perPage;
    call_user_func_array([$stmt, 'bind_param'], $bind);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $mysqli->query($sql);
}
if ($res) {
    while ($row = $res->fetch_assoc()) $tickets[] = $row;
}

// Acciones masivas (listado)
$bulk_errors = [];
$bulk_success = '';

// Flash messages (PRG)
if (isset($_SESSION['bulk_flash_errors']) || isset($_SESSION['bulk_flash_success'])) {
    if (!empty($_SESSION['bulk_flash_errors']) && is_array($_SESSION['bulk_flash_errors'])) {
        $bulk_errors = $_SESSION['bulk_flash_errors'];
    }
    if (!empty($_SESSION['bulk_flash_success']) && is_string($_SESSION['bulk_flash_success'])) {
        $bulk_success = $_SESSION['bulk_flash_success'];
    }
    unset($_SESSION['bulk_flash_errors'], $_SESSION['bulk_flash_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && isset($_SESSION['staff_id'])) {
    $postErrors = [];
    $postSuccess = '';
    if (!isset($_POST['csrf_token']) || !Auth::validateCSRF($_POST['csrf_token'])) {
        $postErrors[] = 'Token de seguridad inválido.';
    } else {
        if ($_POST['do'] === 'bulk_assign') {
            requireRolePermission('ticket.assign', 'tickets.php');
        } elseif ($_POST['do'] === 'bulk_status') {
            // bulk status puede implicar cerrar o solo editar; lo validamos más abajo.
            requireRolePermission('ticket.edit', 'tickets.php');
        } elseif ($_POST['do'] === 'bulk_delete') {
            requireRolePermission('ticket.delete', 'tickets.php');
        }

        $ids = $_POST['ticket_ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $ticketIds = [];
        foreach ($ids as $id) {
            if (is_numeric($id)) $ticketIds[] = (int) $id;
        }
        $ticketIds = array_values(array_unique(array_filter($ticketIds, fn($v) => $v > 0)));

        if (empty($ticketIds)) {
            $postErrors[] = 'Seleccione al menos un ticket.';
        } else {
            $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
            $typesIds = str_repeat('i', count($ticketIds));

            if ($_POST['do'] === 'bulk_assign') {
                $staffId = isset($_POST['bulk_staff_id']) && is_numeric($_POST['bulk_staff_id']) ? (int) $_POST['bulk_staff_id'] : 0;
                $inAllowed = [];

                // Validación: no permitir asignar a agente tickets de distintos departamentos
                // (para evitar mezclas y mostrar advertencia consistente con el frontend)
                if ($staffId !== 0) {
                    $deptIds = [];
                    $sqlDept = "SELECT DISTINCT dept_id FROM tickets WHERE empresa_id = ? AND id IN ($placeholders)";
                    $stmtDept = $mysqli->prepare($sqlDept);
                    if ($stmtDept) {
                        $typesDept = 'i' . $typesIds;
                        $paramsDept = [&$typesDept, &$eid];
                        foreach ($ticketIds as $k => $v) { $paramsDept[] = &$ticketIds[$k]; }
                        call_user_func_array([$stmtDept, 'bind_param'], $paramsDept);
                        if ($stmtDept->execute()) {
                            $resDept = $stmtDept->get_result();
                            while ($resDept && ($dr = $resDept->fetch_assoc())) {
                                $did = (int)($dr['dept_id'] ?? 0);
                                if ($did > 0) $deptIds[$did] = true;
                            }
                        }
                    }
                    if (count($deptIds) > 1) {
                        $postErrors[] = 'Solo puedes asignar tickets del mismo departamento. Selecciona tickets de un solo departamento.';
                        $ticketIds = [];
                    }
                }

                if (empty($ticketIds)) {
                    // no continuar
                } else {

                // Validación: el agente debe ser del mismo dept que el ticket o General (si existe)
                $staffDept = 0;
                if ($staffId !== 0) {
                    // Para asignación masiva, validamos departamento por ticket más abajo.
                    $staffDept = 0;
                }

                // Capturar datos previos para notificación (solo cuando se asigna a un agente)
                $ticketsBefore = [];
                if ($staffId !== 0) {
                    $sqlSel = "SELECT t.id, t.ticket_number, t.subject, t.staff_id, t.dept_id, d.name AS dept_name\n"
                        . "FROM tickets t\n"
                        . "JOIN departments d ON d.id = t.dept_id AND d.empresa_id = ?\n"
                        . "WHERE t.empresa_id = ? AND t.id IN ($placeholders)";
                    $stmtSel = $mysqli->prepare($sqlSel);
                    if ($stmtSel) {
                        $typesSel = 'ii' . $typesIds;
                        $paramsSel = [&$typesSel, &$eid, &$eid];
                        foreach ($ticketIds as $k => $v) {
                            $paramsSel[] = &$ticketIds[$k];
                        }
                        call_user_func_array([$stmtSel, 'bind_param'], $paramsSel);
                        if ($stmtSel->execute()) {
                            $resSel = $stmtSel->get_result();
                            while ($row = $resSel->fetch_assoc()) {
                                $ticketsBefore[(int)$row['id']] = $row;
                            }
                        }
                    }
                }

                if ($staffId === 0) {
                    $sqlUp = "UPDATE tickets SET staff_id = NULL, updated = NOW() WHERE empresa_id = ? AND id IN ($placeholders)";
                    $stmt = $mysqli->prepare($sqlUp);
                    $types = 'i' . $typesIds;
                    $params = [&$types, &$eid];
                    foreach ($ticketIds as $k => $v) { $params[] = &$ticketIds[$k]; }
                    call_user_func_array([$stmt, 'bind_param'], $params);
                    $okBulk = $stmt->execute();
                } else {
                    // Solo actualizar tickets del mismo departamento
                    foreach ($ticketsBefore as $tid0 => $trow) {
                        $tdeptId = (int) ($trow['dept_id'] ?? 0);

                        if ($tdeptId > 0) {
                            if ($staffBelongsToDept((int)$staffId, (int)$tdeptId, (int)$generalDeptId)) {
                                $inAllowed[] = (int) $tid0;
                            }
                        } else {
                            // Si el ticket no tiene dept_id válido, permitir (fallback)
                            $inAllowed[] = (int) $tid0;
                        }
                    }

                    if (empty($inAllowed)) {
                        $okBulk = false;
                    } else {
                        $placeAllowed = implode(',', array_fill(0, count($inAllowed), '?'));
                        $typesAllowed = str_repeat('i', count($inAllowed));

                        $sqlUp = "UPDATE tickets SET staff_id = ?, updated = NOW() WHERE empresa_id = ? AND id IN ($placeAllowed)";
                        $stmt = $mysqli->prepare($sqlUp);
                        $types = 'ii' . $typesAllowed;
                        $params = [&$types, &$staffId, &$eid];
                        foreach ($inAllowed as $k => $v) { $params[] = &$inAllowed[$k]; }
                        call_user_func_array([$stmt, 'bind_param'], $params);
                        $okBulk = $stmt->execute();
                    }
                }

                if (!empty($inAllowed) && count($inAllowed) !== count($ticketIds) && $staffId !== 0) {
                    $postErrors[] = 'Algunos tickets no se asignaron porque el agente no pertenece al departamento.';
                }

                if ($okBulk) {
                    // Enviar email al agente asignado (solo si se asignó a alguien y cambió)
                    if ($staffId !== 0 && !empty($ticketsBefore)) {
                        $stmtS = $mysqli->prepare('SELECT email, firstname, lastname FROM staff WHERE empresa_id = ? AND id = ? AND is_active = 1 LIMIT 1');
                        if ($stmtS) {
                            $stmtS->bind_param('ii', $eid, $staffId);
                            if ($stmtS->execute()) {
                                $srow = $stmtS->get_result()->fetch_assoc();
                                $to = trim((string)($srow['email'] ?? ''));
                                $emailEnabled = ((string)getAppSetting('staff.' . (int)$staffId . '.email_ticket_assigned', '1') === '1');
                                if ($emailEnabled && $to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                                    $staffName = trim((string)($srow['firstname'] ?? '') . ' ' . (string)($srow['lastname'] ?? ''));
                                    if ($staffName === '') $staffName = 'Agente';

                                    foreach ($ticketsBefore as $tid0 => $trow) {
                                        $previousStaffId = $trow['staff_id'] ?? null;
                                        if ((string)$previousStaffId === (string)$staffId) {
                                            continue;
                                        }

                                        $ticketNo = (string)($trow['ticket_number'] ?? ('#' . (int)$tid0));
                                        $subj = '[Asignación] ' . $ticketNo . ' - ' . (string)($trow['subject'] ?? 'Ticket');
                                        $deptName = (string)($trow['dept_name'] ?? 'Soporte');
                                        $viewUrl = (defined('APP_URL') ? APP_URL : '') . '/upload/scp/tickets.php?id=' . (int)$tid0;

                                        $bodyHtml = '<div style="font-family: Segoe UI, sans-serif; max-width: 700px; margin: 0 auto;">'
                                            . '<h2 style="color:#1e3a5f; margin: 0 0 8px;">Se te asignó un ticket</h2>'
                                            . '<p style="color:#475569; margin: 0 0 12px;">Hola <strong>' . htmlspecialchars($staffName) . '</strong>, se te asignó el siguiente ticket:</p>'
                                            . '<table style="width:100%; border-collapse: collapse; margin: 12px 0;">'
                                            . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee;"><strong>Número:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">' . htmlspecialchars($ticketNo) . '</td></tr>'
                                            . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee;"><strong>Asunto:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">' . htmlspecialchars((string)($trow['subject'] ?? '')) . '</td></tr>'
                                            . '<tr><td style="padding: 6px 0;"><strong>Departamento:</strong></td><td style="padding: 6px 0;">' . htmlspecialchars($deptName) . '</td></tr>'
                                            . '</table>'
                                            . '<p style="margin: 14px 0 0;"><a href="' . htmlspecialchars($viewUrl) . '" style="display:inline-block; background:#2563eb; color:#fff; padding:10px 16px; text-decoration:none; border-radius:8px;">Ver ticket</a></p>'
                                            . '<p style="color:#94a3b8; font-size:12px; margin-top: 14px;">' . htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets') . '</p>'
                                            . '</div>';

                                        $bodyText = 'Se te asignó un ticket: ' . $ticketNo . "\n" . 'Asunto: ' . (string)($trow['subject'] ?? '') . "\n" . 'Ver: ' . $viewUrl;
                                        Mailer::send($to, $subj, $bodyHtml, $bodyText);
                                    }
                                }
                            }
                        }
                    }

                    // Notificaciones en BD (solo tickets que realmente cambiaron de agente)
                    if ($staffId !== 0 && !empty($ticketsBefore)) {
                        $type = 'ticket_assigned';
                        $stmtN = $mysqli->prepare('INSERT INTO notifications (staff_id, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
                        if ($stmtN) {
                            foreach ($ticketsBefore as $tid0 => $trow) {
                                $previousStaffId = $trow['staff_id'] ?? null;
                                if ((string)$previousStaffId === (string)$staffId) {
                                    continue;
                                }
                                if (!empty($inAllowed) && !in_array((int)$tid0, $inAllowed, true)) {
                                    continue;
                                }
                                $ticketNo = (string)($trow['ticket_number'] ?? ('#' . (int)$tid0));
                                $message = 'Se te asignó el ticket ' . $ticketNo . ': ' . (string)($trow['subject'] ?? '');
                                $relId = (int) $tid0;
                                $stmtN->bind_param('issi', $staffId, $message, $type, $relId);
                                $stmtN->execute();
                            }
                        }
                    }

                    $postSuccess = 'Asignación actualizada.';
                } else {
                    $postErrors[] = 'Error al asignar tickets.';
                }
                }
            } elseif ($_POST['do'] === 'bulk_status') {
                $statusId = isset($_POST['bulk_status_id']) && is_numeric($_POST['bulk_status_id']) ? (int) $_POST['bulk_status_id'] : 0;
                if ($statusId <= 0) {
                    $postErrors[] = 'Seleccione un estado válido.';
                } else {
                    $isClosingStatus = false;
                    $stmtSt = $mysqli->prepare('SELECT name FROM ticket_status WHERE id = ? LIMIT 1');
                    if ($stmtSt) {
                        $stmtSt->bind_param('i', $statusId);
                        if ($stmtSt->execute()) {
                            $stRow = $stmtSt->get_result()->fetch_assoc();
                            $stName = strtolower(trim((string)($stRow['name'] ?? '')));
                            if ($stName !== '' && (str_contains($stName, 'cerrad') || str_contains($stName, 'resuelt') || str_contains($stName, 'closed') || str_contains($stName, 'resolved'))) {
                                $isClosingStatus = true;
                            }
                        }
                    }

                    if ($isClosingStatus) {
                        requireRolePermission('ticket.close', 'tickets.php');
                        $sqlUp = "UPDATE tickets SET status_id = ?, closed = NOW(), updated = NOW() WHERE empresa_id = ? AND id IN ($placeholders)";
                    } else {
                        requireRolePermission('ticket.edit', 'tickets.php');
                        $sqlUp = "UPDATE tickets SET status_id = ?, closed = NULL, updated = NOW() WHERE empresa_id = ? AND id IN ($placeholders)";
                    }
                    $stmt = $mysqli->prepare($sqlUp);
                    $types = 'ii' . $typesIds;
                    $params = [&$types, &$statusId, &$eid];
                    foreach ($ticketIds as $k => $v) { $params[] = &$ticketIds[$k]; }
                    call_user_func_array([$stmt, 'bind_param'], $params);
                    if ($stmt->execute()) {
                        $postSuccess = 'Estado actualizado.';
                    } else {
                        $postErrors[] = 'Error al cambiar el estado.';
                    }
                }
            } elseif ($_POST['do'] === 'bulk_delete') {
                if (!isset($_POST['confirm']) || $_POST['confirm'] !== '1') {
                    $postErrors[] = 'Confirmación requerida.';
                } else {
                    $mysqli->begin_transaction();
                    try {
                        // Eliminar entradas/hilos si existen
                        $hasThreads = $mysqli->query("SHOW TABLES LIKE 'threads'");
                        $hasEntries = $mysqli->query("SHOW TABLES LIKE 'thread_entries'");
                        $threadsOk = $hasThreads && $hasThreads->num_rows > 0;
                        $entriesOk = $hasEntries && $hasEntries->num_rows > 0;
                        if ($threadsOk && $entriesOk) {
                            $sqlDelEntries = "DELETE te\n"
                                . "FROM thread_entries te\n"
                                . "JOIN threads th ON th.id = te.thread_id\n"
                                . "JOIN tickets t ON t.id = th.ticket_id\n"
                                . "WHERE t.empresa_id = ? AND th.ticket_id IN ($placeholders)";
                            $stmt = $mysqli->prepare($sqlDelEntries);
                            $typesDel = 'i' . $typesIds;
                            $params = [&$typesDel, &$eid];
                            foreach ($ticketIds as $k => $v) { $params[] = &$ticketIds[$k]; }
                            call_user_func_array([$stmt, 'bind_param'], $params);
                            $stmt->execute();

                            $sqlDelThreads = "DELETE th\n"
                                . "FROM threads th\n"
                                . "JOIN tickets t ON t.id = th.ticket_id\n"
                                . "WHERE t.empresa_id = ? AND th.ticket_id IN ($placeholders)";
                            $stmt = $mysqli->prepare($sqlDelThreads);
                            $typesDel = 'i' . $typesIds;
                            $params = [&$typesDel, &$eid];
                            foreach ($ticketIds as $k => $v) { $params[] = &$ticketIds[$k]; }
                            call_user_func_array([$stmt, 'bind_param'], $params);
                            $stmt->execute();
                        }

                        $sqlDelTickets = "DELETE FROM tickets WHERE empresa_id = ? AND id IN ($placeholders)";
                        $stmt = $mysqli->prepare($sqlDelTickets);
                        $typesDelT = 'i' . $typesIds;
                        $params = [&$typesDelT, &$eid];
                        foreach ($ticketIds as $k => $v) { $params[] = &$ticketIds[$k]; }
                        call_user_func_array([$stmt, 'bind_param'], $params);
                        if ($stmt->execute()) {
                            $mysqli->commit();
                            $postSuccess = 'Tickets eliminados.';
                        } else {
                            throw new Exception('No se pudo eliminar.');
                        }
                    } catch (Throwable $e) {
                        $mysqli->rollback();
                        $postErrors[] = 'Error al eliminar tickets.';
                    }
                }
            }
        }
    }

    // PRG: guardar flash y redirigir para evitar reenviar formulario al refrescar
    if (!empty($postErrors)) {
        $_SESSION['bulk_flash_errors'] = $postErrors;
    }
    if (!empty($postSuccess)) {
        $_SESSION['bulk_flash_success'] = $postSuccess;
    }
    $redirFilter = isset($_POST['current_filter']) ? (string) $_POST['current_filter'] : (string) ($filterKey ?? 'open');
    $redirQ = isset($_POST['current_q']) ? trim((string) $_POST['current_q']) : '';
    $redirTopicId = isset($_POST['current_topic_id']) && is_numeric($_POST['current_topic_id']) ? (int) $_POST['current_topic_id'] : $selectedTopicId;
    $redirParams = ['filter' => $redirFilter];
    if ($redirQ !== '') $redirParams['q'] = $redirQ;
    if ($redirTopicId > 0) $redirParams['topic_id'] = $redirTopicId;
    header('Location: tickets.php?' . http_build_query($redirParams));
    exit;
}

// Datos para toolbars
$staffOptions = [];
$staffHasRole = false;
if (isset($mysqli) && $mysqli) {
    $col = $mysqli->query("SHOW COLUMNS FROM staff LIKE 'role'");
    $staffHasRole = ($col && $col->num_rows > 0);
}

// Staff options para asignación
// Si estamos viendo un ticket, filtramos agentes por dept del ticket.
$ticketDeptForStaffOptions = 0;
if (!empty($ticketView)) {
    $ticketDeptForStaffOptions = (int) ($ticketView['dept_id'] ?? 0);
}

if ($hasStaffDepartmentsTable && $ticketDeptForStaffOptions > 0) {
    // New model: use staff_departments table for multi-department support
    $staffSql = "SELECT DISTINCT s.id, s.firstname, s.lastname\n"
        . "FROM staff s\n"
        . "JOIN staff_departments sd ON sd.staff_id = s.id\n"
        . "WHERE s.empresa_id = ? AND s.is_active = 1 AND sd.dept_id = ?";
    if ($staffHasRole) {
        $staffSql .= " AND (s.role IS NULL OR s.role <> 'superadmin')";
    }
    $staffSql .= " ORDER BY s.firstname, s.lastname";
    $stmtStaffTb = $mysqli->prepare($staffSql);
} else {
    // Fallback (modo legacy o si no hay ticket especifico)
    $staffSql = "SELECT id, firstname, lastname, COALESCE(NULLIF(dept_id, 0), $generalDeptId) AS dept_id FROM staff WHERE empresa_id = ? AND is_active = 1";
    if ($staffHasRole) {
        $staffSql .= " AND (role IS NULL OR role <> 'superadmin')";
    }
    $staffSql .= " ORDER BY firstname, lastname";
    $stmtStaffTb = $mysqli->prepare($staffSql);
}
$r = null;
if ($stmtStaffTb) {
    if ($hasStaffDepartmentsTable && $ticketDeptForStaffOptions > 0) {
        $stmtStaffTb->bind_param('ii', $eid, $ticketDeptForStaffOptions);
    } else {
        $stmtStaffTb->bind_param('i', $eid);
    }
    if ($stmtStaffTb->execute()) {
        $r = $stmtStaffTb->get_result();
    }
}
if ($r) while ($row = $r->fetch_assoc()) $staffOptions[] = $row;
$statusOptions = [];
$r = $mysqli->query("SELECT id, name FROM ticket_status ORDER BY id");
if ($r) while ($row = $r->fetch_assoc()) $statusOptions[] = $row;

// Nota: el filtrado por departamento se aplica en SQL mediante JOIN staff_departments
// En modo legacy, se mantiene el filtrado PHP con dept_id.
if (!$hasStaffDepartmentsTable && !empty($ticketView)) {
    $tdept = (int) ($ticketView['dept_id'] ?? 0);
    if ($tdept > 0) {
        $staffOptions = array_values(array_filter($staffOptions, function ($s) use ($tdept) {
            $sd = (int) ($s['dept_id'] ?? 0);
            return $sd === $tdept;
        }));
    }
}
?>

<div class="tickets-shell">
    <?php if (isset($_GET['id']) && !$ticketView): ?>
        <div class="alert alert-warning">Ticket no encontrado.</div>
    <?php endif; ?>

    <?php if (!empty($bulk_errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($bulk_errors as $e): ?>
                    <li><?php echo html($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if ($bulk_success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo html($bulk_success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div id="bulkClientAlert" class="alert alert-warning d-none" role="alert"></div>

    <div id="bulkLoadingOverlay" class="d-none" style="position:fixed; inset:0; background:rgba(15,23,42,0.45); z-index: 3000;">
        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:14px; padding:16px 18px; border:1px solid #e2e8f0; box-shadow:0 16px 40px rgba(0,0,0,0.25); min-width: 260px; text-align:center;">
            <div class="spinner-border text-primary" role="status" style="width:2.25rem; height:2.25rem;"></div>
            <div id="bulkLoadingText" style="margin-top:10px; font-weight:800; color:#0f172a;">Procesando…</div>
            <div style="margin-top:4px; color:#64748b; font-size:0.9rem;">Por favor espera</div>
        </div>
    </div>

    <div class="tickets-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1>Tickets</h1>
                <div class="sub">Abiertos: <strong><?php echo $countOpen; ?></strong> · Sin asignar: <strong><?php echo $countUnassigned; ?></strong> · Míos: <strong><?php echo $countMine; ?></strong><?php if ($topicFilterAvailable && $selectedTopicId > 0): ?> · Tema: <strong><?php echo html($selectedTopicName ?: ('#' . (int)$selectedTopicId)); ?></strong> (Total: <strong><?php echo (int)$countSelectedTopic; ?></strong>)<?php endif; ?></div>
            </div>
            <?php if (roleHasPermission('ticket.create')): ?>
                <a href="tickets.php?a=open" class="btn-new"><i class="bi bi-plus-lg me-1"></i> Nuevo</a>
            <?php endif; ?>
        </div>
    </div>

    <form method="post" id="bulkForm">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="do" id="bulk_do" value="">
        <input type="hidden" name="confirm" id="bulk_confirm" value="0">
        <input type="hidden" name="current_filter" value="<?php echo html($filterKey); ?>">
        <input type="hidden" name="current_q" value="<?php echo html($query); ?>">
        <input type="hidden" name="current_topic_id" value="<?php echo (int)$selectedTopicId; ?>">
        <input type="hidden" name="bulk_staff_id" id="bulk_staff_id" value="">
        <input type="hidden" name="bulk_status_id" id="bulk_status_id" value="">
        <input type="hidden" id="bulk_staff_label" value="">
        <input type="hidden" id="bulk_status_label" value="">

        <?php
        $canBulkAssign = roleHasPermission('ticket.assign');
        $canBulkEdit = roleHasPermission('ticket.edit');
        $canBulkClose = roleHasPermission('ticket.close');
        $canBulkDelete = roleHasPermission('ticket.delete');
        $bulkStatusLocked = !$canBulkEdit && !$canBulkClose;
        ?>
        <div class="tickets-panel" data-filter-key="<?php echo html($filterKey); ?>" data-general-dept-id="<?php echo (int)$generalDeptId; ?>">
            <div class="tickets-toolbar">
                <div class="tickets-actions">
                <button type="button" class="btn btn-action btn-sm" data-action="tickets-select-all">Seleccionar</button>
                <button type="button" class="btn btn-action btn-sm" data-action="tickets-select-none">Ninguno</button>

                <div class="btn-group">
                    <button type="button" class="btn btn-action btn-sm btn-icon" title="<?php echo $canBulkAssign ? 'Asignar' : 'Sin permiso para asignar'; ?>" <?php echo $canBulkAssign ? '' : 'disabled'; ?>>
                        <i class="bi bi-person"></i>
                    </button>
                    <button type="button" class="btn btn-action btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" <?php echo $canBulkAssign ? '' : 'disabled'; ?>>
                        <span class="visually-hidden">Toggle Dropdown</span>
                    </button>
                    <ul class="dropdown-menu" id="bulkAssignMenu">
                        <li id="bulkAssignEmptyItem" class="d-none"><span class="dropdown-item-text text-muted" style="font-weight:700;">Debes seleccionar un ticket</span></li>
                        <li id="bulkAssignUnassignItem"><a class="dropdown-item" href="#" data-action="tickets-bulk-assign" data-staff-id="0" data-staff-label="— Sin asignar —">— Sin asignar —</a></li>
                        <li id="bulkAssignDivider"><hr class="dropdown-divider"></li>
                        <?php foreach ($staffOptions as $s): ?>
                            <?php $sn = trim($s['firstname'] . ' ' . $s['lastname']); ?>
                            <li><a class="dropdown-item bulk-assign-staff-item" href="#" data-action="tickets-bulk-assign" data-staff-id="<?php echo (int) $s['id']; ?>" data-staff-label="<?php echo html($sn); ?>" data-staff-dept-id="<?php echo (int)($s['dept_id'] ?? 0); ?>"><?php echo html($sn); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-action btn-sm btn-icon" title="<?php echo $bulkStatusLocked ? 'Sin permiso para cambiar/cerrar' : 'Cambiar estado'; ?>" <?php echo $bulkStatusLocked ? 'disabled' : ''; ?>>
                        <i class="bi bi-flag"></i>
                    </button>
                    <button type="button" class="btn btn-action btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" <?php echo $bulkStatusLocked ? 'disabled' : ''; ?>>
                        <span class="visually-hidden">Toggle Dropdown</span>
                    </button>
                    <ul class="dropdown-menu">
                        <?php foreach ($statusOptions as $st): ?>
                            <li><a class="dropdown-item" href="#" data-action="tickets-bulk-status" data-status-id="<?php echo (int) $st['id']; ?>" data-status-label="<?php echo html($st['name']); ?>"><?php echo html($st['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <button type="button" class="btn btn-danger-soft btn-sm" data-action="tickets-bulk-delete" title="<?php echo $canBulkDelete ? 'Eliminar' : 'Sin permiso para eliminar'; ?>" <?php echo $canBulkDelete ? '' : 'disabled'; ?>><i class="bi bi-trash"></i> Eliminar</button>
                </div>

                <div class="text-muted" style="font-size: 0.85rem; font-weight: 700;">
                    <?php if ($totalTickets > 0): ?>
                        Mostrando <?php echo (int)$offset + 1; ?>-<?php echo min((int)$offset + $perPage, $totalTickets); ?> de <?php echo $totalTickets; ?> resultados
                    <?php else: ?>
                        0 resultados
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tickets-panel">
            <div class="tickets-toolbar">
                <div class="tickets-filters">
                    <div class="dropdown filter-dd">
                        <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-funnel"></i>
                            <?php echo html($filters[$filterKey]['label']); ?>
                        </button>
                        <ul class="dropdown-menu">
                            <?php $topicParam = ($topicFilterAvailable && $selectedTopicId > 0) ? ('&topic_id=' . (int)$selectedTopicId) : ''; ?>
                            <li><a class="dropdown-item <?php echo $filterKey === 'open' ? 'active' : ''; ?>" href="tickets.php?filter=open<?php echo $query !== '' ? '&q=' . urlencode($query) : ''; ?><?php echo $topicParam; ?>">Abiertos</a></li>
                            <li><a class="dropdown-item <?php echo $filterKey === 'unassigned' ? 'active' : ''; ?>" href="tickets.php?filter=unassigned<?php echo $query !== '' ? '&q=' . urlencode($query) : ''; ?><?php echo $topicParam; ?>">Sin asignar</a></li>
                            <li><a class="dropdown-item <?php echo $filterKey === 'mine' ? 'active' : ''; ?>" href="tickets.php?filter=mine<?php echo $query !== '' ? '&q=' . urlencode($query) : ''; ?><?php echo $topicParam; ?>">Asignados a mí</a></li>
                            <li><a class="dropdown-item <?php echo $filterKey === 'closed' ? 'active' : ''; ?>" href="tickets.php?filter=closed<?php echo $query !== '' ? '&q=' . urlencode($query) : ''; ?><?php echo $topicParam; ?>">Cerrados</a></li>
                            <li><a class="dropdown-item <?php echo $filterKey === 'all' ? 'active' : ''; ?>" href="tickets.php?filter=all<?php echo $query !== '' ? '&q=' . urlencode($query) : ''; ?><?php echo $topicParam; ?>">Todos</a></li>
                        </ul>
                    </div>

                    <?php if ($topicFilterAvailable): ?>
                        <select class="form-select form-select-sm" id="ticketTopicSelect" aria-label="Filtrar por tema">
                            <option value="0">Todos los temas</option>
                            <?php foreach ($topicOptions as $tp): ?>
                                <?php $tpId = (int)($tp['id'] ?? 0); ?>
                                <option value="<?php echo $tpId; ?>" <?php echo $tpId === (int)$selectedTopicId ? 'selected' : ''; ?>><?php echo html((string)($tp['name'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
                <div class="tickets-search">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="ticketSearchInput" class="form-control" placeholder="Buscar" value="<?php echo html($query); ?>">
                        <button type="button" class="btn btn-action btn-sm" data-action="tickets-search">Buscar</button>
                    </div>
                </div>
            </div>
        </div>

    <div class="tickets-table-wrap">
        <table class="table table-hover tickets-table mb-0" id="ticketsTable">
            <thead class="table-light">
                <tr>
                    <th class="check-cell"><input type="checkbox" class="form-check-input" id="check_all"></th>
                    <th>Ticket</th>
                    <th>Estado</th>
                    <th>Prioridad</th>
                    <th class="d-none d-lg-table-cell">Cliente</th>
                    <th class="d-none d-md-table-cell">Actualizado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-inbox" style="font-size: 2rem; opacity: 0.6;"></i>
                                <div class="mt-2">No hay tickets para esta vista.</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <?php
                        $clientName = trim($t['user_first'] . ' ' . $t['user_last']) ?: $t['user_email'];
                        $staffName = trim($t['staff_first'] . ' ' . $t['staff_last']);
                        $statusColor = $t['status_color'] ?: '#2563eb';
                        $priorityColor = $t['priority_color'] ?: '#94a3b8';

                        $tidRow = (int)($t['id'] ?? 0);
                        $createdTs = !empty($t['created']) ? @strtotime((string)$t['created']) : 0;
                        $sidNew = (int)($_SESSION['staff_id'] ?? 0);
                        $sinceKey = $sidNew > 0 ? ('tickets_new_since_' . $sidNew) : '';
                        $newSince = ($sinceKey !== '' && isset($_SESSION[$sinceKey]) && is_numeric($_SESSION[$sinceKey])) ? (int)$_SESSION[$sinceKey] : 0;
                        $isAfterSince = ($newSince > 0 && $createdTs > 0 && $createdTs >= $newSince);
                        $isNew = ($tidRow > 0 && $isAfterSince && !isset($seenIds[$tidRow]));
                        ?>
                        <?php
                            $backRel = 'tickets.php';
                            if (!empty($_SERVER['REQUEST_URI'])) {
                                $u = (string)$_SERVER['REQUEST_URI'];
                                $path = (string)parse_url($u, PHP_URL_PATH);
                                $reqQueryStr = (string)parse_url($u, PHP_URL_QUERY);
                                $rel = ltrim($path, '/');
                                $needle = 'upload/scp/';
                                $pos = strpos($rel, $needle);
                                if ($pos !== false) {
                                    $rel = substr($rel, $pos + strlen($needle));
                                }
                                $rel = trim($rel);
                                if ($rel !== '') {
                                    $backRel = $rel . ($reqQueryStr !== '' ? ('?' . $reqQueryStr) : '');
                                }
                            }
                            $ticketHref = 'tickets.php?id=' . (int)$t['id'] . '&back=' . urlencode($backRel);
                        ?>
                        <tr class="ticket-row" data-ticket-id="<?php echo (int)$t['id']; ?>" data-ticket-dept-id="<?php echo (int)($t['dept_id'] ?? 0); ?>">
                            <td class="check-cell"><input class="form-check-input ticket-check" type="checkbox" name="ticket_ids[]" value="<?php echo (int) $t['id']; ?>" data-ticket-dept-id="<?php echo (int)($t['dept_id'] ?? 0); ?>"></td>
                            <td>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div>
                                        <a class="ticket-title ticket-preview-trigger" href="<?php echo html($ticketHref); ?>" data-ticket-id="<?php echo (int)$t['id']; ?>">#<?php echo html($t['ticket_number']); ?></a>
                                        <?php if ($isNew): ?>
                                            <span class="badge bg-danger ms-2">New</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-md-none text-muted" style="font-size:0.75rem; font-weight:700;">
                                        <?php echo formatDate($t['updated'] ?: $t['created']); ?>
                                    </div>
                                </div>
                                
                                <div class="ticket-subject" style="margin-top: 6px;"><?php echo html($t['subject']); ?></div>
                                
                                <div class="d-md-none mt-2 mb-1" style="font-size:0.85rem; font-weight:700; color:#334155;">
                                    <i class="bi bi-person-circle text-primary me-1"></i> <?php echo html($clientName); ?>
                                </div>

                                <div class="ticket-meta mt-1" style="font-size:0.8rem;">
                                    <i class="bi bi-person-badge"></i> Asignado: <strong style="color:#0f172a;"><?php echo $staffName ?: 'Sin asignar'; ?></strong>
                                </div>

                                <!-- Chip de estado para móvil -->
                                <div class="ticket-row-mobile-meta d-md-none mt-3" style="display: flex; gap: 8px;">
                                    <span class="chip chip-status" style="background: <?php echo html($statusColor); ?>22; color: <?php echo html($statusColor); ?>; font-size:0.75rem;">
                                        <?php echo html($t['status_name']); ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="chip chip-status" style="background: <?php echo html($statusColor); ?>22; color: <?php echo html($statusColor); ?>;">
                                    <?php echo html($t['status_name']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="chip chip-priority" style="background: <?php echo html($priorityColor); ?>22; color: <?php echo html($priorityColor); ?>;">
                                    <?php echo html($t['priority_name']); ?>
                                </span>
                            </td>
                            <td class="d-none d-lg-table-cell">
                                <div class="ticket-meta" style="color:#475569; font-weight:600;">
                                    <?php echo html($clientName); ?>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <div class="ticket-meta"><?php echo formatDate($t['updated'] ?: $t['created']); ?></div>
                            </td>
                            <td>
                                <a href="<?php echo html($ticketHref); ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php
    $basePageParams = ['filter' => $filterKey];
    if ($query !== '') $basePageParams['q'] = $query;
    if ($topicFilterAvailable && $selectedTopicId > 0) $basePageParams['topic_id'] = (int)$selectedTopicId;
    $prevUrl = '';
    $nextUrl = '';
    if ($page > 1) {
        $prevParams = $basePageParams;
        $prevParams['p'] = $page - 1;
        $prevUrl = 'tickets.php?' . http_build_query($prevParams);
    }
    if ($page < $totalPages) {
        $nextParams = $basePageParams;
        $nextParams['p'] = $page + 1;
        $nextUrl = 'tickets.php?' . http_build_query($nextParams);
    }
    ?>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
        <div class="text-muted" style="font-size:0.9rem;">
            Página <?php echo $page; ?> de <?php echo $totalPages; ?>
        </div>
        <div class="d-flex gap-2">
            <?php if ($prevUrl !== ''): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo html($prevUrl); ?>">
                    <i class="bi bi-chevron-left"></i> Anterior
                </a>
            <?php else: ?>
                <button class="btn btn-outline-secondary btn-sm" type="button" disabled>
                    <i class="bi bi-chevron-left"></i> Anterior
                </button>
            <?php endif; ?>

            <?php if ($nextUrl !== ''): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo html($nextUrl); ?>">
                    Siguiente <i class="bi bi-chevron-right"></i>
                </a>
            <?php else: ?>
                <button class="btn btn-outline-secondary btn-sm" type="button" disabled>
                    Siguiente <i class="bi bi-chevron-right"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="ticket-hover-preview d-none" id="ticketHoverPreview" role="dialog" aria-hidden="true">
        <div class="ticket-hover-preview-inner">
            <div class="ticket-hover-preview-head">
                <div class="num" id="ticketHoverNumber"></div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <a class="open" id="ticketHoverOpen" href="#" title="Abrir ticket"><i class="bi bi-box-arrow-up-right"></i></a>
                    <button type="button" class="close" id="ticketHoverClose" aria-label="Cerrar" title="Cerrar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            <div class="subject" id="ticketHoverSubject"></div>
            <div class="meta" id="ticketHoverMeta"></div>
            <div class="msg" id="ticketHoverMsg"></div>
            <div class="loading d-none" id="ticketHoverLoading">
                <div class="spinner-border text-primary" role="status" style="width:1.5rem; height:1.5rem;"></div>
                <div class="text">Cargando…</div>
            </div>
        </div>
    </div>
    </form>
</div>

<!-- Modal confirmación acción masiva -->
<div class="modal fade" id="bulkConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar acción</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="bulkConfirmText"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="bulkConfirmBtn">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal informativo (evita popup del navegador con "localhost") -->
<div class="modal fade" id="bulkInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-exclamation-circle text-warning me-2"></i>Atención</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="bulkInfoText"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>

