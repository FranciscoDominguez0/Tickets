<?php
require __DIR__ . '/tickets/tickets-bootstrap.inc.php';


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
            // Limpiar entidades HTML en el asunto (teclados móviles pueden insertar &nbsp; literales)
            if (function_exists('cleanPlainText')) {
                $subject = cleanPlainText($subject);
            }
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
         d.name AS dept_name, d.requires_report, ts.name AS status_name, ts.color AS status_color,
         p.name AS priority_name, p.color AS priority_color,
         (CASE WHEN tr.id IS NOT NULL THEN 1 ELSE 0 END) AS has_report,
         tr.final_price AS report_final_price"
         . $topicSelect .
        " FROM tickets t
         JOIN users u ON t.user_id = u.id
         LEFT JOIN staff s ON t.staff_id = s.id"
         . $topicJoin .
        " JOIN departments d ON t.dept_id = d.id
         JOIN ticket_status ts ON t.status_id = ts.id
         JOIN priorities p ON t.priority_id = p.id
         LEFT JOIN ticket_reports tr ON tr.ticket_id = t.id
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
            
            // Determinar los diferentes directorios base posibles
            $baseScp = dirname(__DIR__); // upload/scp
            $baseUpload = dirname(__DIR__, 2); // upload
            $baseRoot = dirname(__DIR__, 3);   // sistema-tickets
            
            $full1 = rtrim($baseUpload, '/\\') . '/' . ltrim($rel, '/'); // upload/uploads/attachments/...
            $full = '';
            // El principal y definitivo directorio donde están ahora (upload/uploads/attachments/)
            $fullDir = defined('ATTACHMENTS_DIR') ? ATTACHMENTS_DIR . '/' . ltrim(str_replace('uploads/attachments/', '', $rel), '/') : '';

            if ($rel !== '') {
                if ($fullDir !== '' && is_file($fullDir)) {
                    $full = $fullDir;
                } elseif (is_file($full1)) {
                    $full = $full1;
                } elseif (is_file($full2)) {
                    $full = $full2;
                } elseif (is_file($full3)) {
                    $full = $full3;
                }
            }

            if ($full === '') {
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['owner', 'block_email', 'delete', 'merge', 'link', 'collab_add', 'transfer', 'priority_update'], true)) {
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
                        if ($stName !== '' && (str_contains($stName, 'cerrad') || str_contains($stName, 'closed'))) {
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
                        $ticketSubject = (string)($ticketView['subject'] ?? '');
                        $ticketTopic = (string)($ticketView['topic_name'] ?? 'General');
                        $subjClient = '[Ticket En Camino] ' . $ticketNo . ' - ' . $ticketSubject;
                        
                        $bodyHtmlClient = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
                            . '<h2 style="margin:0 0 10px;color:#1e3a5f;">Técnicos en camino</h2>'
                            . '<p>Estimado usuario, los técnicos ya van en camino para atender su solicitud.</p>'
                            . '<div style="background:#f8fafc; border:1px solid #e2e8f0; padding:14px; border-radius:10px; margin-top:14px;">'
                            . '<p style="margin:0 0 8px;"><strong>ID del Ticket:</strong> ' . html($ticketNo) . '</p>'
                            . '<p style="margin:0 0 8px;"><strong>Tema:</strong> ' . html($ticketTopic) . '</p>'
                            . '<p style="margin:0;"><strong>Asunto:</strong> ' . html($ticketSubject) . '</p>'
                            . '</div>'
                            . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . html((string)(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets')) . '</p>'
                            . '</div>';
                        $bodyTextClient = "Estimado usuario, los técnicos ya van en camino para atender su solicitud.\n\n"
                            . "ID del Ticket: $ticketNo\n"
                            . "Tema: $ticketTopic\n"
                            . "Asunto: $ticketSubject";
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

                // Si cambió a En Proceso (id=3), notificar al usuario
                if ($ok && $sid === 3 && (int)($ticketView['status_id'] ?? 0) !== 3) {
                    $toClient = trim((string)($ticketView['user_email'] ?? ''));
                    if ($toClient !== '' && filter_var($toClient, FILTER_VALIDATE_EMAIL)) {
                        $ticketNo = (string)($ticketView['ticket_number'] ?? ('#' . $tid));
                        $ticketSubject = (string)($ticketView['subject'] ?? '');
                        $ticketTopic = (string)($ticketView['topic_name'] ?? 'General');
                        $subjClient = '[Ticket En Proceso] ' . $ticketNo . ' - ' . $ticketSubject;
                        
                        $viewUrl = (defined('APP_URL') ? APP_URL : '') . '/upload/tickets.php?id=' . (int) $tid;
                        $bodyHtmlClient = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
                            . '<h2 style="margin:0 0 10px;color:#1e3a5f;">Técnicos han llegado al lugar</h2>'
                            . '<p>Estimado usuario, los técnicos han llegado al lugar o su ticket está en proceso.</p>'
                            . '<div style="background:#f8fafc; border:1px solid #e2e8f0; padding:14px; border-radius:10px; margin-top:14px;">'
                            . '<p style="margin:0 0 8px;"><strong>ID del Ticket:</strong> ' . html($ticketNo) . '</p>'
                            . '<p style="margin:0 0 8px;"><strong>Tema:</strong> ' . html($ticketTopic) . '</p>'
                            . '<p style="margin:0;"><strong>Asunto:</strong> ' . html($ticketSubject) . '</p>'
                            . '</div>'
                            . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . html((string)(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets')) . '</p>'
                            . '</div>';
                        $bodyTextClient = "Estimado usuario, los técnicos han llegado al lugar o su ticket está en proceso.\n\n"
                            . "ID del Ticket: $ticketNo\n"
                            . "Tema: $ticketTopic\n"
                            . "Asunto: $ticketSubject";
                        if (function_exists('enqueueEmailJob')) {
                            enqueueEmailJob($toClient, $subjClient, $bodyHtmlClient, $bodyTextClient, [
                                'empresa_id' => (int)$eid,
                                'context_type' => 'ticket_en_proceso_client',
                                'context_id' => (int)$tid,
                            ]);
                            if (function_exists('triggerEmailQueueWorkerAsync')) {
                                triggerEmailQueueWorkerAsync();
                            }
                        } else {
                            Mailer::send($toClient, $subjClient, $bodyHtmlClient, $bodyTextClient);
                        }
                        addLog('ticket_en_proceso_email', 'Notificación En Proceso encolada/enviada al usuario ' . $toClient, 'ticket', $tid);
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
            } elseif ($action === 'priority_update' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['priority_id']) && is_numeric($_POST['priority_id'])) {
                requireRolePermission('ticket.edit', 'tickets.php?id=' . $tid);
                $pid = (int) $_POST['priority_id'];
                $stmt = $mysqli->prepare("UPDATE tickets SET priority_id = ?, updated = NOW() WHERE id = ? AND empresa_id = ?");
                $stmt->bind_param('iii', $pid, $tid, $eid);
                $ok = $stmt->execute();
                $msg = 'priority_updated';
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
                if ($msg === 'updated' && $isClosingStatus && (int)($ticketView['requires_report'] ?? 0) === 1) {
                    $msg = 'closed_report';
                }
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
                                $ticketSubject = (string)($ticketView['subject'] ?? '');
                                $ticketTopic = (string)($ticketView['topic_name'] ?? 'General');
                                $subjClient = '[Ticket En Camino] ' . $ticketNo . ' - ' . $ticketSubject;
                                
                                $bodyHtmlClient = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
                                    . '<h2 style="margin:0 0 10px;color:#1e3a5f;">Técnicos en camino</h2>'
                                    . '<p>Estimado usuario, los técnicos ya van en camino para atender su solicitud.</p>'
                                    . '<div style="background:#f8fafc; border:1px solid #e2e8f0; padding:14px; border-radius:10px; margin-top:14px;">'
                                    . '<p style="margin:0 0 8px;"><strong>ID del Ticket:</strong> ' . html($ticketNo) . '</p>'
                                    . '<p style="margin:0 0 8px;"><strong>Tema:</strong> ' . html($ticketTopic) . '</p>'
                                    . '<p style="margin:0;"><strong>Asunto:</strong> ' . html($ticketSubject) . '</p>'
                                    . '</div>'
                                    . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . html((string)(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets')) . '</p>'
                                    . '</div>';
                                $bodyTextClient = "Estimado usuario, los técnicos ya van en camino para atender su solicitud.\n\n"
                                    . "ID del Ticket: $ticketNo\n"
                                    . "Tema: $ticketTopic\n"
                                    . "Asunto: $ticketSubject";
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

                        // Si cambió a En Proceso (id=3), notificar al usuario
                        if ($new_status_id === 3 && (int)($ticketView['status_id'] ?? 0) !== 3) {
                            $toClient = trim((string)($ticketView['user_email'] ?? ''));
                            if ($toClient !== '' && filter_var($toClient, FILTER_VALIDATE_EMAIL)) {
                                $ticketNo = (string)($ticketView['ticket_number'] ?? ('#' . $tid));
                                $ticketSubject = (string)($ticketView['subject'] ?? '');
                                $ticketTopic = (string)($ticketView['topic_name'] ?? 'General');
                                $subjClient = '[Ticket En Proceso] ' . $ticketNo . ' - ' . $ticketSubject;
                                
                                $viewUrl = (defined('APP_URL') ? APP_URL : '') . '/upload/tickets.php?id=' . (int) $tid;
                                $bodyHtmlClient = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
                                    . '<h2 style="margin:0 0 10px;color:#1e3a5f;">Técnicos han llegado al lugar</h2>'
                                    . '<p>Estimado usuario, los técnicos han llegado al lugar o su ticket está en proceso.</p>'
                                    . '<div style="background:#f8fafc; border:1px solid #e2e8f0; padding:14px; border-radius:10px; margin-top:14px;">'
                                    . '<p style="margin:0 0 8px;"><strong>ID del Ticket:</strong> ' . html($ticketNo) . '</p>'
                                    . '<p style="margin:0 0 8px;"><strong>Tema:</strong> ' . html($ticketTopic) . '</p>'
                                    . '<p style="margin:0;"><strong>Asunto:</strong> ' . html($ticketSubject) . '</p>'
                                    . '</div>'
                                    . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . html((string)(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets')) . '</p>'
                                    . '</div>';
                                $bodyTextClient = "Estimado usuario, los técnicos han llegado al lugar o su ticket está en proceso.\n\n"
                                    . "ID del Ticket: $ticketNo\n"
                                    . "Tema: $ticketTopic\n"
                                    . "Asunto: $ticketSubject";
                                if (function_exists('enqueueEmailJob')) {
                                    enqueueEmailJob($toClient, $subjClient, $bodyHtmlClient, $bodyTextClient, [
                                        'empresa_id' => (int)$eid,
                                        'context_type' => 'ticket_en_proceso_client',
                                        'context_id' => (int)$tid,
                                    ]);
                                    if (function_exists('triggerEmailQueueWorkerAsync')) {
                                        triggerEmailQueueWorkerAsync();
                                    }
                                } else {
                                    Mailer::send($toClient, $subjClient, $bodyHtmlClient, $bodyTextClient);
                                }
                                addLog('ticket_en_proceso_email', 'Notificación En Proceso encolada/enviada al usuario ' . $toClient, 'ticket', $tid);
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
                        $uploadDir = defined('ATTACHMENTS_DIR') ? ATTACHMENTS_DIR : dirname(__DIR__, 3) . '/uploads/attachments';
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
                        $msgFinal = 'reply_sent';
                        if ($isClosingStatus && (int)($ticketView['requires_report'] ?? 0) === 1) {
                            $msgFinal = 'closed_report';
                        }
                        header('Location: tickets.php?id=' . $tid . '&msg=' . $msgFinal);
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


require __DIR__ . '/tickets/tickets-list-controller.inc.php';
require __DIR__ . '/tickets/tickets-list-view.inc.php';
