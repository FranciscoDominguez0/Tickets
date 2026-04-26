<?php
/**
 * CREAR TICKET
 * Formulario para que usuarios creen nuevos tickets
 */

require_once '../config.php';
require_once '../includes/helpers.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Validar que sea cliente
requireLogin('cliente');

$user = getCurrentUser();
$error = '';
$errorFields = [];
$success = '';

$eid = (int)($_SESSION['empresa_id'] ?? 0);
if ($eid <= 0) $eid = 1;

if (!function_exists('normalizeTopicMatchValue')) {
    function normalizeTopicMatchValue($value) {
        $value = trim((string)$value);
        if ($value === '') return '';
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }
        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }
        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', (string)$value);
        return trim((string)$value);
    }
}

if (!function_exists('isRedesInformaticaTopic')) {
    function isRedesInformaticaTopic($topicName) {
        $normalized = normalizeTopicMatchValue($topicName);
        return $normalized !== '' && strpos($normalized, 'redes') !== false;
    }
}

$anydesk = trim((string)($_POST['anydesk'] ?? ''));
$requiresNetworkFields = false;
$userPhone = trim((string)($user['phone'] ?? ''));
if ($userPhone === '' && (int)($_SESSION['user_id'] ?? 0) > 0) {
    $stmtPhone = $mysqli->prepare('SELECT phone FROM users WHERE id = ? LIMIT 1');
    if ($stmtPhone) {
        $uidPhone = (int)$_SESSION['user_id'];
        $stmtPhone->bind_param('i', $uidPhone);
        if ($stmtPhone->execute()) {
            $phoneRow = $stmtPhone->get_result()->fetch_assoc();
            $userPhone = trim((string)($phoneRow['phone'] ?? ''));
        }
    }
}
$hasUserPhone = ($userPhone !== '');

