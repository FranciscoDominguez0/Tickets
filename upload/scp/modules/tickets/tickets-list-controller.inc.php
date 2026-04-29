<?php
// Sin id o ticket no encontrado: listado creativo de solicitudes
$filters = [
    'open' => ['label' => 'Abiertos', 'where' => 't.closed IS NULL'],
    'closed' => ['label' => 'Cerrados', 'where' => 't.closed IS NOT NULL'],
    'mine' => ['label' => 'Asignados a mí', 'where' => 't.staff_id = ?'],
    'unassigned' => ['label' => 'Sin asignar', 'where' => 't.staff_id IS NULL'],
    'all' => ['label' => 'Todos', 'where' => '1=1'],
];
$filterKey = $_GET['filter'] ?? null;
$isAgent = (getCurrentStaffRoleName() === 'agent');
if ($filterKey === null && $isAgent) {
    $filterKey = 'mine';
}
if ($filterKey === null || !isset($filters[$filterKey])) {
    $filterKey = 'open';
}
$query = trim($_GET['q'] ?? '');

// Filtro de fechas (aplica a todos los filtros)
$defaultDateFrom = date('Y-m-01'); // Primer día del mes actual
$defaultDateTo   = date('Y-m-d');  // Hoy
// Columna de fecha a filtrar: t.closed para cerrados, t.created para el resto
$dateCol = ($filterKey === 'closed') ? 't.closed' : 't.created';
if (isset($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'])) {
    $dateFrom = $_GET['date_from'];
} else {
    $dateFrom = $defaultDateFrom;
}
if (isset($_GET['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to'])) {
    $dateTo = $_GET['date_to'];
} else {
    $dateTo = $defaultDateTo;
}

// Filtro por tema (help topic) - opcional según esquema
$topicFilterAvailable = false;
$topicOptions = [];
$selectedTopicId = isset($_GET['topic_id']) && is_numeric($_GET['topic_id']) ? (int)$_GET['topic_id'] : 0;
$selectedTopicName = '';
$countSelectedTopic = 0;

$selectedStaffId = isset($_GET['staff_id']) && is_numeric($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;
$selectedStaffName = '';

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
$sqlCo = 'SELECT COUNT(*) c FROM tickets WHERE empresa_id = ? AND closed IS NULL';
// if ($isAgent) $sqlCo .= ' AND staff_id = ' . (int)$_SESSION['staff_id']; // Removido para que el agente vea el total global
$stmtCo = $mysqli->prepare($sqlCo);
if ($stmtCo) {
    $stmtCo->bind_param('i', $eid);
    if ($stmtCo->execute()) $countOpen = (int)($stmtCo->get_result()->fetch_assoc()['c'] ?? 0);
}
$sqlCc = 'SELECT COUNT(*) c FROM tickets WHERE empresa_id = ? AND closed IS NOT NULL';
// if ($isAgent) $sqlCc .= ' AND staff_id = ' . (int)$_SESSION['staff_id']; // Removido para que el agente vea el total global
$stmtCc = $mysqli->prepare($sqlCc);
if ($stmtCc) {
    $stmtCc->bind_param('i', $eid);
    if ($stmtCc->execute()) $countClosed = (int)($stmtCc->get_result()->fetch_assoc()['c'] ?? 0);
}
if ($isAgent) {
    $countUnassigned = 0;
} else {
    $stmtCu = $mysqli->prepare('SELECT COUNT(*) c FROM tickets WHERE empresa_id = ? AND staff_id IS NULL AND closed IS NULL');
    if ($stmtCu) {
        $stmtCu->bind_param('i', $eid);
        if ($stmtCu->execute()) $countUnassigned = (int)($stmtCu->get_result()->fetch_assoc()['c'] ?? 0);
    }
}
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
    $whereClauses[] = '(t.ticket_number LIKE ? OR t.subject LIKE ? OR u.email LIKE ? OR CONCAT(u.firstname, " ", u.lastname) LIKE ? OR CONCAT(s.firstname, " ", s.lastname) LIKE ?)';
    $types .= 'sssss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
// Filtro por rango de fechas (aplica sobre la columna de fecha correspondiente al filtro)
if ($dateFrom !== '') {
    $whereClauses[] = $dateCol . ' >= ?';
    $types .= 's';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $whereClauses[] = $dateCol . ' <= ?';
    $types .= 's';
    $params[] = $dateTo . ' 23:59:59';
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
        LEFT JOIN staff s ON t.staff_id = s.id
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
        . "LEFT JOIN staff s ON t.staff_id = s.id\n"
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
               u.firstname AS user_first, u.lastname AS user_last, u.email AS user_email, u.company AS user_company,
               s.firstname AS staff_first, s.lastname AS staff_last,
               tr.billing_status,
               (CASE WHEN tr.id IS NOT NULL THEN 1 ELSE 0 END) AS has_report
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN staff s ON t.staff_id = s.id
        JOIN ticket_status ts ON t.status_id = ts.id
        JOIN priorities p ON t.priority_id = p.id
        LEFT JOIN ticket_reports tr ON tr.ticket_id = t.id
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

        if ($isAgent && !empty($ticketIds)) {
            $placeholdersCheck = implode(',', array_fill(0, count($ticketIds), '?'));
            $stmtCheck = $mysqli->prepare("SELECT id FROM tickets WHERE empresa_id = ? AND staff_id = ? AND id IN ($placeholdersCheck)");
            $typesCheck = 'ii' . str_repeat('i', count($ticketIds));
            $sid = (int)($_SESSION['staff_id'] ?? 0);
            $paramsCheck = [&$typesCheck, &$eid, &$sid];
            foreach ($ticketIds as $k => $v) { $paramsCheck[] = &$ticketIds[$k]; }
            call_user_func_array([$stmtCheck, 'bind_param'], $paramsCheck);
            $stmtCheck->execute();
            $resCheck = $stmtCheck->get_result();
            $validIds = [];
            while ($row = $resCheck->fetch_assoc()) {
                $validIds[] = (int)$row['id'];
            }
            $ticketIds = $validIds;
            if (empty($ticketIds)) {
                $postErrors[] = 'No tienes permisos sobre los tickets seleccionados.';
            }
        }

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
                            if ($stName !== '' && (str_contains($stName, 'cerrad') || str_contains($stName, 'closed'))) {
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
                        // Notificaciones internas si es un estado relevante
                        if ($statusId === 2 || $statusId === 3) {
                            $statusName = ($statusId === 2) ? 'En Camino' : 'En Proceso';
                            foreach ($ticketIds as $tidBulk) {
                                notifyStatusChangeToAdminRecipients($tidBulk, $statusName);
                            }
                        }
                    } else {
                        $postErrors[] = 'Error al cambiar el estado.';
                    }
                }
            } elseif ($_POST['do'] === 'bulk_delete' || $_POST['do'] === 'bulk_delete_request') {
                $isRequest = ($_POST['do'] === 'bulk_delete_request' || $isAgent);
                
                if ($isRequest) {
                    // --- LÓGICA DE SOLICITUD DE BORRADO (PARA AGENTES) ---
                    $reason = trim($_POST['bulk_delete_reason'] ?? '');
                    if (!$reason && $isAgent) {
                        $postErrors[] = 'Indique un motivo para el borrado.';
                    } else {
                        try {
                            if (!$reason) $reason = 'Borrado masivo solicitado.';
                            $stmtGet = $mysqli->prepare("SELECT id, ticket_number, subject FROM tickets WHERE empresa_id = ? AND id IN ($placeholders)");
                            $typesGet = 'i' . $typesIds;
                            $paramsGet = [&$typesGet, &$eid];
                            foreach ($ticketIds as $k => $v) { $paramsGet[] = &$ticketIds[$k]; }
                            call_user_func_array([$stmtGet, 'bind_param'], $paramsGet);
                            if ($stmtGet->execute()) {
                                $resGet = $stmtGet->get_result();
                                $stmtIns = $mysqli->prepare("INSERT INTO ticket_deletion_requests (ticket_id, empresa_id, ticket_number, ticket_subject, requested_by, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                                while ($row = $resGet->fetch_assoc()) {
                                    $tid = $row['id'];
                                    $tnum = $row['ticket_number'];
                                    $tsub = $row['subject'];
                                    $stmtIns->bind_param('iissis', $tid, $eid, $tnum, $tsub, $_SESSION['staff_id'], $reason);
                                    $stmtIns->execute();
                                }
                                $postSuccess = 'Solicitudes de borrado enviadas correctamente. Los tickets permanecerán visibles hasta que sean aprobados.';
                            }
                        } catch (Throwable $e) {
                            $postErrors[] = 'Error al enviar las solicitudes.';
                        }
                    }
                } else {
                    // --- LÓGICA DE BORRADO DIRECTO (ADMIN / SUPERVISOR) ---
                    if (!isset($_POST['confirm']) || $_POST['confirm'] !== '1') {
                        $postErrors[] = 'Confirmación requerida.';
                    } else {
                        $mysqli->begin_transaction();
                        try {
                            // LOG: Registrar el borrado masivo en el historial antes de eliminar definitivamente
                            try {
                                $stmtGet = $mysqli->prepare("SELECT id, ticket_number, subject FROM tickets WHERE empresa_id = ? AND id IN ($placeholders)");
                                $typesGet = 'i' . $typesIds;
                                $paramsGet = [&$typesGet, &$eid];
                                foreach ($ticketIds as $k => $v) { $paramsGet[] = &$ticketIds[$k]; }
                                call_user_func_array([$stmtGet, 'bind_param'], $paramsGet);
                                if ($stmtGet->execute()) {
                                    $resGet = $stmtGet->get_result();
                                    $stmtInsLog = $mysqli->prepare("INSERT INTO ticket_deletion_requests (ticket_id, empresa_id, ticket_number, ticket_subject, requested_by, reason, status, resolved_at, resolved_by) VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW(), ?)");
                                    $bulkReason = 'Borrado masivo directo por administrador';
                                    while ($rowLog = $resGet->fetch_assoc()) {
                                        $tidLog = $rowLog['id'];
                                        $tnumLog = $rowLog['ticket_number'];
                                        $tsubLog = $rowLog['subject'];
                                        $stmtInsLog->bind_param('iissisi', $tidLog, $eid, $tnumLog, $tsubLog, $_SESSION['staff_id'], $bulkReason, $_SESSION['staff_id']);
                                        $stmtInsLog->execute();
                                    }
                                }
                            } catch (Throwable $eLog) { /* No bloqueante */ }

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
                                $postSuccess = 'Tickets eliminados correctamente.';
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
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $staffOptions[] = $row;
        if ($selectedStaffId > 0 && (int)($row['id'] ?? 0) === $selectedStaffId) {
            $selectedStaffName = trim($row['firstname'] . ' ' . $row['lastname']);
        }
    }
}
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
