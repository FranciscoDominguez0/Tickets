<?php
// Abrir nuevo ticket (tickets.php?a=open&uid=X)
if (isset($_GET['a']) && $_GET['a'] === 'open' && isset($_SESSION['staff_id'])) {
    $open_uid = isset($_GET['uid']) && is_numeric($_GET['uid']) ? (int) $_GET['uid'] : 0;
    $eid = empresaId();
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
                $generateTicketNumberFromFormat = function ($format) use ($mysqli, $eid) {
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

                $generateTicketNumberFromSequence = function ($sequenceId) use ($mysqli, $eid) {
                    $sequenceId = (int)$sequenceId;
                    if ($sequenceId <= 0) return null;

                    $chkSeq = $mysqli->query("SHOW TABLES LIKE 'sequences'");
                    if (!$chkSeq || $chkSeq->num_rows === 0) return null;

                    $mysqli->query('START TRANSACTION');
                    $stmtSeq = $mysqli->prepare('SELECT next, increment, padding FROM sequences WHERE id = ? AND empresa_id = ? FOR UPDATE');
                    if (!$stmtSeq) {
                        $mysqli->query('ROLLBACK');
                        return null;
                    }
                    $stmtSeq->bind_param('ii', $sequenceId, $eid);
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
                    // Notificación interna si el estado inicial es relevante
                    if ($defaultStatusId === 2 || $defaultStatusId === 3) {
                        $statusName = ($defaultStatusId === 2) ? 'En Camino' : 'En Proceso';
                        notifyStatusChangeToAdminRecipients($new_tid, $statusName);
                    }
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

    require __DIR__ . '/../ticket-open.inc.php';
    return;
}
