<?php
// Módulo: Solicitudes (tickets)
// a=open: abrir nuevo ticket (uid= preselecciona usuario). id=X: vista detallada.

$ticketView = null;
$reply_errors = [];
$reply_success = false;

// Abrir nuevo ticket (tickets.php?a=open&uid=X)
if (isset($_GET['a']) && $_GET['a'] === 'open' && isset($_SESSION['staff_id'])) {
    $open_uid = isset($_GET['uid']) && is_numeric($_GET['uid']) ? (int) $_GET['uid'] : 0;
    $open_errors = [];
    $preSelectedUser = null;
    if ($open_uid > 0) {
        $stmt = $mysqli->prepare("SELECT id, firstname, lastname, email FROM users WHERE id = ?");
        $stmt->bind_param('i', $open_uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $preSelectedUser = $res ? $res->fetch_assoc() : null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'open') {
        if (!isset($_POST['csrf_token']) || !Auth::validateCSRF($_POST['csrf_token'])) {
            $open_errors[] = 'Token de seguridad inválido.';
        } else {
            $user_id = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
            $subject = trim($_POST['subject'] ?? '');
            $body = trim($_POST['body'] ?? '');
            $dept_id = isset($_POST['dept_id']) && is_numeric($_POST['dept_id']) ? (int) $_POST['dept_id'] : 1;
            $priority_id = isset($_POST['priority_id']) && is_numeric($_POST['priority_id']) ? (int) $_POST['priority_id'] : 2;
            $staff_id = isset($_POST['staff_id']) && is_numeric($_POST['staff_id']) ? (int) $_POST['staff_id'] : null;
            if ($staff_id === 0) $staff_id = null;
            if ($user_id <= 0) $open_errors[] = 'Seleccione un usuario.';
            if ($subject === '') $open_errors[] = 'El asunto es obligatorio.';
            if (empty($open_errors)) {
                $ticket_number = 'TKT-' . date('Ymd') . '-' . str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT);
                error_log('[tickets] INSERT tickets via scp/modules/tickets.php open uri=' . ($_SERVER['REQUEST_URI'] ?? '') . ' staff_session=' . (string)($_SESSION['staff_id'] ?? '') . ' user_id=' . (string)$user_id . ' dept_id=' . (string)$dept_id);
                $stmt = $mysqli->prepare("INSERT INTO tickets (ticket_number, user_id, staff_id, dept_id, status_id, priority_id, subject, created) VALUES (?, ?, ?, ?, 1, ?, ?, NOW())");
                // tipos: s (ticket_number), i (user_id), i (staff_id), i (dept_id), i (priority_id), s (subject)
                $stmt->bind_param('siiiis', $ticket_number, $user_id, $staff_id, $dept_id, $priority_id, $subject);
                if ($stmt->execute()) {
                    $new_tid = (int) $mysqli->insert_id;
                    $mysqli->query("INSERT INTO threads (ticket_id, created) VALUES ($new_tid, NOW())");
                    $thread_id = (int) $mysqli->insert_id;
                    if ($body !== '') {
                        $staff_id_entry = (int) ($_SESSION['staff_id'] ?? 0);
                        $stmtE = $mysqli->prepare("INSERT INTO thread_entries (thread_id, staff_id, body, is_internal, created) VALUES (?, ?, ?, 0, NOW())");
                        $stmtE->bind_param('iis', $thread_id, $staff_id_entry, $body);
                        $stmtE->execute();
                    }
                    header('Location: tickets.php?id=' . $new_tid . '&msg=created');
                    exit;
                }
                $open_errors[] = 'Error al crear el ticket.';
            }
        }
    }

    $open_departments = [];
    $r = $mysqli->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
    if ($r) while ($row = $r->fetch_assoc()) $open_departments[] = $row;
    $open_priorities = [];
    $r = $mysqli->query("SELECT id, name FROM priorities ORDER BY level");
    if ($r) while ($row = $r->fetch_assoc()) $open_priorities[] = $row;
    $open_staff = [];
    $r = $mysqli->query("SELECT id, firstname, lastname FROM staff WHERE is_active = 1 ORDER BY firstname, lastname");
    if ($r) while ($row = $r->fetch_assoc()) $open_staff[] = $row;

    // Búsqueda de usuario (como osTicket): no listar todos por defecto
    $open_user_query = trim($_GET['uq'] ?? '');
    $open_user_results = [];
    if ($open_user_query !== '') {
        $term = '%' . $open_user_query . '%';
        $stmt = $mysqli->prepare(
            "SELECT id, firstname, lastname, email, phone
             FROM users
             WHERE firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR phone LIKE ?
             ORDER BY firstname, lastname
             LIMIT 25"
        );
        $stmt->bind_param('ssss', $term, $term, $term, $term);
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
        $stmtSig = $mysqli->prepare('SELECT * FROM staff WHERE id = ? LIMIT 1');
        $stmtSig->bind_param('i', $current_staff_id);
        $stmtSig->execute();
        $staffRow = $stmtSig->get_result()->fetch_assoc();
        if ($staffRow && array_key_exists('signature', $staffRow)) {
            $staff_signature = trim((string)($staffRow['signature'] ?? ''));
            $staff_has_signature = $staff_signature !== '';
        }
    }

    // Cargar ticket con usuario, estado, prioridad, departamento, asignado
    $stmt = $mysqli->prepare(
        "SELECT t.*, u.firstname AS user_first, u.lastname AS user_last, u.email AS user_email,
         s.firstname AS staff_first, s.lastname AS staff_last, s.email AS staff_email,
         d.name AS dept_name, ts.name AS status_name, ts.color AS status_color,
         p.name AS priority_name, p.color AS priority_color
         FROM tickets t
         JOIN users u ON t.user_id = u.id
         LEFT JOIN staff s ON t.staff_id = s.id
         JOIN departments d ON t.dept_id = d.id
         JOIN ticket_status ts ON t.status_id = ts.id
         JOIN priorities p ON t.priority_id = p.id
         WHERE t.id = ?"
    );
    $stmt->bind_param('i', $tid);
    $stmt->execute();
    $res = $stmt->get_result();
    $ticketView = $res ? $res->fetch_assoc() : null;

    if ($ticketView) {
        // Asegurar que exista un thread para este ticket
        $stmt = $mysqli->prepare("SELECT id FROM threads WHERE ticket_id = ?");
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $threadRow = $stmt->get_result()->fetch_assoc();
        if (!$threadRow) {
            $mysqli->query("INSERT INTO threads (ticket_id, created) VALUES ($tid, NOW())");
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['owner', 'block_email', 'delete', 'merge', 'link', 'collab_add'], true)) {
            $csrfOk = isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token']);
        }
        if ($action !== null && isset($_SESSION['staff_id']) && $csrfOk) {
            $ok = false;
            $msg = '';
            if ($action === 'status' && isset($_GET['status_id']) && is_numeric($_GET['status_id'])) {
                $sid = (int) $_GET['status_id'];
                $stmt = $mysqli->prepare("UPDATE tickets SET status_id = ?, updated = NOW() WHERE id = ?");
                $stmt->bind_param('ii', $sid, $tid);
                $ok = $stmt->execute();
                $msg = 'updated';
            } elseif ($action === 'assign') {
                $staff_id = isset($_GET['staff_id']) ? (int) $_GET['staff_id'] : (isset($_POST['staff_id']) ? (int) $_POST['staff_id'] : null);
                if ($staff_id !== null) {
                    $val = $staff_id === 0 ? null : $staff_id;
                    $previousStaffId = $ticketView['staff_id'] ?? null;
                    $stmt = $mysqli->prepare("UPDATE tickets SET staff_id = ?, updated = NOW() WHERE id = ?");
                    $stmt->bind_param('ii', $val, $tid);
                    $ok = $stmt->execute();
                    $msg = 'assigned';

                    // Enviar email al agente asignado (solo si se asignó a alguien y cambió)
                    if ($ok && $val !== null && (string)$previousStaffId !== (string)$val) {
                        $stmtS = $mysqli->prepare('SELECT email, firstname, lastname FROM staff WHERE id = ? AND is_active = 1 LIMIT 1');
                        $stmtS->bind_param('i', $val);
                        if ($stmtS->execute()) {
                            $srow = $stmtS->get_result()->fetch_assoc();
                            $to = trim((string)($srow['email'] ?? ''));
                            if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
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
                $stmt = $mysqli->query("SELECT id FROM ticket_status WHERE LOWER(name) LIKE '%resuelto%' OR LOWER(name) LIKE '%contestado%' LIMIT 1");
                $resolved_id = 4;
                if ($stmt && $row = $stmt->fetch_assoc()) $resolved_id = (int) $row['id'];
                $stmt = $mysqli->prepare("UPDATE tickets SET status_id = ?, updated = NOW() WHERE id = ?");
                $stmt->bind_param('ii', $resolved_id, $tid);
                $ok = $stmt->execute();
                $msg = 'marked';
            } elseif ($action === 'owner' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
                $uid = (int) $_POST['user_id'];
                $stmt = $mysqli->prepare("UPDATE tickets SET user_id = ?, updated = NOW() WHERE id = ?");
                $stmt->bind_param('ii', $uid, $tid);
                $ok = $stmt->execute();
                $msg = 'owner';
            } elseif ($action === 'block_email' && ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['confirm']) && $_GET['confirm'] === '1')) {
                $email = $ticketView['user_email'] ?? '';
                if ($email) {
                    $stmt = $mysqli->prepare("UPDATE users SET status = 'banned', updated = NOW() WHERE id = ?");
                    $stmt->bind_param('i', $ticketView['user_id']);
                    $ok = $stmt->execute();
                    $msg = 'blocked';
                }
            } elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === '1') {
                $stmt = $mysqli->prepare("DELETE FROM tickets WHERE id = ?");
                $stmt->bind_param('i', $tid);
                $ok = $stmt->execute();
                if ($ok) {
                    header('Location: users.php?id=' . (int)$ticketView['user_id'] . '&msg=ticket_deleted');
                    exit;
                }
            } elseif ($action === 'merge' && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($_POST['target_ticket_id'] ?? ''))) {
                $target_input = trim($_POST['target_ticket_id']);
                $target_id = is_numeric($target_input) ? (int) $target_input : 0;
                if ($target_id === 0) {
                    $stmt = $mysqli->prepare("SELECT id FROM tickets WHERE ticket_number = ?");
                    $stmt->bind_param('s', $target_input);
                    $stmt->execute();
                    $r = $stmt->get_result()->fetch_assoc();
                    if ($r) $target_id = (int) $r['id'];
                }
                if ($target_id > 0 && $target_id !== $tid) {
                    $stmt = $mysqli->prepare("SELECT id FROM threads WHERE ticket_id = ?");
                    $stmt->bind_param('i', $target_id);
                    $stmt->execute();
                    $targetThread = $stmt->get_result()->fetch_assoc();
                    if ($targetThread) {
                        $stmt = $mysqli->prepare("SELECT id, user_id, staff_id, body, is_internal, created FROM thread_entries WHERE thread_id = ? ORDER BY created ASC");
                        $stmt->bind_param('i', $thread_id);
                        $stmt->execute();
                        $entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        $ins = $mysqli->prepare("INSERT INTO thread_entries (thread_id, user_id, staff_id, body, is_internal, created) VALUES (?, ?, ?, ?, ?, ?)");
                        foreach ($entries as $e) {
                            $ins->bind_param('iiisis', $targetThread['id'], $e['user_id'], $e['staff_id'], $e['body'], $e['is_internal'], $e['created']);
                            $ins->execute();
                        }
                        $closed = $mysqli->query("SELECT id FROM ticket_status WHERE LOWER(name) LIKE '%cerrado%' LIMIT 1");
                        $closed_id = 5;
                        if ($closed && $r = $closed->fetch_assoc()) $closed_id = (int)$r['id'];
                        $mysqli->prepare("UPDATE tickets SET status_id = ?, closed = NOW(), updated = NOW() WHERE id = ?")->bind_param('ii', $closed_id, $tid)->execute();
                        $ok = true;
                        $msg = 'merged';
                        header('Location: tickets.php?id=' . $target_id . '&msg=merged');
                        exit;
                    }
                }
            } elseif ($action === 'link' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['linked_ticket_id']) && is_numeric($_POST['linked_ticket_id'])) {
                $linked_id = (int) $_POST['linked_ticket_id'];
                if ($linked_id !== $tid && $linked_id > 0) {
                    $tbl = 'ticket_links';
                    $exists = $mysqli->query("SHOW TABLES LIKE 'ticket_links'");
                    if ($exists && $exists->num_rows > 0) {
                        $stmt = $mysqli->prepare("INSERT IGNORE INTO ticket_links (ticket_id, linked_ticket_id) VALUES (?, ?), (?, ?)");
                        $stmt->bind_param('iiii', $tid, $linked_id, $linked_id, $tid);
                        $ok = $stmt->execute();
                        $msg = 'linked';
                    }
                }
            } elseif ($action === 'unlink' && isset($_GET['linked_id']) && is_numeric($_GET['linked_id'])) {
                $linked_id = (int) $_GET['linked_id'];
                $exists = $mysqli->query("SHOW TABLES LIKE 'ticket_links'");
                if ($exists && $exists->num_rows > 0) {
                    $stmt = $mysqli->prepare("DELETE FROM ticket_links WHERE (ticket_id = ? AND linked_ticket_id = ?) OR (ticket_id = ? AND linked_ticket_id = ?)");
                    $stmt->bind_param('iiii', $tid, $linked_id, $linked_id, $tid);
                    $ok = $stmt->execute();
                    $msg = 'unlinked';
                }
            } elseif ($action === 'collab_add' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
                $uid = (int) $_POST['user_id'];
                $exists = $mysqli->query("SHOW TABLES LIKE 'ticket_collaborators'");
                if ($exists && $exists->num_rows > 0) {
                    $stmt = $mysqli->prepare("INSERT IGNORE INTO ticket_collaborators (ticket_id, user_id) VALUES (?, ?)");
                    $stmt->bind_param('ii', $tid, $uid);
                    $ok = $stmt->execute();
                    $msg = 'collab_added';
                }
            } elseif ($action === 'collab_remove' && isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
                $uid = (int) $_GET['user_id'];
                $exists = $mysqli->query("SHOW TABLES LIKE 'ticket_collaborators'");
                if ($exists && $exists->num_rows > 0) {
                    $stmt = $mysqli->prepare("DELETE FROM ticket_collaborators WHERE ticket_id = ? AND user_id = ?");
                    $stmt->bind_param('ii', $tid, $uid);
                    $ok = $stmt->execute();
                    $msg = 'collab_removed';
                }
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
                error_log('[tickets] reply POST scp/modules/tickets.php uri=' . ($_SERVER['REQUEST_URI'] ?? '') . ' tid=' . (string)$tid . ' staff_session=' . (string)($_SESSION['staff_id'] ?? '') . ' internal=' . ($is_internal ? '1' : '0'));
                $new_status_id = isset($_POST['status_id']) && is_numeric($_POST['status_id']) ? (int) $_POST['status_id'] : (int) $ticketView['status_id'];
                $signature_mode = trim($_POST['signature'] ?? 'none');
                if ($body === '') {
                    $reply_errors[] = 'El mensaje no puede estar vacío.';
                } else {
                    $staff_id = (int) ($_SESSION['staff_id'] ?? 0);

                    $stmt = $mysqli->prepare(
                        "INSERT INTO thread_entries (thread_id, staff_id, body, is_internal, created) VALUES (?, ?, ?, ?, NOW())"
                    );
                    $stmt->bind_param('iisi', $thread_id, $staff_id, $body, $is_internal);
                    if ($stmt->execute()) {
                        $entry_id = (int) $mysqli->insert_id;
                        // Actualizar estado del ticket
                        $stmtU = $mysqli->prepare("UPDATE tickets SET status_id = ?, updated = NOW() WHERE id = ?");
                        $stmtU->bind_param('ii', $new_status_id, $tid);
                        $stmtU->execute();
                        if (!$is_internal && $ticketView['staff_id'] === null) {
                            $mysqli->query("UPDATE tickets SET staff_id = $staff_id WHERE id = $tid");
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
                                if (function_exists('finfo_open') && !empty($files['tmp_name'][$i])) {
                                    $fi = @finfo_open(FILEINFO_MIME_TYPE);
                                    if ($fi) {
                                        $detected = @finfo_file($fi, $files['tmp_name'][$i]);
                                        @finfo_close($fi);
                                        if (is_string($detected) && $detected !== '') $mime = $detected;
                                    }
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
            $stmt = $mysqli->prepare("SELECT tl.linked_ticket_id AS id, t.ticket_number, t.subject FROM ticket_links tl JOIN tickets t ON t.id = tl.linked_ticket_id WHERE tl.ticket_id = ?");
            $stmt->bind_param('i', $tid);
            $stmt->execute();
            $ticketView['linked_tickets'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        $resCollab = @$mysqli->query("SHOW TABLES LIKE 'ticket_collaborators'");
        if ($resCollab && $resCollab->num_rows > 0) {
            $stmt = $mysqli->prepare("SELECT tc.user_id, u.firstname, u.lastname, u.email FROM ticket_collaborators tc JOIN users u ON u.id = tc.user_id WHERE tc.ticket_id = ?");
            $stmt->bind_param('i', $tid);
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

// Contadores rápidos
$countOpen = (int) ($mysqli->query("SELECT COUNT(*) c FROM tickets WHERE closed IS NULL")->fetch_assoc()['c'] ?? 0);
$countClosed = (int) ($mysqli->query("SELECT COUNT(*) c FROM tickets WHERE closed IS NOT NULL")->fetch_assoc()['c'] ?? 0);
$countUnassigned = (int) ($mysqli->query("SELECT COUNT(*) c FROM tickets WHERE staff_id IS NULL AND closed IS NULL")->fetch_assoc()['c'] ?? 0);
$countMine = 0;
if (!empty($_SESSION['staff_id'])) {
    $sid = (int) $_SESSION['staff_id'];
    $countMine = (int) ($mysqli->query("SELECT COUNT(*) c FROM tickets WHERE staff_id = $sid AND closed IS NULL")->fetch_assoc()['c'] ?? 0);
}

// Query listado
$whereClauses = [];
$types = '';
$params = [];
if ($filterKey === 'mine') {
    $whereClauses[] = 't.staff_id = ?';
    $types .= 'i';
    $params[] = (int) ($_SESSION['staff_id'] ?? 0);
} elseif ($filterKey !== 'all') {
    $whereClauses[] = $filters[$filterKey]['where'];
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

$sql = "SELECT t.id, t.ticket_number, t.subject, t.created, t.updated, t.closed,
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
        LIMIT 200";

$tickets = [];
if ($types !== '') {
    $stmt = $mysqli->prepare($sql);
    $bind = [$types];
    foreach ($params as $k => $v) { $bind[] = &$params[$k]; }
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

                // Capturar datos previos para notificación (solo cuando se asigna a un agente)
                $ticketsBefore = [];
                if ($staffId !== 0) {
                    $sqlSel = "SELECT t.id, t.ticket_number, t.subject, t.staff_id, d.name AS dept_name\n"
                        . "FROM tickets t\n"
                        . "LEFT JOIN departments d ON d.id = t.dept_id\n"
                        . "WHERE t.id IN ($placeholders)";
                    $stmtSel = $mysqli->prepare($sqlSel);
                    if ($stmtSel) {
                        $paramsSel = [&$typesIds];
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
                    $sqlUp = "UPDATE tickets SET staff_id = NULL, updated = NOW() WHERE id IN ($placeholders)";
                    $stmt = $mysqli->prepare($sqlUp);
                    $params = [&$typesIds];
                    foreach ($ticketIds as $k => $v) { $params[] = &$ticketIds[$k]; }
                    call_user_func_array([$stmt, 'bind_param'], $params);
                } else {
                    $sqlUp = "UPDATE tickets SET staff_id = ?, updated = NOW() WHERE id IN ($placeholders)";
                    $stmt = $mysqli->prepare($sqlUp);
                    $types = 'i' . $typesIds;
                    $params = [&$types, &$staffId];
                    foreach ($ticketIds as $k => $v) { $params[] = &$ticketIds[$k]; }
                    call_user_func_array([$stmt, 'bind_param'], $params);
                }
                if ($stmt->execute()) {
                    // Enviar email al agente asignado (solo si se asignó a alguien y cambió)
                    if ($staffId !== 0 && !empty($ticketsBefore)) {
                        $stmtS = $mysqli->prepare('SELECT email, firstname, lastname FROM staff WHERE id = ? AND is_active = 1 LIMIT 1');
                        if ($stmtS) {
                            $stmtS->bind_param('i', $staffId);
                            if ($stmtS->execute()) {
                                $srow = $stmtS->get_result()->fetch_assoc();
                                $to = trim((string)($srow['email'] ?? ''));
                                if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
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

                    $postSuccess = 'Asignación actualizada.';
                } else {
                    $postErrors[] = 'Error al asignar tickets.';
                }
            } elseif ($_POST['do'] === 'bulk_status') {
                $statusId = isset($_POST['bulk_status_id']) && is_numeric($_POST['bulk_status_id']) ? (int) $_POST['bulk_status_id'] : 0;
                if ($statusId <= 0) {
                    $postErrors[] = 'Seleccione un estado válido.';
                } else {
                    $sqlUp = "UPDATE tickets SET status_id = ?, updated = NOW() WHERE id IN ($placeholders)";
                    $stmt = $mysqli->prepare($sqlUp);
                    $types = 'i' . $typesIds;
                    $params = [&$types, &$statusId];
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
                            $sqlDelEntries = "DELETE te FROM thread_entries te JOIN threads th ON th.id = te.thread_id WHERE th.ticket_id IN ($placeholders)";
                            $stmt = $mysqli->prepare($sqlDelEntries);
                            $params = [&$typesIds];
                            foreach ($ticketIds as $k => $v) { $params[] = &$ticketIds[$k]; }
                            call_user_func_array([$stmt, 'bind_param'], $params);
                            $stmt->execute();

                            $sqlDelThreads = "DELETE FROM threads WHERE ticket_id IN ($placeholders)";
                            $stmt = $mysqli->prepare($sqlDelThreads);
                            $params = [&$typesIds];
                            foreach ($ticketIds as $k => $v) { $params[] = &$ticketIds[$k]; }
                            call_user_func_array([$stmt, 'bind_param'], $params);
                            $stmt->execute();
                        }

                        $sqlDelTickets = "DELETE FROM tickets WHERE id IN ($placeholders)";
                        $stmt = $mysqli->prepare($sqlDelTickets);
                        $params = [&$typesIds];
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
    $redirParams = ['filter' => $redirFilter];
    if ($redirQ !== '') $redirParams['q'] = $redirQ;
    header('Location: tickets.php?' . http_build_query($redirParams));
    exit;
}

// Datos para toolbars
$staffOptions = [];
$r = $mysqli->query("SELECT id, firstname, lastname FROM staff WHERE is_active = 1 ORDER BY firstname, lastname");
if ($r) while ($row = $r->fetch_assoc()) $staffOptions[] = $row;
$statusOptions = [];
$r = $mysqli->query("SELECT id, name FROM ticket_status ORDER BY id");
if ($r) while ($row = $r->fetch_assoc()) $statusOptions[] = $row;
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
                <div class="sub">Abiertos: <strong><?php echo $countOpen; ?></strong> · Sin asignar: <strong><?php echo $countUnassigned; ?></strong> · Míos: <strong><?php echo $countMine; ?></strong></div>
            </div>
            <a href="tickets.php?a=open" class="btn-new"><i class="bi bi-plus-lg me-1"></i> Nuevo</a>
        </div>
    </div>

    <form method="post" id="bulkForm">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="do" id="bulk_do" value="">
        <input type="hidden" name="confirm" id="bulk_confirm" value="0">
        <input type="hidden" name="current_filter" value="<?php echo html($filterKey); ?>">
        <input type="hidden" name="current_q" value="<?php echo html($query); ?>">
        <input type="hidden" name="bulk_staff_id" id="bulk_staff_id" value="">
        <input type="hidden" name="bulk_status_id" id="bulk_status_id" value="">
        <input type="hidden" id="bulk_staff_label" value="">
        <input type="hidden" id="bulk_status_label" value="">

        <div class="tickets-panel" data-filter-key="<?php echo html($filterKey); ?>">
            <div class="tickets-toolbar">
                <div class="tickets-actions">
                <button type="button" class="btn btn-action btn-sm" data-action="tickets-select-all">Seleccionar</button>
                <button type="button" class="btn btn-action btn-sm" data-action="tickets-select-none">Ninguno</button>

                <div class="btn-group">
                    <button type="button" class="btn btn-action btn-sm btn-icon" title="Asignar">
                        <i class="bi bi-person"></i>
                    </button>
                    <button type="button" class="btn btn-action btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="visually-hidden">Toggle Dropdown</span>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-action="tickets-bulk-assign" data-staff-id="0" data-staff-label="— Sin asignar —">— Sin asignar —</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php foreach ($staffOptions as $s): ?>
                            <?php $sn = trim($s['firstname'] . ' ' . $s['lastname']); ?>
                            <li><a class="dropdown-item" href="#" data-action="tickets-bulk-assign" data-staff-id="<?php echo (int) $s['id']; ?>" data-staff-label="<?php echo html($sn); ?>"><?php echo html($sn); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-action btn-sm btn-icon" title="Cambiar estado">
                        <i class="bi bi-flag"></i>
                    </button>
                    <button type="button" class="btn btn-action btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="visually-hidden">Toggle Dropdown</span>
                    </button>
                    <ul class="dropdown-menu">
                        <?php foreach ($statusOptions as $st): ?>
                            <li><a class="dropdown-item" href="#" data-action="tickets-bulk-status" data-status-id="<?php echo (int) $st['id']; ?>" data-status-label="<?php echo html($st['name']); ?>"><?php echo html($st['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <button type="button" class="btn btn-danger-soft btn-sm" data-action="tickets-bulk-delete"><i class="bi bi-trash"></i> Eliminar</button>
                </div>

                <div class="text-muted" style="font-size: 0.85rem; font-weight: 700;">
                    Máx 200 resultados
                </div>
            </div>
        </div>

        <div class="tickets-panel">
            <div class="tickets-toolbar">
                <div class="dropdown filter-dd">
                    <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-funnel"></i>
                        <?php echo html($filters[$filterKey]['label']); ?>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?php echo $filterKey === 'open' ? 'active' : ''; ?>" href="tickets.php?filter=open<?php echo $query !== '' ? '&q=' . urlencode($query) : ''; ?>">Abiertos</a></li>
                        <li><a class="dropdown-item <?php echo $filterKey === 'unassigned' ? 'active' : ''; ?>" href="tickets.php?filter=unassigned<?php echo $query !== '' ? '&q=' . urlencode($query) : ''; ?>">Sin asignar</a></li>
                        <li><a class="dropdown-item <?php echo $filterKey === 'mine' ? 'active' : ''; ?>" href="tickets.php?filter=mine<?php echo $query !== '' ? '&q=' . urlencode($query) : ''; ?>">Asignados a mí</a></li>
                        <li><a class="dropdown-item <?php echo $filterKey === 'closed' ? 'active' : ''; ?>" href="tickets.php?filter=closed<?php echo $query !== '' ? '&q=' . urlencode($query) : ''; ?>">Cerrados</a></li>
                        <li><a class="dropdown-item <?php echo $filterKey === 'all' ? 'active' : ''; ?>" href="tickets.php?filter=all<?php echo $query !== '' ? '&q=' . urlencode($query) : ''; ?>">Todos</a></li>
                    </ul>
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
        <table class="table table-hover tickets-table mb-0">
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
                        ?>
                        <tr class="ticket-row">
                            <td class="check-cell"><input class="form-check-input ticket-check" type="checkbox" name="ticket_ids[]" value="<?php echo (int) $t['id']; ?>"></td>
                            <td>
                                <div class="ticket-title"><?php echo html($t['ticket_number']); ?></div>
                                <div class="ticket-subject"><?php echo html($t['subject']); ?></div>
                                <div class="ticket-meta">Asignado: <?php echo $staffName ?: '— Sin asignar —'; ?></div>
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
                                <a href="tickets.php?id=<?php echo (int) $t['id']; ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
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