if ($_POST) {
    if (!validateCSRF()) {
        $error = 'Token de seguridad inválido';
    } else {
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $topic_id = intval($_POST['topic_id'] ?? 0);
        $dept_id = intval($_POST['dept_id'] ?? 0);
        $anydesk = trim((string)($_POST['anydesk'] ?? ''));
        $hasFiles = !empty($_FILES['attachments']['name'][0]);
        $plain = trim(str_replace("\xC2\xA0", ' ', html_entity_decode(strip_tags($body), ENT_QUOTES, 'UTF-8')));

        $ticketMaxFileMb = (int)getAppSetting('tickets.ticket_max_file_mb', '10');
        if ($ticketMaxFileMb < 1) $ticketMaxFileMb = 1;
        if ($ticketMaxFileMb > 256) $ticketMaxFileMb = 256;
        $ticketMaxUploads = (int)getAppSetting('tickets.ticket_max_uploads', '5');
        if ($ticketMaxUploads < 0) $ticketMaxUploads = 0;
        if ($ticketMaxUploads > 20) $ticketMaxUploads = 20;
        $maxSize = $ticketMaxFileMb * 1024 * 1024;

        if ($error === '' && !empty($_FILES['attachments']) && isset($_FILES['attachments']['name'])) {
            $files = $_FILES['attachments'];
            if (!is_array($files['name'])) {
                $files = ['name' => [$files['name']], 'type' => [$files['type']], 'tmp_name' => [$files['tmp_name']], 'error' => [$files['error']], 'size' => [$files['size']]];
            }

            $validCount = 0;
            $n = is_array($files['name']) ? count($files['name']) : 0;
            for ($i = 0; $i < $n; $i++) {
                $err = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                if ($err === UPLOAD_ERR_NO_FILE) continue;
                if ($err !== UPLOAD_ERR_OK) {
                    $error = 'No se pudo subir uno de los adjuntos.';
                    break;
                }
                $orig = trim((string)($files['name'][$i] ?? ''));
                $size = (int)($files['size'][$i] ?? 0);
                if ($orig === '' || $size <= 0) continue;
                $validCount++;
                if ($ticketMaxUploads === 0) {
                    $error = 'No se permiten adjuntos.';
                    break;
                }
                if ($validCount > $ticketMaxUploads) {
                    $error = 'Máximo de ' . (string)$ticketMaxUploads . ' adjunto(s) por mensaje.';
                    break;
                }
                if ($size > $maxSize) {
                    $error = 'El adjunto "' . html($orig) . '" supera el tamaño máximo permitido (' . (string)$ticketMaxFileMb . ' MB).';
                    break;
                }
            }
        }

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
            $stmtTopicDept = $mysqli->prepare('SELECT dept_id FROM help_topics WHERE id = ? AND empresa_id = ? LIMIT 1');
            if ($stmtTopicDept) {
                $stmtTopicDept->bind_param('ii', $topic_id, $eid);
                if ($stmtTopicDept->execute()) {
                    $tr = $stmtTopicDept->get_result()->fetch_assoc();
                    $deptFromTopic = (int) ($tr['dept_id'] ?? 0);
                    if ($deptFromTopic > 0) {
                        $dept_id = $deptFromTopic;
                    }
                }
            }
        }

        if ($topic_id > 0) {
            $stmtTopicName = $mysqli->prepare('SELECT name FROM help_topics WHERE id = ? AND empresa_id = ? LIMIT 1');
            if ($stmtTopicName) {
                $stmtTopicName->bind_param('ii', $topic_id, $eid);
                if ($stmtTopicName->execute()) {
                    $topicRow = $stmtTopicName->get_result()->fetch_assoc();
                    $requiresNetworkFields = isRedesInformaticaTopic($topicRow['name'] ?? '');
                }
            }
        }

        // Fallback final
        if ($dept_id <= 0) {
            $fallbackDept = 0;
            $stmtFD = $mysqli->prepare('SELECT id FROM departments WHERE empresa_id = ? AND is_active = 1 ORDER BY id ASC LIMIT 1');
            if ($stmtFD) {
                $stmtFD->bind_param('i', $eid);
                if ($stmtFD->execute()) {
                    $fallbackDept = (int)($stmtFD->get_result()->fetch_assoc()['id'] ?? 0);
                }
            }
            $dept_id = $fallbackDept > 0 ? $fallbackDept : 1;
        }

        // Asignación automática por departamento (si está configurado)
        $generalDeptId = 0;
        $rgd = $mysqli->query("SELECT id FROM departments WHERE empresa_id = " . (int)$eid . " AND LOWER(name) LIKE '%general%' LIMIT 1");
        if ($rgd && ($row = $rgd->fetch_assoc())) {
            $generalDeptId = (int) ($row['id'] ?? 0);
        }

        $defaultStaffId = null;
        $hasDeptDefaultStaff = false;
        $chkCol = $mysqli->query("SHOW COLUMNS FROM departments LIKE 'default_staff_id'");
        if ($chkCol && $chkCol->num_rows > 0) $hasDeptDefaultStaff = true;
        if ($hasDeptDefaultStaff && $dept_id > 0) {
            $stmtDef = $mysqli->prepare('SELECT default_staff_id FROM departments WHERE id = ? AND empresa_id = ? AND is_active = 1 LIMIT 1');
            if ($stmtDef) {
                $stmtDef->bind_param('ii', $dept_id, $eid);
                if ($stmtDef->execute()) {
                    $v = (int)($stmtDef->get_result()->fetch_assoc()['default_staff_id'] ?? 0);
                    if ($v > 0) {
                        $allowed = false;
                        
                        // Check if staff_departments table exists
                        $hasStaffDepartmentsTableOpen = false;
                        if (isset($mysqli) && $mysqli) {
                            try {
                                $rt = $mysqli->query("SHOW TABLES LIKE 'staff_departments'");
                                $hasStaffDepartmentsTableOpen = ($rt && $rt->num_rows > 0);
                            } catch (Throwable $e) {
                                $hasStaffDepartmentsTableOpen = false;
                            }
                        }
                        
                        if ($hasStaffDepartmentsTableOpen) {
                            // New model: check staff_departments
                            $stmtSd = $mysqli->prepare('SELECT 1 FROM staff s JOIN staff_departments sd ON sd.staff_id = s.id WHERE s.id = ? AND s.empresa_id = ? AND s.is_active = 1 AND sd.dept_id = ? LIMIT 1');
                            if ($stmtSd) {
                                $stmtSd->bind_param('iii', $v, $eid, $dept_id);
                                if ($stmtSd->execute()) {
                                    $row = $stmtSd->get_result()->fetch_assoc();
                                    $allowed = ($row !== null);
                                }
                            }
                        } else {
                            // Legacy model
                            $stmtSd = $mysqli->prepare('SELECT COALESCE(NULLIF(dept_id, 0), ?) AS dept_id FROM staff WHERE id = ? AND empresa_id = ? AND is_active = 1 LIMIT 1');
                            if ($stmtSd) {
                                $stmtSd->bind_param('iii', $generalDeptId, $v, $eid);
                                if ($stmtSd->execute()) {
                                    $sdept = (int)($stmtSd->get_result()->fetch_assoc()['dept_id'] ?? 0);
                                    $allowed = ($sdept === $dept_id);
                                }
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
            $errorFields['topic_id'] = true;
        } elseif (!$hasUserPhone) {
            $error = 'Debes registrar tu teléfono en tu perfil antes de crear un ticket.';
        } elseif ($requiresNetworkFields && $anydesk === '') {
            $error = 'Para temas de Redes Informática debes completar Anydesk.';
            $errorFields['anydesk'] = true;
        } elseif (empty($subject) || $isBodyEmpty) {
            $error = 'Asunto y descripción son requeridos';
            if (empty($subject)) $errorFields['subject'] = true;
            if ($isBodyEmpty) $errorFields['body'] = true;
        } elseif ($hasFiles && $plain === '' && stripos($body, '<img') === false && stripos($body, '<iframe') === false) {
            $error = 'Debes escribir una descripción para enviar archivos. Si solo quieres adjuntar, escribe una breve descripción.';
            $errorFields['body'] = true;
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
                    $stmtCnt = $mysqli->prepare('SELECT COUNT(*) AS cnt FROM tickets WHERE user_id = ? AND empresa_id = ? AND closed IS NULL');
                } else {
                    $stmtCnt = $mysqli->prepare('SELECT COUNT(*) AS cnt FROM tickets WHERE user_id = ? AND empresa_id = ?');
                }
                if ($stmtCnt) {
                    $uid = (int)$_SESSION['user_id'];
                    $stmtCnt->bind_param('ii', $uid, $eid);
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
                    $stmtChk = $mysqli->prepare('SELECT id FROM tickets WHERE ticket_number = ? AND empresa_id = ? LIMIT 1');
                    if (!$stmtChk) return $num;
                    $stmtChk->bind_param('si', $num, $eid);
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

                // Intentar hasta 50 veces encontrar un número no duplicado
                for ($attempt = 0; $attempt < 50; $attempt++) {
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

                    $num = ($padding > 0) ? str_pad((string)$current, $padding, '0', STR_PAD_LEFT) : (string)$current;

                    // Verificar si ya existe un ticket con este número (evitar Duplicate Entry fatal)
                    $stmtChk = $mysqli->prepare('SELECT 1 FROM tickets WHERE ticket_number = ? LIMIT 1');
                    if ($stmtChk) {
                        $stmtChk->bind_param('s', $num);
                        $stmtChk->execute();
                        if (!$stmtChk->get_result()->fetch_assoc()) {
                            return $num;
                        }
                        // Si existe, el bucle continúa y vuelve a incrementar la secuencia
                    } else {
                        return $num;
                    }
                }

                return null;
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
                        'INSERT INTO tickets (ticket_number, empresa_id, user_id, staff_id, dept_id, topic_id, status_id, priority_id, subject, created) '
                        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->bind_param('siiiiiiis', $ticket_number, $eid, $uid, $staffVal, $dept_id, $topic_id, $defaultStatusId, $defaultPriorityId, $subject);
                } elseif ($hasStaffIdCol && $staffVal !== null) {
                    $stmt = $mysqli->prepare(
                        'INSERT INTO tickets (ticket_number, empresa_id, user_id, staff_id, dept_id, topic_id, status_id, subject, created) '
                        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->bind_param('siiiiiis', $ticket_number, $eid, $uid, $staffVal, $dept_id, $topic_id, $defaultStatusId, $subject);
                } elseif ($hasPriorityCol) {
                    $stmt = $mysqli->prepare(
                        'INSERT INTO tickets (ticket_number, empresa_id, user_id, dept_id, topic_id, status_id, priority_id, subject, created) '
                        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->bind_param('siiiiiis', $ticket_number, $eid, $uid, $dept_id, $topic_id, $defaultStatusId, $defaultPriorityId, $subject);
                } else {
                    $stmt = $mysqli->prepare(
                        'INSERT INTO tickets (ticket_number, empresa_id, user_id, dept_id, topic_id, status_id, subject, created) '
                        . 'VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->bind_param('siiiiis', $ticket_number, $eid, $uid, $dept_id, $topic_id, $defaultStatusId, $subject);
                }
            } else {
                if ($hasStaffIdCol && $staffVal !== null && $hasPriorityCol) {
                    $stmt = $mysqli->prepare(
                        'INSERT INTO tickets (ticket_number, empresa_id, user_id, staff_id, dept_id, status_id, priority_id, subject, created) '
                        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->bind_param('siiiiiiis', $ticket_number, $eid, $uid, $staffVal, $dept_id, $defaultStatusId, $defaultPriorityId, $subject);
                } elseif ($hasStaffIdCol && $staffVal !== null) {
                    $stmt = $mysqli->prepare(
                        'INSERT INTO tickets (ticket_number, empresa_id, user_id, staff_id, dept_id, status_id, subject, created) '
                        . 'VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->bind_param('siiiiis', $ticket_number, $eid, $uid, $staffVal, $dept_id, $defaultStatusId, $subject);
                } elseif ($hasPriorityCol) {
                    $stmt = $mysqli->prepare(
                        'INSERT INTO tickets (ticket_number, empresa_id, user_id, dept_id, status_id, priority_id, subject, created) '
                        . 'VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->bind_param('siiiiiis', $ticket_number, $eid, $uid, $dept_id, $defaultStatusId, $defaultPriorityId, $subject);
                } else {
                    $stmt = $mysqli->prepare(
                        'INSERT INTO tickets (ticket_number, empresa_id, user_id, dept_id, status_id, subject, created) '
                        . 'VALUES (?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->bind_param('siiiis', $ticket_number, $eid, $uid, $dept_id, $defaultStatusId, $subject);
                }
            }
            
            if ($stmt->execute()) {
                $ticket_id = $mysqli->insert_id;
                // Notificación interna si el estado inicial es relevante
                if ($defaultStatusId === 2 || $defaultStatusId === 3) {
                    $statusName = ($defaultStatusId === 2) ? 'En Camino' : 'En Proceso';
                    notifyStatusChangeToAdminRecipients($ticket_id, $statusName);
                }

                $hasAnydeskCol = false;
                $colAnydesk = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'anydesk'");
                if ($colAnydesk && $colAnydesk->num_rows > 0) $hasAnydeskCol = true;

                if (!$requiresNetworkFields) {
                    $anydesk = '';
                }

                if ($hasAnydeskCol) {
                    $stmtExtra = $mysqli->prepare('UPDATE tickets SET anydesk = ? WHERE id = ? LIMIT 1');
                    if ($stmtExtra) {
                        $stmtExtra->bind_param('si', $anydesk, $ticket_id);
                        $stmtExtra->execute();
                    }
                }
                
                // Crear thread y primer mensaje
                $stmt2 = $mysqli->prepare('INSERT INTO threads (empresa_id, ticket_id, created) VALUES (?, ?, NOW())');
                $stmt2->bind_param('ii', $eid, $ticket_id);
                $stmt2->execute();
                $thread_id = $mysqli->insert_id;
                
                $stmt3 = $mysqli->prepare(
                    'INSERT INTO thread_entries (empresa_id, thread_id, user_id, body, created)
                     VALUES (?, ?, ?, ?, NOW())'
                );
                $uidEntry = (int)($_SESSION['user_id'] ?? 0);
                $stmt3->bind_param('iiis', $eid, $thread_id, $uidEntry, $body);
                $stmt3->execute();
                $entry_id = (int) $mysqli->insert_id;

                // Notificación al agente asignado por departamento por defecto (si existe)
                if ($defaultStaffId !== null && (int)$defaultStaffId > 0) {
                    $val = (int) $defaultStaffId;
                    addLog('ticket_assigned_by_dept_default_user', 'Asignación automática por departamento (desde usuario)', 'ticket', $ticket_id, 'staff', $val);

                    // Notificación en BD solo al asignado
                    $message = 'Se te asignó el ticket ' . (string)$ticket_number . ': ' . (string)$subject;
                    $type = 'ticket_assigned';
                    $stmtN = $mysqli->prepare('INSERT INTO notifications (staff_id, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
                    if ($stmtN) {
                        $stmtN->bind_param('issi', $val, $message, $type, $ticket_id);
                        $stmtN->execute();
                    } else {
                        addLog('ticket_assign_notification_failed', 'No se pudo preparar INSERT notifications (user)', 'ticket', $ticket_id, 'staff', $val);
                    }
                }
                
                // Los correos de notificación de nuevo ticket se envían SOLO a los destinatarios
                // configurados en emailsettings.php (notification_recipients) - ver bloque posterior

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
                if (!empty($_FILES['attachments']['name'][0])) {
                    $attachmentsHasEmpresa = false;
                    $colA = $mysqli->query("SHOW COLUMNS FROM attachments LIKE 'empresa_id'");
                    $attachmentsHasEmpresa = ($colA && $colA->num_rows > 0);

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
                                if (is_string($detected) && $detected !== '') $mime = $detected;
                            }
                        }

                        $safeName = bin2hex(random_bytes(8)) . '_' . time() . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
                        $uploadDir = defined('ATTACHMENTS_DIR') ? ATTACHMENTS_DIR : __DIR__ . '/uploads/attachments';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0755, true);
                        }
                        $path = $uploadDir . '/' . $safeName;
                        if (move_uploaded_file($files['tmp_name'][$i], $path)) {
                            $relPath = 'uploads/attachments/' . $safeName;
                            $hash = @hash_file('sha256', $path) ?: '';
                            if ($attachmentsHasEmpresa) {
                                $stmtA = $mysqli->prepare("INSERT INTO attachments (empresa_id, thread_entry_id, filename, original_filename, mimetype, size, path, hash, created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                                $stmtA->bind_param('iisssiss', $eid, $entry_id, $safeName, $orig, $mime, $size, $relPath, $hash);
                            } else {
                                $stmtA = $mysqli->prepare("INSERT INTO attachments (thread_entry_id, filename, original_filename, mimetype, size, path, hash, created) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                                $stmtA->bind_param('isssiss', $entry_id, $safeName, $orig, $mime, $size, $relPath, $hash);
                            }
                            $stmtA->execute();
                        }
                    }
                }

                // Notificar por correo a destinatarios configurados (staff), en cola
                $adminNotifyEnabled = ((string)getAppSetting('mail.admin_notify_enabled', '1') === '1');
                $clientName = trim(($user['name'] ?? '') ?: 'Cliente');
                $clientEmail = $user['email'] ?? '';
                $deptName = 'Soporte';
                $stmtDept = $mysqli->prepare('SELECT name FROM departments WHERE id = ? AND empresa_id = ?');
                $stmtDept->bind_param('ii', $dept_id, $eid);
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
                $recipientCount = 0;
                if ($adminNotifyEnabled) {
                    $recipientEmails = [];
                    if (ensureNotificationRecipientsTable()) {
                        $staffHasEmpresa = false;
                        try {
                            $staffHasEmpresa = dbColumnExists('staff', 'empresa_id');
                        } catch (Throwable $e) {
                            $staffHasEmpresa = false;
                        }

                        $sqlRcpt = "SELECT s.email\n"
                            . "FROM notification_recipients nr\n"
                            . "INNER JOIN staff s ON s.id = nr.staff_id\n"
                            . "WHERE nr.empresa_id = ? AND s.is_active = 1";
                        if ($staffHasEmpresa) {
                            $sqlRcpt .= ' AND s.empresa_id = ?';
                        }
                        $sqlRcpt .= ' ORDER BY s.id ASC';
                        $stmtRcpt = $mysqli->prepare($sqlRcpt);
                        if ($stmtRcpt) {
                            if ($staffHasEmpresa) {
                                $stmtRcpt->bind_param('ii', $eid, $eid);
                            } else {
                                $stmtRcpt->bind_param('i', $eid);
                            }
                            if ($stmtRcpt->execute()) {
                                $rsRcpt = $stmtRcpt->get_result();
                                while ($rsRcpt && ($rr = $rsRcpt->fetch_assoc())) {
                                    $em = strtolower(trim((string)($rr['email'] ?? '')));
                                    if ($em === '' || !filter_var($em, FILTER_VALIDATE_EMAIL)) continue;
                                    $recipientEmails[$em] = true;
                                }
                            }
                        }
                    }

                    $recipientCount = count($recipientEmails);
                    foreach (array_keys($recipientEmails) as $notifyEmail) {
                        if (enqueueEmailJob($notifyEmail, $emailSubject, $bodyHtml, '', ['empresa_id' => $eid, 'context_type' => 'ticket_created', 'context_id' => $ticket_id])) {
                            $mailSent++;
                        } else {
                            addLog('admin_notify_email_enqueue_failed', 'No se pudo encolar correo para destinatario: ' . (string)$notifyEmail, 'ticket', $ticket_id, 'staff', 0);
                        }
                    }
                    if ($mailSent === 0) {
                        addLog('admin_notify_email_skipped', 'No hay destinatarios seleccionados en notification_recipients', 'ticket', $ticket_id, 'staff', 0);
                    }
                } else {
                    $reason = '';
                    if (!$adminNotifyEnabled) {
                        $reason = 'Notificación admin desactivada (mail.admin_notify_enabled=0)';
                    }
                    if ($reason !== '') {
                        addLog('admin_notify_email_skipped', $reason, 'ticket', $ticket_id, 'staff', 0);
                    }
                }
                addLog(
                    'ticket_email_queue_summary',
                    'Ticket ' . (string)$ticket_number . ' | recipients=' . (string)$recipientCount . ' | enqueued=' . (string)$mailSent . ' | notify=' . ($adminNotifyEnabled ? 'on' : 'off'),
                    'ticket',
                    $ticket_id,
                    'staff',
                    0
                );
                if ($mailSent > 0) {
                    $triggered = triggerEmailQueueWorkerAsync(40);
                    if (!$triggered) {
                        addLog('ticket_email_queue_trigger_failed', 'No se pudo disparar worker asíncrono', 'ticket', $ticket_id, 'staff', 0);
                    }
                }
                $_SESSION['flash_msg'] = 'Ticket creado exitosamente! Número: ' . (string)$ticket_number;
                $_SESSION['new_ticket_id'] = (int)$ticket_id;
                $_SESSION['prevent_open_back'] = 1;
                header('Location: tickets.php', true, 303);
                exit;
            } else {
                error_log('[tickets] open.php insert failed: ' . (string)$mysqli->error);
                $error = 'Error al crear el ticket. Intenta nuevamente.';
            }
        }
    }
}

}

// Bloquear volver atrás a open.php después de crear ticket
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['prevent_open_back'])) {
    header('Location: tickets.php', true, 302);
    exit;
}

// Obtener departamentos y temas
$departments = [];
$stmt = $mysqli->query('SELECT id, name FROM departments WHERE empresa_id = ' . (int)$eid . ' AND is_active = 1');
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
        $hasTopicDescCol = false;
        $cd = $mysqli->query("SHOW COLUMNS FROM help_topics LIKE 'description'");
        if ($cd && $cd->num_rows > 0) {
            $hasTopicDescCol = true;
        }
        if ($hasTopicDescCol) {
            $stmt = $mysqli->query('SELECT ht.id, ht.name, ht.dept_id, IFNULL(ht.description, \'\') AS description FROM help_topics ht WHERE ht.empresa_id = ' . (int)$eid . ' AND ht.is_active = 1 ORDER BY ht.name');
        } else {
            $stmt = $mysqli->query('SELECT ht.id, ht.name, ht.dept_id FROM help_topics ht WHERE ht.empresa_id = ' . (int)$eid . ' AND ht.is_active = 1 ORDER BY ht.name');
        }
        while ($row = $stmt->fetch_assoc()) {
            if (!$hasTopicDescCol) {
                $row['description'] = '';
            }
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
    <link rel="icon" type="image/x-icon" href="<?php echo html(rtrim(defined('APP_URL') ? APP_URL : '', '/')); ?>/publico/img/favicon.ico">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
    <style>
        textarea.is-invalid + .note-editor {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important;
        }
        
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

        .contact-fields-row {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 1rem;
        }
        .contact-fields-row .contact-field {
            flex: 1 1 100%;
            min-width: 0;
        }
        .contact-fields-row .anydesk-field {
            display: none;
        }
        .contact-fields-row.has-anydesk .contact-field {
            flex: 1 1 calc(50% - 8px);
        }
        .contact-fields-row.has-anydesk .anydesk-field {
            display: block;
        }

        @media (max-width: 767.98px) {
            .contact-fields-row,
            .contact-fields-row.has-anydesk {
                flex-direction: column;
                gap: 12px;
            }
            .contact-fields-row .contact-field,
            .contact-fields-row.has-anydesk .contact-field {
                flex: 1 1 100%;
                width: 100%;
            }
        }

        .attach-zone {
            border: 2px dashed #cbd5e1;
            background: #f8fafc;
            border-radius: 12px;
            padding: 14px;
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

        #open-loading-overlay{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(15,23,42,.46);z-index:2000;backdrop-filter: blur(10px);padding:18px}
        #open-loading-overlay .box{background:rgba(255,255,255,0.92);border:1px solid rgba(226,232,240,0.92);border-radius:22px;padding:18px 20px;min-width:320px;max-width:560px;width:100%;box-shadow:0 30px 90px rgba(15,23,42,.30);backdrop-filter: blur(10px);animation: openLoadingIn .14s ease-out}
        #open-loading-overlay .spinner-border{width:2.25rem;height:2.25rem}
        #open-loading-overlay .progress{border-radius:999px;overflow:hidden;background:rgba(148,163,184,0.25)}
        #open-loading-overlay .progress-bar{background:linear-gradient(90deg,#2563eb,#60a5fa);width:100%}
        @keyframes openLoadingIn{from{transform:translateY(6px) scale(.985); opacity:.65;}to{transform:translateY(0) scale(1); opacity:1;}}

        .notif-dd {
            border-radius: 18px;
            border: 1px solid rgba(226,232,240,0.95);
            overflow: hidden;
            box-shadow: 0 22px 55px rgba(15, 23, 42, 0.22);
        }
        .notif-dd-head {
            background: radial-gradient(900px circle at 0% 0%, rgba(255,255,255,0.35), transparent 55%),
                        linear-gradient(135deg, #2563eb, #0ea5e9);
            color: #fff;
        }
        .notif-dd-flex.show {
            display: flex !important;
            flex-direction: column;
        }
        .notif-dd-title { font-weight: 900; letter-spacing: 0.02em; }
        .notif-dd-sub { opacity: .85; font-weight: 700; font-size: .85rem; }
        .notif-dd-count {
            background: rgba(255,255,255,0.22);
            border: 1px solid rgba(255,255,255,0.28);
            color: #fff;
            padding: 3px 10px;
            border-radius: 999px;
            font-weight: 900;
            font-size: .78rem;
        }
        .notif-empty { border: 1px dashed rgba(148, 163, 184, 0.6); background: rgba(248, 250, 252, 0.7); border-radius: 16px; }
        .notif-item { border: 1px solid rgba(226,232,240,0.95); background: #fff; transition: transform .12s ease, box-shadow .12s ease, background .12s ease; }
        .notif-item:hover { transform: translateY(-1px); box-shadow: 0 12px 26px rgba(15, 23, 42, 0.10); background: #f1f5f9; }
        .notif-item + .notif-item { margin-top: 10px; }
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
                    <button class="btn btn-outline-light btn-sm user-menu-btn" type="button" id="notifBellBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Notificaciones">
                        <i class="bi bi-bell"></i>
                        <span id="notifBellBadge" class="badge bg-danger ms-1" style="display:none; font-size:.7rem;">0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0 notif-dd notif-dd-flex" style="min-width: 380px; max-height: 420px;" aria-labelledby="notifBellBtn">
                        <div class="p-3 notif-dd-head" style="flex-shrink: 0;">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:36px;height:36px;border-radius:14px;background:rgba(255,255,255,0.18);display:flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,0.22);">
                                        <i class="bi bi-bell" style="font-size:1.05rem;"></i>
                                    </div>
                                    <div>
                                        <div class="notif-dd-title">Notificaciones</div>
                                        <div class="notif-dd-sub" id="notifBellSub">Respuestas a tus tickets</div>
                                    </div>
                                </div>
                                <div id="notifBellCountPill" class="notif-dd-count" style="display:none;">0 nuevas</div>
                            </div>
                        </div>
                        <div id="notifBellList" class="p-3" style="flex: 1; overflow-y: auto; min-height: 0;">
                            <div class="notif-empty text-center text-muted py-3" style="font-size:.92rem">
                                <div class="mb-1" style="font-weight:900;color:#0f172a;">Todo al día</div>
                                <div style="color:#64748b;">Cuando el equipo responda, te aparecerá aquí.</div>
                            </div>
                        </div>
                        <div class="p-2 border-top" style="background:#f8f9fa; flex-shrink: 0;">
                            <button id="notifMarkAllRead" class="btn btn-sm btn-outline-secondary w-100" type="button" style="font-size:.85rem;">
                                <i class="bi bi-check-all"></i> Marcar todas como leídas
                            </button>
                        </div>
                    </div>
                </div>
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
                <div class="alert alert-danger"><?php echo html($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo html($success); ?></div>
            <?php endif; ?>

            <?php if (!$hasUserPhone): ?>
                <div class="alert alert-warning">
                    Debes registrar tu teléfono en tu perfil antes de crear un ticket.
                </div>
            <?php endif; ?>

            <form id="open-ticket-form" method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="subject" class="form-label">Asunto</label>
                    <input type="text" class="form-control <?php echo !empty($errorFields['subject']) ? 'is-invalid' : ''; ?>" id="subject" name="subject" value="<?php echo html($subject ?? ''); ?>" required>
                </div>

                <?php if ($hasTopics): ?>
                <div class="mb-3">
                    <label for="topic_id" class="form-label">Tema</label>
                    <select class="form-select <?php echo !empty($errorFields['topic_id']) ? 'is-invalid' : ''; ?>" id="topic_id" name="topic_id" onchange="updateDepartmentFromTopic()" required>
                        <option value="">Seleccionar tema...</option>
                        <?php foreach ($topics as $topic): ?>
                            <?php
                            $topicDesc = trim((string)($topic['description'] ?? ''));
                            $topicDescAttr = htmlspecialchars($topicDesc, ENT_QUOTES, 'UTF-8');
                            ?>
                            <option value="<?php echo $topic['id']; ?>" data-dept="<?php echo $topic['dept_id']; ?>" data-description="<?php echo $topicDescAttr; ?>"<?php echo $topicDesc !== '' ? ' title="' . $topicDescAttr . '"' : ''; ?> <?php echo ((int)($topic['id'] ?? 0) === (int)($topic_id ?? 0)) ? 'selected' : ''; ?>><?php echo html($topic['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-text text-muted small mb-0 mt-1" id="topic_description_hint" style="display:none;" role="status" aria-live="polite"></p>
                </div>
                <?php else: ?>
                <div class="mb-3">
                    <label for="dept_id" class="form-label">Departamento</label>
                    <select class="form-select <?php echo !empty($errorFields['dept_id']) ? 'is-invalid' : ''; ?>" id="dept_id" name="dept_id" required>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo ((int)($dept['id'] ?? 0) === (int)($dept_id ?? 0)) ? 'selected' : ''; ?>><?php echo html($dept['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div id="contact-fields-row" class="contact-fields-row">
                    <div class="contact-field telefono-field">
                        <label for="telefono_display" class="form-label">Teléfono registrado</label>
                        <input type="text" class="form-control" id="telefono_display" value="<?php echo html($userPhone !== '' ? $userPhone : 'No registrado'); ?>" readonly disabled>
                    </div>
                    <div id="network-extra-fields" class="contact-field anydesk-field">
                        <label for="anydesk" class="form-label">Anydesk</label>
                        <input type="text" class="form-control <?php echo !empty($errorFields['anydesk']) ? 'is-invalid' : ''; ?>" id="anydesk" name="anydesk" value="<?php echo html($anydesk ?? ''); ?>" autocomplete="off" disabled>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="body" class="form-label">Descripción</label>
                    <textarea class="form-control <?php echo !empty($errorFields['body']) ? 'is-invalid' : ''; ?>" id="body" name="body" rows="8"><?php echo html($body ?? ''); ?></textarea>
                </div>

                <div class="attach-zone" id="attach-zone">
                    <input type="file" name="attachments[]" id="attachments" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt">
                    <div class="attach-text"><i class="bi bi-paperclip"></i> Agregar archivos aquí o <a href="#" id="attach-choose-link">elegirlos</a></div>
                    <div class="attach-list" id="attach-list"></div>
                </div>

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <button id="open-ticket-submit" type="submit" class="btn btn-primary" <?php echo !$hasUserPhone ? 'disabled' : ''; ?>>Crear Ticket</button>
                <a href="tickets.php" class="btn btn-secondary">Cancelar</a>
            </form>
                </div>
            </main>
        </div>

        <script>
            function updateTopicHintFromSelect() {
                var topicSelect = document.getElementById('topic_id');
                var hint = document.getElementById('topic_description_hint');
                if (!topicSelect) return;
                var opt = topicSelect.options[topicSelect.selectedIndex];
                var d = '';
                if (opt && opt.value) {
                    d = (opt.getAttribute('data-description') || '').trim();
                }
                if (hint) {
                    if (d) {
                        hint.textContent = d;
                        hint.style.display = '';
                    } else {
                        hint.textContent = '';
                        hint.style.display = 'none';
                    }
                }
                if (d) {
                    topicSelect.setAttribute('title', d);
                } else {
                    topicSelect.removeAttribute('title');
                }
            }

            function updateDepartmentFromTopic() {
                var topicSelect = document.getElementById('topic_id');
                var deptSelect = document.getElementById('dept_id');
                if (topicSelect) {
                    updateTopicHintFromSelect();
                }
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

            document.addEventListener('DOMContentLoaded', function () {
                try { updateTopicHintFromSelect(); } catch (e) {}
            });
            
        (function () {
            try {
                var alerts = document.querySelectorAll('.alert');
                if (alerts && alerts.length) {
                    setTimeout(function () {
                        alerts.forEach(function (a) {
                            a.style.transition = 'opacity 200ms ease, max-height 220ms ease, margin 220ms ease, padding 220ms ease';
                            a.style.opacity = '0';
                            a.style.maxHeight = '0';
                            a.style.margin = '0';
                            a.style.paddingTop = '0';
                            a.style.paddingBottom = '0';
                            setTimeout(function () { try { a.remove(); } catch (e) {} }, 260);
                        });
                    }, 4500);
                }
            } catch (e) {}
            try {
                if (window.history && window.history.replaceState) {
                    window.history.replaceState({}, document.title, window.location.pathname);
                }
            } catch (e) {}

            var zone = document.getElementById('attach-zone');
            var input = document.getElementById('attachments');
            var list = document.getElementById('attach-list');
            var chooseLink = document.getElementById('attach-choose-link');
            if (!zone || !input || !list) return;

            var picking = false;
            try {
                input.addEventListener('click', function (ev) { try { ev.stopPropagation(); } catch (e) {} });
            } catch (e) {}

            var openPicker = function () {
                if (picking) return;
                picking = true;
                try { input.value = ''; } catch (e) {}
                try { input.click(); } catch (e) {}
                setTimeout(function () { picking = false; }, 800);
            };

            zone.addEventListener('click', function (e) {
                // Evitar que el click en botones internos (Quitar) dispare el picker
                if (e.target && (e.target.closest && e.target.closest('button[data-remove-index]'))) return;
                if (e.target === input) return;
                openPicker();
            });
            chooseLink && chooseLink.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                openPicker();
            });

            input.addEventListener('change', function () {
                picking = false;
                updateList();
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
            var extraFieldsWrap = document.getElementById('network-extra-fields');
            var contactFieldsRow = document.getElementById('contact-fields-row');
            var anydeskInput = document.getElementById('anydesk');
            var hasUserPhone = <?php echo $hasUserPhone ? 'true' : 'false'; ?>;

            var normalizeTopicText = function (value) {
                var text = (value || '').toString().trim().toLowerCase();
                if (text.normalize) {
                    text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                }
                text = text.replace(/[^a-z0-9\s]/g, ' ').replace(/\s+/g, ' ').trim();
                return text;
            };

            var isNetworkTopicSelected = function () {
                if (!topicSelect) return false;
                var idx = topicSelect.selectedIndex;
                if (idx < 0) return false;
                var option = topicSelect.options[idx];
                var topicText = normalizeTopicText(option ? option.text : '');
                return topicText.indexOf('redes') !== -1;
            };

            var toggleNetworkFields = function () {
                var shouldShow = isNetworkTopicSelected();
                if (!extraFieldsWrap) return shouldShow;
                if (contactFieldsRow) {
                    if (shouldShow) contactFieldsRow.classList.add('has-anydesk');
                    else contactFieldsRow.classList.remove('has-anydesk');
                }
                if (anydeskInput) anydeskInput.required = shouldShow;
                if (anydeskInput) anydeskInput.disabled = !shouldShow;
                return shouldShow;
            };

            if (topicSelect) {
                topicSelect.addEventListener('change', toggleNetworkFields);
            }
            toggleNetworkFields();

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
                document.getElementById('subject') && document.getElementById('subject').classList.remove('is-invalid');
                document.getElementById('topic_id') && document.getElementById('topic_id').classList.remove('is-invalid');
                document.getElementById('anydesk') && document.getElementById('anydesk').classList.remove('is-invalid');
                document.getElementById('body') && document.getElementById('body').classList.remove('is-invalid');

                try {
                    var inpsubject = document.getElementById('subject');
                    if (inpsubject && String(inpsubject.value || '').trim() === '') {
                        inpsubject.classList.add('is-invalid');
                        if (ev && ev.preventDefault) ev.preventDefault();
                        if (ev && ev.stopPropagation) ev.stopPropagation();
                        if (ev && ev.stopImmediatePropagation) ev.stopImmediatePropagation();
                        if (window.__showCreativePop) {
                            window.__showCreativePop('El asunto es obligatorio.', 'Falta Asunto');
                        } else {
                            alert('El asunto es obligatorio.');
                        }
                        try { inpsubject.focus(); } catch(e) {}
                        return false;
                    }

                    if (topicSelect && String(topicSelect.value || '').trim() === '') {
                        topicSelect.classList.add('is-invalid');
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

                    if (!hasUserPhone) {
                        if (ev && ev.preventDefault) ev.preventDefault();
                        if (ev && ev.stopPropagation) ev.stopPropagation();
                        if (ev && ev.stopImmediatePropagation) ev.stopImmediatePropagation();
                        if (window.__showCreativePop) {
                            window.__showCreativePop('Debes registrar tu teléfono en tu perfil antes de crear un ticket.', 'Teléfono no registrado');
                        } else {
                            alert('Debes registrar tu teléfono en tu perfil antes de crear un ticket.');
                        }
                        return false;
                    }

                    var needsNetworkFields = toggleNetworkFields();
                    if (needsNetworkFields) {
                        var anydeskValue = anydeskInput ? String(anydeskInput.value || '').trim() : '';
                        if (anydeskValue === '') {
                            if (anydeskInput) anydeskInput.classList.add('is-invalid');
                            if (ev && ev.preventDefault) ev.preventDefault();
                            if (ev && ev.stopPropagation) ev.stopPropagation();
                            if (ev && ev.stopImmediatePropagation) ev.stopImmediatePropagation();
                            if (window.__showCreativePop) {
                                window.__showCreativePop('Para el tema Redes Informática debes completar Anydesk.', 'Campo obligatorio');
                            } else {
                                alert('Para el tema Redes Informática debes completar Anydesk.');
                            }
                            try {
                                if (anydeskInput) anydeskInput.focus();
                            } catch (e5) {}
                            return false;
                        }
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
                        if (editor) editor.classList.add('is-invalid');
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

            function hideLoading(){
                var overlay = document.getElementById('open-loading-overlay');
                if (overlay) overlay.style.display = 'none';
                var btn = document.getElementById('open-ticket-submit');
                if (btn) btn.disabled = false;
            }

            try {
                var form = document.getElementById('open-ticket-form');
                if (!form) return;

                form.addEventListener('submit', function(ev){
                    if (ev.defaultPrevented) return;
                    showLoading();
                });

                window.addEventListener('pageshow', function(ev){
                    try {
                        if (ev && ev.persisted) {
                            hideLoading();
                        } else if (window.performance && performance.getEntriesByType) {
                            var nav = performance.getEntriesByType('navigation');
                            if (nav && nav[0] && nav[0].type === 'back_forward') {
                                hideLoading();
                            }
                        }
                    } catch (e) {}
                });
            } catch (e) {}
        })();
    </script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-es-ES.min.js"></script>

    <script>
        (function(){
            var POLL_MS = 12000;

            function setBellCount(n) {
                try {
                    var badge = document.getElementById('notifBellBadge');
                    var pill = document.getElementById('notifBellCountPill');
                    if (!badge) return;
                    var v = parseInt(n || 0, 10) || 0;
                    badge.textContent = String(v);
                    badge.style.display = v > 0 ? '' : 'none';
                    if (pill) {
                        pill.textContent = String(v) + ' nuevas';
                        pill.style.display = v > 0 ? '' : 'none';
                    }
                } catch (e) {}
            }

            function renderBell(items) {
                try {
                    var list = document.getElementById('notifBellList');
                    if (!list) return;
                    if (!items || !items.length) {
                        list.innerHTML = ''
                            + '<div class="notif-empty text-center text-muted py-3" style="font-size:.92rem">'
                            +   '<div class="mb-1" style="font-weight:900;color:#0f172a;">Todo al día</div>'
                            +   '<div style="color:#64748b;">Cuando el equipo responda, te aparecerá aquí.</div>'
                            + '</div>';
                        return;
                    }
                    var html = '';
                    items.forEach(function(it){
                        var msg = (it.message || '').toString();
                        var when = (it.created_at || '').toString();
                        var href = it.ticket_id ? ('view-ticket.php?id=' + String(it.ticket_id)) : 'tickets.php';
                        html += ''
                            + '<div class="notif-item rounded-3 px-2 py-2" style="cursor:pointer;">'
                            +   '<div class="d-flex align-items-start gap-2">'
                            +     '<div class="flex-shrink-0" style="width:34px;height:34px;border-radius:12px;background:rgba(37,99,235,.12);display:flex;align-items:center;justify-content:center;color:#2563eb;">'
                            +       '<i class="bi bi-chat-dots"></i>'
                            +     '</div>'
                            +     '<div class="flex-grow-1">'
                            +       '<div class="text-dark" style="font-weight:800;font-size:.92rem;line-height:1.15;">' + msg.replace(/</g,'&lt;') + '</div>'
                            +       '<div class="text-muted" style="font-size:.78rem;">' + when.replace(/</g,'&lt;') + '</div>'
                            +     '</div>'
                            +     '<div class="flex-shrink-0">'
                            +       '<button class="btn btn-sm btn-outline-primary" data-mark-read="' + String(it.id) + '" data-href="' + href + '" style="border-radius:999px;">Ver</button>'
                            +     '</div>'
                            +   '</div>'
                            + '</div>';
                    });
                    list.innerHTML = html;
                } catch (e) {}
            }

            function poll() {
                fetch('tickets.php?action=user_notifs_count', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (!data || !data.ok) return;
                        setBellCount(data.count || 0);
                        var cnt = (parseInt(data.count || 0, 10) || 0);
                        if (cnt <= 0) {
                            renderBell([]);
                            return;
                        }
                        return fetch('tickets.php?action=user_notifs_list', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                            .then(function(r){ return r.json(); })
                            .then(function(d2){
                                if (!d2 || !d2.ok) return;
                                renderBell(Array.isArray(d2.items) ? d2.items : []);
                            });
                    })
                    .catch(function(){});
            }

            document.addEventListener('click', function(ev){
                try {
                    var btn = ev.target && ev.target.getAttribute ? ev.target.getAttribute('data-mark-read') : null;
                    if (!btn) return;
                    ev.preventDefault();
                    var id = parseInt(btn, 10) || 0;
                    if (!id) return;
                    var href = ev.target.getAttribute('data-href') || 'tickets.php';
                    var fd = new FormData();
                    fd.append('id', String(id));
                    fetch('tickets.php?action=user_notifs_mark_read', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                        .then(function(){ window.location.href = href; })
                        .catch(function(){ window.location.href = href; });
                } catch (e) {}
            });

            // Botón "Marcar todas como leídas"
            (function(){
                var markAllBtn = document.getElementById('notifMarkAllRead');
                if (!markAllBtn) return;
                markAllBtn.addEventListener('click', function(ev){
                    ev.preventDefault();
                    ev.stopPropagation();
                    fetch('tickets.php?action=user_notifs_mark_all_read', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                        .then(function(r){ return r.json(); })
                        .then(function(data){
                            if (data && data.ok) {
                                setBellCount(0);
                                renderBell([]);
                                var list = document.getElementById('notifBellList');
                                if (list) {
                                    list.innerHTML = '<div class="notif-empty text-center text-muted py-3" style="font-size:.92rem"><div class="mb-1" style="font-weight:900;color:#0f172a;">Todo al día</div><div style="color:#64748b;">Todas las notificaciones fueron marcadas como leídas.</div></div>';
                                }
                            }
                        })
                        .catch(function(){});
                });
            })();

            poll();
            window.setInterval(poll, POLL_MS);
        })();
    </script>

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

    <div class="modal fade" id="imageInsertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Insertar imagen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="imageInsertFile" class="form-label">Seleccionar imagen</label>
                    <input type="file" class="form-control" id="imageInsertFile" accept="image/*">
                    <div class="my-2 text-center text-muted">o</div>
                    <label for="imageInsertUrl" class="form-label">Pegar URL de imagen</label>
                    <input type="url" class="form-control" id="imageInsertUrl" placeholder="https://...">
                    <div class="form-text">Selecciona una imagen para insertarla en la descripción.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="imageInsertConfirm">Insertar</button>
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

            var imageModalEl = document.getElementById('imageInsertModal');
            var imageFileEl = document.getElementById('imageInsertFile');
            var imageUrlEl = document.getElementById('imageInsertUrl');
            var imageConfirmEl = document.getElementById('imageInsertConfirm');
            var imageModal = null;
            var onImageSubmit = null;
            if (imageModalEl && window.bootstrap && bootstrap.Modal) {
                imageModal = new bootstrap.Modal(imageModalEl);
            }

            function openVideoModal(cb) {
                onVideoSubmit = cb;
                if (!videoModal || !videoUrlEl) return;
                videoUrlEl.value = '';
                videoModal.show();
                setTimeout(function () { try { videoUrlEl.focus(); } catch (e) {} }, 100);
            }

            function openImageModal(cb) {
                onImageSubmit = cb;
                if (!imageModal || !imageFileEl) return;
                imageFileEl.value = '';
                if (imageUrlEl) imageUrlEl.value = '';
                imageModal.show();
                setTimeout(function () { try { imageFileEl.focus(); } catch (e) {} }, 100);
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
            if (imageConfirmEl) {
                imageConfirmEl.addEventListener('click', function () {
                    if (!onImageSubmit || !imageFileEl) return;
                    var f = imageFileEl.files && imageFileEl.files[0] ? imageFileEl.files[0] : null;
                    var url = imageUrlEl ? (imageUrlEl.value || '').trim() : '';
                    if (!f && !url) return;
                    try { imageModal && imageModal.hide(); } catch (e) {}
                    try { onImageSubmit(f || url); } catch (e2) {}
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
            if (imageFileEl) {
                imageFileEl.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        imageConfirmEl && imageConfirmEl.click();
                    }
                });
            }
            if (imageUrlEl) {
                imageUrlEl.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        imageConfirmEl && imageConfirmEl.click();
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

            var myImageBtn = function () {
                var ui = jQuery.summernote.ui;
                return ui.button({
                    contents: '<i class="note-icon-picture"></i>',
                    tooltip: 'Insertar imagen',
                    click: function () {
                        openImageModal(function (fileOrUrl) {
                            if (!fileOrUrl) return;
                            if (typeof fileOrUrl === 'string') {
                                jQuery('#body').summernote('insertImage', fileOrUrl);
                                return;
                            }
                            var file = fileOrUrl;
                            var data = new FormData();
                            data.append('file', file);
                            data.append('csrf_token', <?php echo json_encode((string)($_SESSION['csrf_token'] ?? '')); ?>);
                            fetch('editor_image_upload.php', {
                                method: 'POST',
                                body: data,
                                credentials: 'same-origin'
                            })
                            .then(function (r) { return r.json(); })
                            .then(function (json) {
                                if (!json || !json.ok || !json.url) throw new Error((json && json.error) ? json.error : 'Upload failed');
                                jQuery('#body').summernote('insertImage', json.url);
                            })
                            .catch(function (err) {
                                window.__showCreativePop && window.__showCreativePop('No se pudo subir la imagen. Intenta con otra o usa Adjuntar archivos.', 'Error al subir imagen');
                                try { console.error(err); } catch (e) {}
                            });
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
                    ['insert', ['myImage', 'myVideo'] ],
                    ['view', ['codeview']]
                ],
                buttons: {
                    myVideo: myVideoBtn,
                    myImage: myImageBtn
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
