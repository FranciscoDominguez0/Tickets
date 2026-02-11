<?php
/**
 * CREAR TICKET
 * Formulario para que usuarios creen nuevos tickets
 */

require_once '../config.php';
require_once '../includes/helpers.php';

// Validar que sea cliente
requireLogin('cliente');

$user = getCurrentUser();
$error = '';
$success = '';

if ($_POST) {
    if (!validateCSRF()) {
        $error = 'Token de seguridad inválido';
    } else {
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $topic_id = intval($_POST['topic_id'] ?? 0);
        $dept_id = intval($_POST['dept_id'] ?? 0);
        $hasFiles = !empty($_FILES['attachments']['name'][0]);
        $plain = trim(str_replace("\xC2\xA0", ' ', html_entity_decode(strip_tags($body), ENT_QUOTES, 'UTF-8')));

        $hasTopicsTable = false;
        $hasTopicCol = false;
        $t = $mysqli->query("SHOW TABLES LIKE 'help_topics'");
        if ($t && $t->num_rows > 0) $hasTopicsTable = true;
        $c = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'topic_id'");
        if ($c && $c->num_rows > 0) $hasTopicCol = true;

        $defaultHelpTopic = (int)getAppSetting('tickets.default_help_topic', '0');
        if ($topic_id <= 0 && $defaultHelpTopic > 0) {
            $topic_id = $defaultHelpTopic;
        }

        // Si se seleccionó un tema, tomar el dept_id directamente desde la BD
        // (en el formulario el selector de departamento puede estar oculto)
        if ($topic_id > 0) {
            $stmtTopicDept = $mysqli->prepare('SELECT dept_id FROM help_topics WHERE id = ? LIMIT 1');
            if ($stmtTopicDept) {
                $stmtTopicDept->bind_param('i', $topic_id);
                if ($stmtTopicDept->execute()) {
                    $tr = $stmtTopicDept->get_result()->fetch_assoc();
                    $deptFromTopic = (int) ($tr['dept_id'] ?? 0);
                    if ($deptFromTopic > 0) {
                        $dept_id = $deptFromTopic;
                    }
                }
            }
        }

        // Fallback final
        if ($dept_id <= 0) {
            $dept_id = 1;
        }

        // Asignación automática por departamento (si está configurado)
        $generalDeptId = 0;
        $rgd = $mysqli->query("SELECT id FROM departments WHERE LOWER(name) LIKE '%general%' LIMIT 1");
        if ($rgd && ($row = $rgd->fetch_assoc())) {
            $generalDeptId = (int) ($row['id'] ?? 0);
        }

        $defaultStaffId = null;
        $hasDeptDefaultStaff = false;
        $chkCol = $mysqli->query("SHOW COLUMNS FROM departments LIKE 'default_staff_id'");
        if ($chkCol && $chkCol->num_rows > 0) $hasDeptDefaultStaff = true;
        if ($hasDeptDefaultStaff && $dept_id > 0) {
            $stmtDef = $mysqli->prepare('SELECT default_staff_id FROM departments WHERE id = ? AND is_active = 1 LIMIT 1');
            if ($stmtDef) {
                $stmtDef->bind_param('i', $dept_id);
                if ($stmtDef->execute()) {
                    $v = (int)($stmtDef->get_result()->fetch_assoc()['default_staff_id'] ?? 0);
                    if ($v > 0) {
                        $allowed = false;
                        $stmtSd = $mysqli->prepare('SELECT COALESCE(NULLIF(dept_id, 0), ?) AS dept_id FROM staff WHERE id = ? AND is_active = 1 LIMIT 1');
                        if ($stmtSd) {
                            $stmtSd->bind_param('ii', $generalDeptId, $v);
                            if ($stmtSd->execute()) {
                                $sdept = (int)($stmtSd->get_result()->fetch_assoc()['dept_id'] ?? 0);
                                $allowed = ($sdept === $dept_id);
                            }
                        }
                        if ($allowed) {
                            $defaultStaffId = $v;
                        }
                    }
                }
            }
        }

        $hasMedia = (stripos($body, '<img') !== false || stripos($body, '<iframe') !== false);
        $isBodyEmpty = ($plain === '' && !$hasMedia);

        if ($hasTopicsTable && $hasTopicCol && $defaultHelpTopic <= 0 && $topic_id <= 0) {
            $error = 'Debes seleccionar un tema.';
        } elseif (empty($subject) || $isBodyEmpty) {
            $error = 'Asunto y descripción son requeridos';
        } elseif ($hasFiles && $plain === '' && stripos($body, '<img') === false && stripos($body, '<iframe') === false) {
            $error = 'Debes escribir una descripción para enviar archivos. Si solo quieres adjuntar, escribe una breve descripción.';
        } elseif (stripos($body, 'data:image/') !== false) {
            $error = 'Las imágenes pegadas dentro del texto no están soportadas. Adjunta la imagen usando la opción de archivos.';
        } elseif (strlen($body) > 500000) {
            $error = 'La descripción es demasiado grande. Por favor adjunta archivos en vez de pegarlos dentro del texto.';
        } else {
            $maxOpenTicketsSetting = (int)getAppSetting('tickets.max_open_tickets', '0');
            if ($maxOpenTicketsSetting > 0 && (int)($_SESSION['user_id'] ?? 0) > 0) {
                $hasClosedCol = false;
                $c = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'closed'");
                if ($c && $c->num_rows > 0) $hasClosedCol = true;

                if ($hasClosedCol) {
                    $stmtCnt = $mysqli->prepare('SELECT COUNT(*) AS cnt FROM tickets WHERE user_id = ? AND closed IS NULL');
                } else {
                    $stmtCnt = $mysqli->prepare('SELECT COUNT(*) AS cnt FROM tickets WHERE user_id = ?');
                }
                if ($stmtCnt) {
                    $uid = (int)$_SESSION['user_id'];
                    $stmtCnt->bind_param('i', $uid);
                    $stmtCnt->execute();
                    $cntRow = $stmtCnt->get_result()->fetch_assoc();
                    $openCount = (int)($cntRow['cnt'] ?? 0);
                    if ($openCount >= $maxOpenTicketsSetting) {
                        $error = 'Has alcanzado el máximo de tickets abiertos.';
                    }
                }
            }

            if ($error === '') {
            // Generar número de ticket
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
                    $stmtChk = $mysqli->prepare('SELECT id FROM tickets WHERE ticket_number = ? LIMIT 1');
                    if (!$stmtChk) return $num;
                    $stmtChk->bind_param('s', $num);
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
            
            // Verificar si existe la estructura de temas
            $hasTopicCol = false;
            $hasTopicsTable = false;
            $c = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'topic_id'");
            if ($c && $c->num_rows > 0) $hasTopicCol = true;
            $t = $mysqli->query("SHOW TABLES LIKE 'help_topics'");
            if ($t && $t->num_rows > 0) $hasTopicsTable = true;

            $hasPriorityCol = false;
            $cp = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'priority_id'");
            if ($cp && $cp->num_rows > 0) $hasPriorityCol = true;

            $hasStaffIdCol = false;
            $cs = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'staff_id'");
            if ($cs && $cs->num_rows > 0) $hasStaffIdCol = true;

            $defaultStatusId = (int)getAppSetting('tickets.default_ticket_status_id', '1');
            if ($defaultStatusId <= 0) $defaultStatusId = 1;
            $defaultPriorityId = (int)getAppSetting('tickets.default_priority_id', '1');
            if ($defaultPriorityId <= 0) $defaultPriorityId = 1;
            
            // Insertar ticket
            error_log('[tickets] INSERT tickets via upload/open.php uri=' . ($_SERVER['REQUEST_URI'] ?? '') . ' user_id=' . (string)($_SESSION['user_id'] ?? '') . ' dept_id=' . (string)$dept_id . ' topic_id=' . (string)$topic_id);
            
            $uid = (int)$_SESSION['user_id'];
            $staffVal = ($hasStaffIdCol && $defaultStaffId !== null) ? (int)$defaultStaffId : null;
            if ($hasTopicCol && $hasTopicsTable && $topic_id > 0) {
                if ($hasStaffIdCol && $staffVal !== null && $hasPriorityCol) {
                    $stmt = $mysqli->prepare(
                        'INSERT INTO tickets (ticket_number, user_id, staff_id, dept_id, topic_id, status_id, priority_id, subject, created) '
                        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->bind_param('siiiiiis', $ticket_number, $uid, $staffVal, $dept_id, $topic_id, $defaultStatusId, $defaultPriorityId, $subject);
                } elseif ($hasStaffIdCol && $staffVal !== null) {
                    $stmt = $mysqli->prepare(
                        'INSERT INTO tickets (ticket_number, user_id, staff_id, dept_id, topic_id, status_id, subject, created) '
                        . 'VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->bind_param('siiiiis', $ticket_number, $uid, $staffVal, $dept_id, $topic_id, $defaultStatusId, $subject);
                } elseif ($hasPriorityCol) {
                    $stmt = $mysqli->prepare(
                        'INSERT INTO tickets (ticket_number, user_id, dept_id, topic_id, status_id, priority_id, subject, created) '
                        . 'VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->bind_param('siiiiis', $ticket_number, $uid, $dept_id, $topic_id, $defaultStatusId, $defaultPriorityId, $subject);
                } else {
                    $stmt = $mysqli->prepare(
                        'INSERT INTO tickets (ticket_number, user_id, dept_id, topic_id, status_id, subject, created) '
                        . 'VALUES (?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->bind_param('siiiis', $ticket_number, $uid, $dept_id, $topic_id, $defaultStatusId, $subject);
                }
            } else {
                if ($hasStaffIdCol && $staffVal !== null && $hasPriorityCol) {
                    $stmt = $mysqli->prepare(
                        'INSERT INTO tickets (ticket_number, user_id, staff_id, dept_id, status_id, priority_id, subject, created) '
                        . 'VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->bind_param('siiiiis', $ticket_number, $uid, $staffVal, $dept_id, $defaultStatusId, $defaultPriorityId, $subject);
                } elseif ($hasStaffIdCol && $staffVal !== null) {
                    $stmt = $mysqli->prepare(
                        'INSERT INTO tickets (ticket_number, user_id, staff_id, dept_id, status_id, subject, created) '
                        . 'VALUES (?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->bind_param('siiiis', $ticket_number, $uid, $staffVal, $dept_id, $defaultStatusId, $subject);
                } elseif ($hasPriorityCol) {
                    $stmt = $mysqli->prepare(
                        'INSERT INTO tickets (ticket_number, user_id, dept_id, status_id, priority_id, subject, created) '
                        . 'VALUES (?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->bind_param('siiiis', $ticket_number, $uid, $dept_id, $defaultStatusId, $defaultPriorityId, $subject);
                } else {
                    $stmt = $mysqli->prepare(
                        'INSERT INTO tickets (ticket_number, user_id, dept_id, status_id, subject, created) '
                        . 'VALUES (?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->bind_param('siiis', $ticket_number, $uid, $dept_id, $defaultStatusId, $subject);
                }
            }
            
            if ($stmt->execute()) {
                $ticket_id = $mysqli->insert_id;
                
                // Crear thread y primer mensaje
                $stmt2 = $mysqli->prepare('INSERT INTO threads (ticket_id, created) VALUES (?, NOW())');
                $stmt2->bind_param('i', $ticket_id);
                $stmt2->execute();
                $thread_id = $mysqli->insert_id;
                
                $stmt3 = $mysqli->prepare(
                    'INSERT INTO thread_entries (thread_id, user_id, body, created)
                     VALUES (?, ?, ?, NOW())'
                );
                $stmt3->bind_param('iis', $thread_id, $_SESSION['user_id'], $body);
                $stmt3->execute();
                $entry_id = (int) $mysqli->insert_id;

                // Notificación + correo al agente asignado por departamento por defecto
                if ($defaultStaffId !== null && (int)$defaultStaffId > 0) {
                    $val = (int) $defaultStaffId;
                    addLog('ticket_assigned_by_dept_default_user', 'Asignación automática por departamento (desde usuario)', 'ticket', $ticket_id, 'staff', $val);

                    // Notificación en BD
                    $message = 'Se te asignó el ticket ' . (string)$ticket_number . ': ' . (string)$subject;
                    $type = 'ticket_assigned';
                    $stmtN = $mysqli->prepare('INSERT INTO notifications (staff_id, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
                    if ($stmtN) {
                        $stmtN->bind_param('issi', $val, $message, $type, $ticket_id);
                        $stmtN->execute();
                    } else {
                        addLog('ticket_assign_notification_failed', 'No se pudo preparar INSERT notifications (user)', 'ticket', $ticket_id, 'staff', $val);
                    }

                    // Correo al agente
                    $stmtE = $mysqli->prepare('SELECT firstname, lastname, email FROM staff WHERE id = ? AND is_active = 1 LIMIT 1');
                    if ($stmtE) {
                        $stmtE->bind_param('i', $val);
                        if ($stmtE->execute()) {
                            $r = $stmtE->get_result()->fetch_assoc();
                            if ($r && !empty($r['email'])) {
                                $to = $r['email'];
                                $subj = 'Ticket asignado: ' . (string)$ticket_number;
                                $staffName = trim(($r['firstname'] ?? '') . ' ' . ($r['lastname'] ?? ''));
                                if ($staffName === '') $staffName = 'Agente';
                                $viewUrl = (defined('APP_URL') ? APP_URL : '') . '/upload/scp/tickets.php?id=' . (string)$ticket_id;
                                $bodyHtml = '<div style="font-family: Segoe UI, sans-serif; max-width: 700px; margin: 0 auto;">'
                                    . '<h2 style="color:#1e3a5f; margin: 0 0 8px;">Se te asignó un ticket</h2>'
                                    . '<p style="color:#475569; margin: 0 0 12px;">Hola <strong>' . htmlspecialchars($staffName) . '</strong>, se te asignó el siguiente ticket:</p>'
                                    . '<table style="width:100%; border-collapse: collapse; margin: 12px 0;">'
                                    . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee;"><strong>Número:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">' . htmlspecialchars((string)$ticket_number) . '</td></tr>'
                                    . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee;"><strong>Asunto:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">' . htmlspecialchars((string)$subject) . '</td></tr>'
                                    . '</table>'
                                    . '<p style="margin: 14px 0 0;"><a href="' . htmlspecialchars($viewUrl) . '" style="display:inline-block; background:#2563eb; color:#fff; padding:10px 16px; text-decoration:none; border-radius:8px;">Ver ticket</a></p>'
                                    . '<p style="color:#94a3b8; font-size:12px; margin-top: 14px;">' . htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets') . '</p>'
                                    . '</div>';
                                $bodyText = 'Hola ' . $staffName . ",\nSe te ha asignado el ticket " . (string)$ticket_number . ": " . (string)$subject . "\n\nVer: " . $viewUrl;
                                $mailOk = Mailer::send($to, $subj, $bodyHtml, $bodyText);
                                if (!$mailOk) {
                                    $err = (string)(Mailer::$lastError ?? 'Error desconocido');
                                    addLog('ticket_assign_email_failed_user', $err, 'ticket', $ticket_id, 'staff', $val);
                                }
                            } else {
                                addLog('ticket_assign_email_missing_user', 'Agente sin email válido (user)', 'ticket', $ticket_id, 'staff', $val);
                            }
                        } else {
                            addLog('ticket_assign_email_lookup_failed_user', 'No se pudo ejecutar SELECT staff(email) (user)', 'ticket', $ticket_id, 'staff', $val);
                        }
                    } else {
                        addLog('ticket_assign_email_lookup_failed_user', 'No se pudo preparar SELECT staff(email) (user)', 'ticket', $ticket_id, 'staff', $val);
                    }
                }

                // Adjuntos: guardar archivos y registrar en BD
                $uploadDir = __DIR__ . '/uploads/attachments';
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
                        $n = 1;
                    }
                    for ($i = 0; $i < $n; $i++) {
                        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                        $orig = (string) ($files['name'][$i] ?? '');
                        $mime = (string) ($files['type'][$i] ?? '');
                        $size = (int) ($files['size'][$i] ?? 0);
                        if ($orig === '' || $size <= 0) continue;
                        if ($size > $maxSize) continue;
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

                // Notificar por correo solo al admin
                $adminEmail = trim((string)getAppSetting('mail.admin_notify_email', defined('ADMIN_NOTIFY_EMAIL') ? (string)ADMIN_NOTIFY_EMAIL : ''));
                $clientName = trim(($user['name'] ?? '') ?: 'Cliente');
                $clientEmail = $user['email'] ?? '';
                $deptName = 'Soporte';
                $stmtDept = $mysqli->prepare('SELECT name FROM departments WHERE id = ?');
                $stmtDept->bind_param('i', $dept_id);
                $stmtDept->execute();
                if ($r = $stmtDept->get_result()->fetch_assoc()) {
                    $deptName = $r['name'];
                }
                $viewUrl = (defined('APP_URL') ? APP_URL : '') . '/upload/scp/tickets.php?id=' . (int) $ticket_id;

                $bodyEmailText = trim(str_replace("\xC2\xA0", ' ', html_entity_decode(strip_tags((string)$body), ENT_QUOTES, 'UTF-8')));

                $bodyHtml = '
                    <div style="font-family: Segoe UI, sans-serif; max-width: 600px; margin: 0 auto;">
                        <h2 style="color: #2c3e50;">Nuevo ticket creado</h2>
                        <p>Se ha abierto un nuevo ticket en el sistema.</p>
                        <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
                            <tr><td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Número:</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #eee;">' . htmlspecialchars($ticket_number) . '</td></tr>
                            <tr><td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Asunto:</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #eee;">' . htmlspecialchars($subject) . '</td></tr>
                            <tr><td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Cliente:</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #eee;">' . htmlspecialchars($clientName) . ' &lt;' . htmlspecialchars($clientEmail) . '&gt;</td></tr>
                            <tr><td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Departamento:</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #eee;">' . htmlspecialchars($deptName) . '</td></tr>
                            <tr><td style="padding: 8px 0;"><strong>Mensaje:</strong></td><td style="padding: 8px 0;"></td></tr>
                        </table>
                        <div style="background: #f5f5f5; padding: 12px; border-radius: 6px; margin: 12px 0;">' . nl2br(htmlspecialchars($bodyEmailText)) . '</div>
                        <p><a href="' . htmlspecialchars($viewUrl) . '" style="display: inline-block; background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Ver ticket</a></p>
                        <p style="color: #7f8c8d; font-size: 12px;">' . htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets') . '</p>
                    </div>';
                $emailSubject = '[Nuevo ticket] ' . $ticket_number . ' - ' . $subject;
                $mailSent = 0;
                $mailError = '';
                if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                    if (Mailer::send($adminEmail, $emailSubject, $bodyHtml)) {
                        $mailSent = 1;
                    } else {
                        $mailError = Mailer::$lastError;
                    }
                }
                $success = 'Ticket creado exitosamente! Número: ' . $ticket_number;
                echo '<script>
                    setTimeout(function() {
                        window.location.href = "tickets.php";
                    }, 2000);
                </script>';
            } else {
                $error = 'Error al crear el ticket: ' . $mysqli->error;
            }
        }
    }
}

}

// Obtener departamentos y temas
$departments = [];
$stmt = $mysqli->query('SELECT id, name FROM departments WHERE is_active = 1');
while ($row = $stmt->fetch_assoc()) {
    $departments[] = $row;
}

// Verificar si hay temas disponibles
$topics = [];
$hasTopics = false;
$checkTopics = $mysqli->query("SHOW TABLES LIKE 'help_topics'");
if ($checkTopics && $checkTopics->num_rows > 0) {
    $checkCol = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'topic_id'");
    if ($checkCol && $checkCol->num_rows > 0) {
        $hasTopics = true;
        $stmt = $mysqli->query('SELECT ht.id, ht.name, ht.dept_id FROM help_topics ht WHERE ht.is_active = 1 ORDER BY ht.name');
        while ($row = $stmt->fetch_assoc()) {
            $topics[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Ticket - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
    <style>
        body {
            background: #f6f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 62px;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(700px circle at 12% 0%, rgba(245, 158, 11, 0.08), transparent 52%),
                radial-gradient(900px circle at 88% 10%, rgba(99, 102, 241, 0.10), transparent 55%),
                repeating-linear-gradient(135deg, rgba(15, 23, 42, 0.02) 0px, rgba(15, 23, 42, 0.02) 1px, transparent 1px, transparent 14px);
            z-index: -1;
        }

        .topbar {
            background: linear-gradient(135deg, #0b1220, #111827);
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }
        .topbar.navbar {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }
        .topbar .container-fluid {
            padding-top: 2px;
            padding-bottom: 2px;
        }
        .topbar .navbar-brand { font-weight: 900; letter-spacing: 0.02em; }
        .topbar .profile-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            text-decoration: none;
        }
        .topbar .profile-brand .brand-logo-wrap {
            height: 46px;
            padding: 0;
            border-radius: 0;
            background: transparent;
            border: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: none;
        }
        .topbar .profile-brand .brand-logo {
            height: 30px;
            width: auto;
            max-width: 320px;
            object-fit: contain;
            display: block;
        }
        @media (max-width: 420px) {
            .topbar .profile-brand .brand-logo { max-width: 200px; }
        }
        .topbar .user-menu-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border-radius: 999px;
            font-weight: 800;
        }
        .topbar .user-menu-btn .uavatar {
            width: 30px;
            height: 30px;
            border-radius: 12px;
            background: rgba(255,255,255,0.92);
            color: #0b1220;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 1000;
            letter-spacing: 0.08em;
            flex: 0 0 auto;
        }
        .topbar .profile-brand .avatar {
            width: 36px;
            height: 36px;
            border-radius: 14px;
            background: rgba(255,255,255,0.92);
            color: #0b1220;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 1000;
            letter-spacing: 0.08em;
            flex: 0 0 auto;
        }
        .topbar .profile-brand .name {
            font-weight: 900;
            font-size: 0.98rem;
            line-height: 1.1;
        }
        .topbar .btn { border-radius: 999px; font-weight: 700; }
        .container-main {
            max-width: 980px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .shell {
            max-width: 980px;
            margin: 0 auto;
        }

        .panel-soft {
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(10px);
            border-radius: 22px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .panel-soft {
            background-image:
                radial-gradient(900px circle at 0% 0%, rgba(37, 99, 235, 0.06), transparent 52%),
                radial-gradient(700px circle at 100% 0%, rgba(245, 158, 11, 0.06), transparent 55%);
        }

        @media (max-width: 992px) {
            .shell { max-width: 100%; }
        }
        .page-header {
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            padding: 22px 22px;
            border-radius: 16px;
            margin-bottom: 18px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            color: #0f172a;
            border: 1px solid #e2e8f0;
            border-left: 6px solid #2563eb;
        }
        .page-header .sub { color: #64748b; font-weight: 700; }

        .form-card {
            background: #fff;
            padding: 28px;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
        }

        .form-card {
            transition: box-shadow .15s ease, border-color .15s ease;
        }
        .form-card:hover {
            box-shadow: 0 14px 36px rgba(15, 23, 42, 0.10);
            border-color: #cbd5e1;
        }

        .section-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .section-title h4 { margin: 0; font-weight: 800; color: #0f172a; }
        .help { color: #64748b; font-size: 0.95rem; }

        .attach-zone {
            border: 2px dashed #cbd5e1;
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            cursor: pointer;
            margin-bottom: 14px;
        }
        .attach-zone:hover { border-color: #94a3b8; }
        .attach-zone input[type="file"] { display: none; }
        .attach-text { color: #64748b; font-size: 0.95rem; }
        .attach-list { margin-top: 10px; display: flex; flex-direction: column; gap: 6px; }
        .attach-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px 10px;
            color: #0f172a;
        }
        .attach-item .name { font-weight: 600; font-size: 0.9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .attach-item .size { color: #64748b; font-size: 0.85rem; flex: 0 0 auto; }

        .note-editor .note-editable img { max-width: 420px !important; max-height: 260px !important; width: auto !important; height: auto !important; display: block; object-fit: contain; }
        .note-editor .note-editable iframe { max-width: 520px !important; width: 100% !important; aspect-ratio: 16 / 9; height: auto !important; display: block; }

        #open-loading-overlay{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.25);z-index:2000}
        #open-loading-overlay .box{background:#fff;border-radius:14px;padding:18px 22px;min-width:320px;max-width:92vw;box-shadow:0 10px 30px rgba(0,0,0,.25)}
    </style>
</head>
<body>
    <?php
        $navUserName = trim((string)($user['name'] ?? ''));
        $companyName = trim((string)getAppSetting('company.name', ''));
        $companyLogoUrl = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png');
        $navInitials = '';
        $parts = preg_split('/\s+/', trim($navUserName));
        if (!empty($parts[0])) $navInitials .= (function_exists('mb_substr') ? mb_substr($parts[0], 0, 1) : substr($parts[0], 0, 1));
        if (!empty($parts[1])) $navInitials .= (function_exists('mb_substr') ? mb_substr($parts[1], 0, 1) : substr($parts[1], 0, 1));
        $navInitials = strtoupper($navInitials ?: 'U');
        if ($navUserName === '') $navUserName = 'Mi Perfil';
    ?>
    <nav class="navbar navbar-dark topbar" style="position: fixed; top: 0; left: 0; width: 100%; z-index: 1030;">
        <div class="container-fluid">
            <a class="navbar-brand profile-brand" href="tickets.php">
                <span class="brand-logo-wrap" aria-hidden="true">
                    <img class="brand-logo" src="<?php echo html($companyLogoUrl); ?>" alt="<?php echo html($companyName !== '' ? $companyName : 'Logo'); ?>">
                </span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle user-menu-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="uavatar" aria-hidden="true"><?php echo html($navInitials); ?></span>
                        <span class="d-none d-sm-inline"><?php echo html($navUserName); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="tickets.php"><i class="bi bi-inboxes"></i> Mis Tickets</a></li>
                        <li><a class="dropdown-item" href="open.php"><i class="bi bi-plus-circle"></i> Crear Ticket</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Mi perfil</a></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-main">
        <div id="open-loading-overlay" role="status" aria-live="polite" aria-busy="true">
            <div class="box">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                    <div>
                        <div class="fw-semibold">Creando ticket…</div>
                        <div class="text-muted small">Por favor espera</div>
                    </div>
                </div>
                <div class="progress" style="height:8px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div>
                </div>
            </div>
        </div>

        <div class="shell">
            <main class="panel-soft" style="padding: 18px;">
                <div class="page-header" style="margin-top: 0;">
                    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                        <div>
                            <h2 class="mb-1">Abrir un nuevo Ticket</h2>
                            <div class="sub">Completa el formulario para crear una nueva solicitud.</div>
                        </div>
                        <div>
                            <a href="tickets.php" class="btn btn-light btn-sm" style="border-radius: 999px; font-weight: 800;"><i class="bi bi-arrow-left"></i> Volver</a>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="section-title">
                        <h4><i class="bi bi-chat-left-text"></i> Ticket Details</h4>
                        <div class="help">Completa los datos para crear una nueva solicitud.</div>
                    </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form id="open-ticket-form" method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="subject" class="form-label">Asunto</label>
                    <input type="text" class="form-control" id="subject" name="subject" required>
                </div>

                <?php if ($hasTopics): ?>
                <div class="mb-3">
                    <label for="topic_id" class="form-label">Tema</label>
                    <select class="form-select" id="topic_id" name="topic_id" onchange="updateDepartmentFromTopic()" required>
                        <option value="">Seleccionar tema...</option>
                        <?php foreach ($topics as $topic): ?>
                            <option value="<?php echo $topic['id']; ?>" data-dept="<?php echo $topic['dept_id']; ?>"><?php echo html($topic['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <div class="mb-3">
                    <label for="dept_id" class="form-label">Departamento</label>
                    <select class="form-select" id="dept_id" name="dept_id" required>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo html($dept['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="body" class="form-label">Descripción</label>
                    <textarea class="form-control" id="body" name="body" rows="8"></textarea>
                </div>

                <div class="attach-zone" id="attach-zone">
                    <input type="file" name="attachments[]" id="attachments" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt">
                    <div class="attach-text"><i class="bi bi-paperclip"></i> Agregar archivos aquí o <a href="#" id="attach-choose-link">elegirlos</a></div>
                    <div class="attach-list" id="attach-list"></div>
                </div>

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <button id="open-ticket-submit" type="submit" class="btn btn-primary">Crear Ticket</button>
                <a href="tickets.php" class="btn btn-secondary">Cancelar</a>
            </form>
                </div>
            </main>
        </div>

        <script>
            function updateDepartmentFromTopic() {
                var topicSelect = document.getElementById('topic_id');
                var deptSelect = document.getElementById('dept_id');
                if (!topicSelect || !deptSelect) return;
                
                var selectedOption = topicSelect.options[topicSelect.selectedIndex];
                if (selectedOption && selectedOption.getAttribute('data-dept')) {
                    var deptId = selectedOption.getAttribute('data-dept');
                    for (var i = 0; i < deptSelect.options.length; i++) {
                        if (deptSelect.options[i].value == deptId) {
                            deptSelect.selectedIndex = i;
                            break;
                        }
                    }
                }
            }
            
        (function () {
            var zone = document.getElementById('attach-zone');
            var input = document.getElementById('attachments');
            var list = document.getElementById('attach-list');
            var chooseLink = document.getElementById('attach-choose-link');
            if (!zone || !input || !list) return;

            var openPicker = function () {
                try { input.click(); } catch (e) {}
            };

            zone.addEventListener('click', function (e) {
                // Evitar que el click en botones internos (Quitar) dispare el picker
                if (e.target && (e.target.closest && e.target.closest('button[data-remove-index]'))) return;
                openPicker();
            });
            chooseLink && chooseLink.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                openPicker();
            });

            function humanSize(bytes) {
                if (!bytes) return '0 B';
                var units = ['B', 'KB', 'MB', 'GB'];
                var i = Math.floor(Math.log(bytes) / Math.log(1024));
                i = Math.min(i, units.length - 1);
                return (bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
            }

            function removeAt(index) {
                try {
                    var dt = new DataTransfer();
                    for (var i = 0; i < input.files.length; i++) {
                        if (i === index) continue;
                        dt.items.add(input.files[i]);
                    }
                    input.files = dt.files;
                } catch (e) {
                    // Fallback: si el navegador no permite manipular FileList
                    input.value = '';
                }
                updateList();
            }

            function updateList() {
                list.innerHTML = '';
                if (!input.files || input.files.length === 0) return;
                for (var i = 0; i < input.files.length; i++) {
                    var f = input.files[i];
                    var row = document.createElement('div');
                    row.className = 'attach-item';

                    var left = document.createElement('div');
                    left.className = 'name';
                    left.textContent = f.name;

                    var right = document.createElement('div');
                    right.style.display = 'flex';
                    right.style.alignItems = 'center';
                    right.style.gap = '8px';

                    var size = document.createElement('div');
                    size.className = 'size';
                    size.textContent = humanSize(f.size);

                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn btn-sm btn-outline-danger';
                    btn.textContent = 'Quitar';
                    btn.setAttribute('data-remove-index', String(i));
                    btn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        var idx = parseInt(this.getAttribute('data-remove-index'), 10);
                        if (!isNaN(idx)) removeAt(idx);
                    });

                    right.appendChild(size);
                    right.appendChild(btn);
                    row.appendChild(left);
                    row.appendChild(right);
                    list.appendChild(row);
                }
            }

            input.addEventListener('change', updateList);
        })();

        (function () {
            var bound = false;

            function getEls() {
                var overlay = document.getElementById('creativePop');
                var msgEl = document.getElementById('creativePopMsg');
                var titleEl = document.getElementById('creativePopTitle');
                return { overlay: overlay, msgEl: msgEl, titleEl: titleEl };
            }

            function ensureBound() {
                if (bound) return;
                var els = getEls();
                if (!els.overlay) return;
                bound = true;
                els.overlay.addEventListener('click', function (e) {
                    if (e.target === els.overlay) window.__hideCreativePop && window.__hideCreativePop();
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') window.__hideCreativePop && window.__hideCreativePop();
                });
            }

            window.__showCreativePop = function (msg, title) {
                var els = getEls();
                if (!els.overlay || !els.msgEl) {
                    alert(msg || 'Atención');
                    return;
                }
                els.msgEl.textContent = msg || '';
                if (els.titleEl) els.titleEl.textContent = title || 'Atención';
                try { els.overlay.style.display = 'none'; } catch (e0) {}
                els.overlay.style.display = 'flex';
                els.overlay.setAttribute('aria-hidden', 'false');
                ensureBound();
            };
            window.__hideCreativePop = function () {
                var els = getEls();
                if (!els.overlay) return;
                els.overlay.style.display = 'none';
                els.overlay.setAttribute('aria-hidden', 'true');
            };

            var form = document.querySelector('form[enctype="multipart/form-data"]');
            if (!form) return;
            var fileInput = document.getElementById('attachments');
            var topicSelect = document.getElementById('topic_id');
            var editor = document.getElementById('body');

            var focusEditor = function () {
                try {
                    if (typeof jQuery !== 'undefined' && jQuery(editor).summernote) {
                        jQuery(editor).summernote('focus');
                        return;
                    }
                } catch (e) {}
                try { editor && editor.focus(); } catch (e2) {}
            };

            var getPlainTextFromHtml = function (html) {
                var tmp = document.createElement('div');
                tmp.innerHTML = html || '';
                return (tmp.textContent || tmp.innerText || '').replace(/\u00A0/g, ' ').trim();
            };

            var validateAttachmentsNeedText = function (ev) {
                try {
                    if (topicSelect && String(topicSelect.value || '').trim() === '') {
                        if (ev && ev.preventDefault) ev.preventDefault();
                        if (ev && ev.stopPropagation) ev.stopPropagation();
                        if (ev && ev.stopImmediatePropagation) ev.stopImmediatePropagation();
                        if (window.__showCreativePop) {
                            window.__showCreativePop('Debes seleccionar un tema para poder crear el ticket.', 'Tema requerido');
                        } else {
                            alert('Debes seleccionar un tema para poder crear el ticket.');
                        }
                        try { topicSelect.focus(); } catch (e0) {}
                        return false;
                    }

                    var hasFiles = fileInput && fileInput.files && fileInput.files.length > 0;

                    var html = '';
                    try {
                        if (typeof jQuery !== 'undefined' && jQuery(editor).summernote) {
                            html = jQuery(editor).summernote('code') || '';
                            if (jQuery(editor).summernote('isEmpty')) html = '';
                        }
                    } catch (e) {}
                    if (!html) html = (editor && editor.value) ? editor.value : '';

                    var plain = getPlainTextFromHtml(html);
                    var hasMedia = html.indexOf('<img') !== -1 || html.indexOf('<iframe') !== -1;
                    if (!hasMedia && plain === '') {
                        if (ev && ev.preventDefault) ev.preventDefault();
                        if (ev && ev.stopPropagation) ev.stopPropagation();
                        if (ev && ev.stopImmediatePropagation) ev.stopImmediatePropagation();
                        if (window.__showCreativePop) {
                            window.__showCreativePop(hasFiles
                                ? 'Adjuntaste un archivo, pero la descripción está vacía. Escribe una breve descripción para poder enviarlo.'
                                : 'La descripción es obligatoria. Escribe un mensaje para poder crear el ticket.',
                                'Falta una descripción'
                            );
                            try {
                                var o = document.getElementById('creativePop');
                                if (!o || o.style.display !== 'flex') {
                                    alert(hasFiles
                                        ? 'Adjuntaste un archivo, pero la descripción está vacía. Escribe una breve descripción para poder enviarlo.'
                                        : 'La descripción es obligatoria. Escribe un mensaje para poder crear el ticket.'
                                    );
                                }
                            } catch (e3) {}
                        } else {
                            alert(hasFiles
                                ? 'Adjuntaste un archivo, pero la descripción está vacía. Escribe una breve descripción para poder enviarlo.'
                                : 'La descripción es obligatoria. Escribe un mensaje para poder crear el ticket.'
                            );
                        }
                        setTimeout(focusEditor, 50);
                        return false;
                    }
                } catch (e2) {}
                return true;
            };

            form.addEventListener('submit', function (ev) {
                var ok = validateAttachmentsNeedText(ev);
                if (!ok) {
                    if (ev && ev.preventDefault) ev.preventDefault();
                    if (ev && ev.stopPropagation) ev.stopPropagation();
                    if (ev && ev.stopImmediatePropagation) ev.stopImmediatePropagation();
                    return false;
                }
            }, true);

            var submitBtn = document.getElementById('open-ticket-submit');
            if (submitBtn) {
                submitBtn.addEventListener('click', function (ev) {
                    var ok = validateAttachmentsNeedText(ev);
                    if (!ok) {
                        if (ev && ev.preventDefault) ev.preventDefault();
                        if (ev && ev.stopPropagation) ev.stopPropagation();
                        if (ev && ev.stopImmediatePropagation) ev.stopImmediatePropagation();
                        return false;
                    }
                }, true);
            }
        })();

        (function(){
            function showLoading(){
                var overlay = document.getElementById('open-loading-overlay');
                if (overlay) overlay.style.display = 'flex';
                var btn = document.getElementById('open-ticket-submit');
                if (btn) btn.disabled = true;
            }

            try {
                var form = document.getElementById('open-ticket-form');
                if (!form) return;

                form.addEventListener('submit', function(ev){
                    if (ev.defaultPrevented) return;
                    showLoading();
                });
            } catch (e) {}
        })();
    </script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-es-ES.min.js"></script>

    <div class="modal fade" id="videoInsertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Insertar video</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="videoInsertUrl" class="form-label">URL (YouTube o Vimeo)</label>
                    <input type="url" class="form-control" id="videoInsertUrl" placeholder="https://www.youtube.com/watch?v=...">
                    <div class="form-text">Pega un enlace de YouTube/Vimeo y se insertará en la descripción.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="videoInsertConfirm">Insertar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof jQuery === 'undefined' || !jQuery().summernote) return;

            var videoModalEl = document.getElementById('videoInsertModal');
            var videoUrlEl = document.getElementById('videoInsertUrl');
            var videoConfirmEl = document.getElementById('videoInsertConfirm');
            var videoModal = null;
            var onVideoSubmit = null;
            if (videoModalEl && window.bootstrap && bootstrap.Modal) {
                videoModal = new bootstrap.Modal(videoModalEl);
            }

            function openVideoModal(cb) {
                onVideoSubmit = cb;
                if (!videoModal || !videoUrlEl) return;
                videoUrlEl.value = '';
                videoModal.show();
                setTimeout(function () { try { videoUrlEl.focus(); } catch (e) {} }, 100);
            }

            if (videoConfirmEl) {
                videoConfirmEl.addEventListener('click', function () {
                    if (!onVideoSubmit || !videoUrlEl) return;
                    var v = (videoUrlEl.value || '').trim();
                    if (v === '') return;
                    try { videoModal && videoModal.hide(); } catch (e) {}
                    try { onVideoSubmit(v); } catch (e2) {}
                });
            }
            if (videoUrlEl) {
                videoUrlEl.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        videoConfirmEl && videoConfirmEl.click();
                    }
                });
            }

            function toEmbedUrl(url) {
                url = (url || '').trim();
                if (!url) return '';
                if (url.indexOf('//') === 0) url = 'https:' + url;
                if (/^https?:\/\/(www\.)?(youtube\.com\/embed\/|youtube-nocookie\.com\/embed\/)/i.test(url)) return url;
                if (/^https?:\/\/(www\.)?player\.vimeo\.com\/video\//i.test(url)) return url;
                var m = url.match(/(?:youtube\.com\/watch\?v=|youtube\.com\/shorts\/|youtu\.be\/)([A-Za-z0-9_-]{6,})/i);
                if (m && m[1]) return 'https://www.youtube-nocookie.com/embed/' + m[1] + '?rel=0';
                var v = url.match(/vimeo\.com\/(?:video\/)?(\d+)/i);
                if (v && v[1]) return 'https://player.vimeo.com/video/' + v[1];
                return '';
            }

            var myVideoBtn = function (context) {
                var ui = jQuery.summernote.ui;
                return ui.button({
                    contents: '<i class="note-icon-video"></i>',
                    tooltip: 'Insertar video (YouTube/Vimeo)',
                    click: function () {
                        openVideoModal(function (url) {
                            var embed = toEmbedUrl(url);
                            if (!embed) {
                                window.__showCreativePop && window.__showCreativePop('Formato de enlace no soportado. Usa un enlace de YouTube o Vimeo.', 'Video no soportado');
                                return;
                            }
                            var html = '<iframe src="' + embed.replace(/"/g, '') + '" width="560" height="315" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>';
                            context.invoke('editor.pasteHTML', html);
                        });
                    }
                }).render();
            };

            jQuery('#body').summernote({
                height: 220,
                lang: 'es-ES',
                placeholder: 'Describe tu solicitud…',
                toolbar: [
                    ['style', ['bold', 'italic', 'underline']],
                    ['para', ['ul', 'ol']],
                    ['insert', ['link', 'myVideo'] ],
                    ['view', ['codeview']]
                ],
                buttons: {
                    myVideo: myVideoBtn
                }
            });
        });
    </script>

    <style>
        .creative-pop-overlay{position:fixed; inset:0; background:rgba(15,23,42,.46); display:none; align-items:center; justify-content:center; padding:18px; z-index:2000; backdrop-filter: blur(10px);}
        .creative-pop{max-width:560px; width:100%; background:rgba(255,255,255,0.88); border:1px solid rgba(226,232,240,0.92); border-radius:22px; box-shadow:0 30px 90px rgba(15,23,42,.30); overflow:hidden; backdrop-filter: blur(10px); animation: creativePopIn .14s ease-out;}
        .creative-pop-head{display:flex; align-items:center; gap:12px; padding:14px 16px; background:linear-gradient(135deg,#0b1220,#111827); color:#fff; border-bottom:1px solid rgba(255,255,255,0.12);}
        .creative-pop-icon{width:40px; height:40px; border-radius:14px; background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,0.16); display:flex; align-items:center; justify-content:center; flex:0 0 auto;}
        .creative-pop-title{font-weight:1000; margin:0; font-size:15px; letter-spacing:.02em;}
        .creative-pop-body{padding:16px 16px; color:#0f172a; font-weight:650; line-height:1.45;}
        .creative-pop-actions{display:flex; gap:10px; justify-content:flex-end; padding:0 16px 16px;}
        .creative-pop-btn{border:1px solid transparent; border-radius:999px; padding:10px 14px; font-weight:900; cursor:pointer;}
        .creative-pop-btn.primary{background:#111827; color:#fff; border-color:rgba(255,255,255,0.12);}
        .creative-pop-btn.primary:hover{background:#0b1220;}
        .creative-pop-btn.ghost{background:#f1f5f9; color:#0f172a; border-color:#e2e8f0;}
        .creative-pop-btn.ghost:hover{background:#e2e8f0;}

        @keyframes creativePopIn{from{transform:translateY(6px) scale(.985); opacity:.65;}to{transform:translateY(0) scale(1); opacity:1;}}
    </style>
    <div class="creative-pop-overlay" id="creativePop" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="creative-pop">
            <div class="creative-pop-head">
                <div class="creative-pop-icon"><i class="bi bi-info-circle"></i></div>
                <div>
                    <div class="creative-pop-title" id="creativePopTitle">Atención</div>
                    <div style="opacity:.9; font-weight:700; font-size:12px;">Antes de enviar</div>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto" aria-label="Cerrar" onclick="window.__hideCreativePop && window.__hideCreativePop()"></button>
            </div>
            <div class="creative-pop-body" id="creativePopMsg"></div>
            <div class="creative-pop-actions">
                <button type="button" class="creative-pop-btn ghost" onclick="window.__hideCreativePop && window.__hideCreativePop()">Entendido</button>
                <button type="button" class="creative-pop-btn primary" onclick="window.__hideCreativePop && window.__hideCreativePop(); try{ if(window.jQuery && jQuery('#body').summernote){ jQuery('#body').summernote('focus'); } else { var el=document.getElementById('body'); el && el.focus(); } }catch(e){}">Escribir</button>
            </div>
        </div>
    </div>
    <footer style="text-align: center; padding: 20px 0; background-color: #f8f9fa; border-top: 1px solid #dee2e6; margin-top: 40px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">
            Derechos de autor &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getAppSetting('company.name', 'Vigitec Panama')); ?> - Sistema de Tickets - Todos los derechos reservados.
        </p>
    </footer>
</body>
</html>
