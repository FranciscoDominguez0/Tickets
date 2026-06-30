<?php
/**
 * VER TICKET (USUARIO)
 * Detalle de ticket con hilo y adjuntos
 */

require_once '../config.php';
require_once '../includes/helpers.php';

requireLogin('cliente');
$uid = (int)$_SESSION['user_id'];
$eid = (int)empresaId();

// Cargar info del usuario
$user = null;
$stmtU = $mysqli->prepare("SELECT firstname, lastname, email, org_tickets_view FROM users WHERE id = ? AND empresa_id = ?");
if ($stmtU) {
    $stmtU->bind_param('ii', $uid, $eid);
    $stmtU->execute();
    $resU = $stmtU->get_result();
    if ($rU = $resU->fetch_assoc()) {
        $user = $rU;
        $user['name'] = trim($rU['firstname'] . ' ' . $rU['lastname']) ?: 'Usuario';
    }
}
if (!$user) {
    $user = ['name' => 'Usuario', 'email' => '', 'org_tickets_view' => 0];
}

$user = getCurrentUser();
$uid = (int) ($_SESSION['user_id'] ?? 0);
$eid = (int)($_SESSION['empresa_id'] ?? 0);
if ($eid <= 0) $eid = 1;

// Re-cargar org_tickets_view ya que getCurrentUser() no lo trae
$user['org_tickets_view'] = 0;
if ($uid > 0 && $eid > 0) {
    $stmtOtv = $mysqli->prepare("SELECT org_tickets_view FROM users WHERE id = ? AND empresa_id = ? LIMIT 1");
    if ($stmtOtv) {
        $stmtOtv->bind_param('ii', $uid, $eid);
        if ($stmtOtv->execute()) {
            $rowOtv = $stmtOtv->get_result()->fetch_assoc();
            $user['org_tickets_view'] = (int)($rowOtv['org_tickets_view'] ?? 0);
        }
    }
}

if (!isset($_SESSION['client_dark_mode'])) {
    $_SESSION['client_dark_mode'] = 0;
    if (isset($mysqli) && $mysqli && !empty($_SESSION['user_id'])) {
        $uidT = (int)$_SESSION['user_id'];
        try {
            $colRes = $mysqli->query("SHOW COLUMNS FROM users LIKE 'dark_mode'");
            if ($colRes && $colRes->num_rows > 0) {
                $rs = $mysqli->query("SELECT dark_mode FROM users WHERE id = $uidT");
                if ($rs && $r = $rs->fetch_assoc()) {
                    $_SESSION['client_dark_mode'] = (int)$r['dark_mode'];
                }
            }
        } catch (Throwable $e) {}
    }
}
$isDarkMode = (isset($_SESSION['client_dark_mode']) && (int)$_SESSION['client_dark_mode'] === 1);
$tid = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;

if ($tid <= 0) {
    header('Location: tickets.php');
    exit;
}

$topicSelect = '';
$topicJoin = '';
if (function_exists('dbColumnExists') && function_exists('dbTableExists')
    && dbColumnExists('tickets', 'topic_id') && dbTableExists('help_topics')) {
    $topicSelect = ", ht.name AS topic_name\n";
    $topicJoin = "LEFT JOIN help_topics ht ON ht.id = t.topic_id AND ht.empresa_id = t.empresa_id\n";
}

// Cargar ticket y validar pertenencia (propio o vista por organización)
$stmt = $mysqli->prepare(
    "SELECT t.id, t.user_id, t.ticket_number, t.subject, t.created, t.updated, t.closed, t.status_id, t.staff_id, t.signature_token, t.signature_requested, t.client_signature,\n"
    . "       ts.name AS status_name, ts.color AS status_color,\n"
    . "       p.name AS priority_name, p.color AS priority_color,\n"
    . "       d.name AS dept_name,\n"
    . "       s.firstname AS staff_first, s.lastname AS staff_last"
    . $topicSelect
    . "FROM tickets t\n"
    . "JOIN ticket_status ts ON t.status_id = ts.id\n"
    . "JOIN priorities p ON t.priority_id = p.id\n"
    . "JOIN departments d ON t.dept_id = d.id\n"
    . "LEFT JOIN staff s ON t.staff_id = s.id\n"
    . $topicJoin
    . "WHERE t.id = ? AND t.empresa_id = ?\n"
    . "LIMIT 1"
);
$stmt->bind_param('ii', $tid, $eid);
$stmt->execute();
$t = $stmt->get_result()->fetch_assoc();
$ticketOwnerId = (int)($t['user_id'] ?? 0);
if (!$t || !clientUserCanAccessTicket($mysqli, $uid, $ticketOwnerId, $eid)) {
    header('Location: tickets.php');
    exit;
}

$isOrgPeerView = ($ticketOwnerId !== $uid);
$orgViewFromExplorer = (($_GET['from'] ?? '') === 'org');
$viewTicketBackUrl = 'tickets.php';
$orgViewBackUrl = 'tickets.php?view=org';

if ($orgViewFromExplorer) {
    $bOrg = isset($_GET['org_id']) && is_numeric($_GET['org_id']) ? (int)$_GET['org_id'] : 0;
    $bMem = isset($_GET['member_id']) && is_numeric($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
    if ($bOrg > 0) {
        $orgViewBackUrl .= '&org_id=' . $bOrg;
        if ((string)($_GET['list'] ?? '') === 'all') {
            $orgViewBackUrl .= '&list=all';
            $bOat = isset($_GET['oat']) && is_numeric($_GET['oat']) ? (int)$_GET['oat'] : 0;
            if ($bOat > 1) {
                $orgViewBackUrl .= '&oat=' . $bOat;
            }
        } elseif ($bMem > 0) {
            $orgViewBackUrl .= '&member_id=' . $bMem;
            $bOtp = isset($_GET['otp']) && is_numeric($_GET['otp']) ? (int)$_GET['otp'] : 0;
            if ($bOtp > 1) {
                $orgViewBackUrl .= '&otp=' . $bOtp;
            }
        }
        $bMonth = parseTicketMonthFilter($_GET['month'] ?? null);
        if ($bMonth) {
            $orgViewBackUrl .= '&month=' . rawurlencode($bMonth['param']);
        }
        $viewTicketBackUrl = $orgViewBackUrl;
    }
}

$ticketOwnerName = '';
if ($isOrgPeerView) {
    $stmtOwn = $mysqli->prepare('SELECT firstname, lastname, email FROM users WHERE id = ? AND empresa_id = ? LIMIT 1');
    if ($stmtOwn) {
        $stmtOwn->bind_param('ii', $ticketOwnerId, $eid);
        if ($stmtOwn->execute()) {
            $rowOwn = $stmtOwn->get_result()->fetch_assoc();
            if ($rowOwn) {
                $ticketOwnerName = trim((string)($rowOwn['firstname'] ?? '') . ' ' . (string)($rowOwn['lastname'] ?? ''));
                if ($ticketOwnerName === '') {
                    $ticketOwnerName = (string)($rowOwn['email'] ?? '');
                }
            }
        }
    }
}

$ticketClientSignatureUrl = '';
if (!empty($t['client_signature'])) {
    $sigRel = ltrim(str_replace('\\', '/', $t['client_signature']), '/');
    // Subir un nivel para salir de /upload/ y entrar en /firmas/
    $fullSigPath = realpath(__DIR__ . '/../' . $sigRel);
    if ($fullSigPath && is_file($fullSigPath)) {
        $ticketClientSignatureUrl = '../' . $sigRel . '?v=' . filemtime($fullSigPath);
    }
}

// Validar token de firma (si viene en la URL)
$isSignatureLink = false;
$sigToken = trim((string)($_GET['s'] ?? ''));
if ($sigToken !== '' && !empty($t['signature_token']) && $sigToken === $t['signature_token'] && empty($t['closed'])) {
    $isSignatureLink = true;
}

// Permitir firma desde sesión normal (sin token de correo) cuando el cliente es dueño del ticket
$signIntent = (string)($_GET['sign'] ?? '');
if (!$isSignatureLink && $signIntent === '1' && !empty($t['signature_requested']) && !empty($t['signature_token']) && empty($t['closed'])) {
    $isSignatureLink = true;
    $sigToken = (string)$t['signature_token'];
}

// Thread id
$stmt = $mysqli->prepare('SELECT id FROM threads WHERE ticket_id = ? AND (empresa_id = ? OR empresa_id IS NULL)');
$stmt->bind_param('ii', $tid, $eid);
$stmt->execute();
$threadRow = $stmt->get_result()->fetch_assoc();
$thread_id = (int)($threadRow['id'] ?? 0);

// Estado de aprobación
$ticketApprovalStatus = 'none';
$stmtA = $mysqli->prepare("SELECT status FROM ticket_approvals WHERE ticket_id = ? ORDER BY id DESC LIMIT 1");
if ($stmtA) {
    $stmtA->bind_param('i', $tid);
    $stmtA->execute();
    $resA = $stmtA->get_result();
    if ($rowA = $resA->fetch_assoc()) {
        $ticketApprovalStatus = $rowA['status'];
    }
}

// Check if quote was already sent
$quoteAlreadySent = false;
$stmtL = $mysqli->prepare("SELECT id FROM logs WHERE action = 'executive_quote_sent' AND object_type = 'ticket' AND object_id = ? LIMIT 1");
if ($stmtL) {
    $stmtL->bind_param('i', $tid);
    $stmtL->execute();
    if ($stmtL->get_result()->fetch_assoc()) {
        $quoteAlreadySent = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['cotizacion', 'aprobado', 'rechazado'])) {
    if ($ticketApprovalStatus === 'pending' && !empty($user['org_tickets_view'])) {
        if (validateCSRF()) {
            $newStatus = $_POST['action'];
            $stmtUpd = $mysqli->prepare("UPDATE ticket_approvals SET status = ?, manager_id = ?, resolved_at = NOW() WHERE ticket_id = ? AND status = 'pending'");
            $stmtUpd->bind_param('sii', $newStatus, $uid, $tid);
            if ($stmtUpd->execute()) {
                $ticketApprovalStatus = $newStatus;
                
                if ($newStatus === 'rechazado') {
                    // Cierra el ticket
                    $stmtC = $mysqli->prepare("UPDATE tickets SET closed = NOW(), status_id = COALESCE((SELECT id FROM ticket_status WHERE name LIKE '%cerrado%' OR name LIKE '%closed%' LIMIT 1), 5) WHERE id = ? AND empresa_id = ?");
                    if ($stmtC) {
                        $stmtC->bind_param('ii', $tid, $eid);
                        $stmtC->execute();
                    }
                }
                
                if (function_exists('notifyApprovalToAdminRecipients')) {
                    $statusLabelNotif = ($newStatus === 'cotizacion') ? 'Cotización' : (($newStatus === 'aprobado') ? 'Aprobado' : 'Rechazado');
                    notifyApprovalToAdminRecipients($tid, $statusLabelNotif);
                }
                
                $managerName = htmlspecialchars($user['name'], ENT_QUOTES);
                $svgCheck = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="#16a34a" viewBox="0 0 16 16" style="vertical-align:-1px;"><path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425z"/></svg>';
                $svgX     = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="#dc2626" viewBox="0 0 16 16" style="vertical-align:-1px;"><path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/></svg>';
                if ($newStatus === 'cotizacion') {
                    $threadMsg = 'Cotización solicitada por: ' . $managerName;
                } elseif ($newStatus === 'aprobado') {
                    $threadMsg = $svgCheck . ' Aprobado por: ' . $managerName;
                } else {
                    $threadMsg = $svgX . ' Rechazado por: ' . $managerName;
                }
                $entry_id = 0;
                $stmtTh = $mysqli->prepare("INSERT INTO thread_entries (empresa_id, thread_id, user_id, body, created) VALUES (?, ?, ?, ?, NOW())");
                if ($stmtTh && $thread_id > 0) {
                    $stmtTh->bind_param('iiis', $eid, $thread_id, $uid, $threadMsg);
                    if ($stmtTh->execute()) {
                        $entry_id = (int)$mysqli->insert_id;
                    }
                }
                
                // Process Orden de Compra file upload
                if ($newStatus === 'aprobado' && $entry_id > 0 && !empty($_FILES['orden_compra']['name'])) {
                    $file = $_FILES['orden_compra'];
                    if ($file['error'] === UPLOAD_ERR_OK) {
                        $orig = (string)$file['name'];
                        $mime = (string)$file['type'];
                        $size = (int)$file['size'];
                        $ext = strtolower((string)(pathinfo($orig, PATHINFO_EXTENSION) ?: ''));
                        
                        $allowedExt = [
                            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                            'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
                            'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'txt' => 'text/plain', 'zip' => 'application/zip', 'rar' => 'application/x-rar-compressed'
                        ];
                        
                        if (isset($allowedExt[$ext])) {
                            $safeName = bin2hex(random_bytes(8)) . '_' . time() . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
                            $uploadDir = defined('ATTACHMENTS_DIR') ? ATTACHMENTS_DIR : __DIR__ . '/uploads/attachments';
                            if (!is_dir($uploadDir)) {
                                @mkdir($uploadDir, 0755, true);
                            }
                            $path = $uploadDir . '/' . $safeName;
                            if (move_uploaded_file($file['tmp_name'], $path)) {
                                $relPath = 'uploads/attachments/' . $safeName;
                                $hash = @hash_file('sha256', $path) ?: '';
                                
                                $attachmentsHasEmpresa = false;
                                $colA = $mysqli->query("SHOW COLUMNS FROM attachments LIKE 'empresa_id'");
                                $attachmentsHasEmpresa = ($colA && $colA->num_rows > 0);
                                
                                if ($attachmentsHasEmpresa) {
                                    $stmtA = $mysqli->prepare("INSERT INTO attachments (empresa_id, thread_entry_id, filename, original_filename, mimetype, size, path, hash, created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                                    $stmtA->bind_param('iisssiss', $eid, $entry_id, $safeName, $orig, $mime, $size, $relPath, $hash);
                                } else {
                                    $stmtA = $mysqli->prepare("INSERT INTO attachments (thread_entry_id, filename, original_filename, mimetype, size, path, hash, created) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                                    $stmtA->bind_param('isssiss', $entry_id, $safeName, $orig, $mime, $size, $relPath, $hash);
                                }
                                $stmtA->execute();
                                
                                // Update thread body to reflect purchase order
                                $updatedMsg = $threadMsg . '<br><strong>Orden de compra</strong>';
                                $stmtUpdBody = $mysqli->prepare("UPDATE thread_entries SET body = ? WHERE id = ?");
                                $stmtUpdBody->bind_param('si', $updatedMsg, $entry_id);
                                $stmtUpdBody->execute();
                            }
                        }
                    }
                }
                
                $stmtUpdTkt = $mysqli->prepare("UPDATE tickets SET updated = NOW() WHERE id = ?");
                if ($stmtUpdTkt) {
                    $stmtUpdTkt->bind_param('i', $tid);
                    $stmtUpdTkt->execute();
                }


                
                $redirectParams = $_GET;
                $redirectParams['id'] = $tid;
                $redirectParams['from'] = 'org';
                $redirectParams['msg'] = 'approved';
                header('Location: view-ticket.php?' . http_build_query($redirectParams));
                exit;
            }
        }
    }
}

if (!$isOrgPeerView && $thread_id > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST' && function_exists('markThreadEntriesReadByUser')) {
    markThreadEntriesReadByUser($mysqli, $thread_id, $uid, $eid);
}

$reply_error = '';
$replyBodyPrefill = '';

$replySessionKey = 'reply_form_' . (int)$tid;
if (isset($_SESSION[$replySessionKey]) && is_array($_SESSION[$replySessionKey])) {
    $reply_error = (string)($_SESSION[$replySessionKey]['error'] ?? '');
    $replyBodyPrefill = (string)($_SESSION[$replySessionKey]['body'] ?? '');
    unset($_SESSION[$replySessionKey]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'reply') {
    if ($isOrgPeerView && empty($user['org_tickets_view'])) {
        $reply_error = 'No puedes responder en tickets de otros usuarios.';
    } elseif (!validateCSRF()) {
        $reply_error = 'Token de seguridad inválido';
    } elseif (!empty($t['closed'])) {
        $reply_error = 'Este ticket está cerrado y no admite nuevas respuestas.';
    } else {
        $body = trim($_POST['body'] ?? '');
        $replyBodyPrefill = $body;
        $hasFiles = false;
        if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']) && isset($_FILES['attachments']['name'])) {
            $names = $_FILES['attachments']['name'];
            if (is_array($names)) {
                foreach ($names as $n) {
                    if (trim((string)$n) !== '') { $hasFiles = true; break; }
                }
            } else {
                $hasFiles = trim((string)$names) !== '';
            }
        }
        $plain = trim(str_replace("\xC2\xA0", ' ', html_entity_decode(strip_tags($body), ENT_QUOTES, 'UTF-8')));

        $ticketMaxFileMb = (int)getAppSetting('tickets.ticket_max_file_mb', '10');
        if ($ticketMaxFileMb < 1) $ticketMaxFileMb = 1;
        if ($ticketMaxFileMb > 256) $ticketMaxFileMb = 256;
        $ticketMaxUploads = (int)getAppSetting('tickets.ticket_max_uploads', '5');
        if ($ticketMaxUploads < 0) $ticketMaxUploads = 0;
        if ($ticketMaxUploads > 20) $ticketMaxUploads = 20;
        $maxSize = $ticketMaxFileMb * 1024 * 1024;

        if ($reply_error === '' && !empty($_FILES['attachments']) && isset($_FILES['attachments']['name'])) {
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
                    $reply_error = 'No se pudo subir uno de los adjuntos.';
                    break;
                }
                $orig = trim((string)($files['name'][$i] ?? ''));
                $size = (int)($files['size'][$i] ?? 0);
                if ($orig === '' || $size <= 0) continue;
                $validCount++;
                if ($ticketMaxUploads === 0) {
                    $reply_error = 'No se permiten adjuntos.';
                    break;
                }
                if ($validCount > $ticketMaxUploads) {
                    $reply_error = 'Máximo de ' . (string)$ticketMaxUploads . ' adjunto(s) por mensaje.';
                    break;
                }
                if ($size > $maxSize) {
                    $reply_error = 'El adjunto "' . html($orig) . '" supera el tamaño máximo permitido (' . (string)$ticketMaxFileMb . ' MB).';
                    break;
                }
            }
        }

        if ($reply_error !== '') {
            // No continuar: ya existe un error (ej. adjunto muy grande / demasiados adjuntos)
        } elseif ($body === '' && !$hasFiles) {
            $reply_error = 'El mensaje no puede estar vacío.';
        } elseif (stripos($body, 'data:image/') !== false) {
            $reply_error = 'Las imágenes pegadas dentro del texto no están soportadas. Adjunta la imagen usando la opción de archivos.';
        } elseif (strlen($body) > 500000) {
            $reply_error = 'El mensaje es demasiado grande. Por favor adjunta archivos en vez de pegarlos dentro del texto.';
        } elseif ($thread_id <= 0) {
            $reply_error = 'No se encontró el hilo del ticket.';
        } else {
            $stmt = $mysqli->prepare('INSERT INTO thread_entries (empresa_id, thread_id, user_id, body, created) VALUES (?, ?, ?, ?, NOW())');
            $stmt->bind_param('iiis', $eid, $thread_id, $uid, $body);
            if ($stmt->execute()) {
                $entry_id = (int) $mysqli->insert_id;
                $stmtUpdTicket = $mysqli->prepare('UPDATE tickets SET updated = NOW() WHERE id = ? AND empresa_id = ?');
                if ($stmtUpdTicket) {
                    $stmtUpdTicket->bind_param('ii', $tid, $eid);
                    $stmtUpdTicket->execute();
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
                    'mp4' => 'video/mp4',
                    'webm' => 'video/webm',
                    'mov' => 'video/quicktime',
                    'mkv' => 'video/x-matroska',
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

                // No enviar notificaciones por correo cuando el usuario responde
                // El sistema ya registra la respuesta sin necesidad de enviar correos

                $_SESSION['reply_success'] = true;
                $redirectParams = $_GET;
                $redirectParams['id'] = (int)$tid;
                header('Location: view-ticket.php?' . http_build_query($redirectParams));
                exit;
            }
            $reply_error = 'No se pudo enviar la respuesta.';
        }
    }

    if ($reply_error !== '') {
        $_SESSION[$replySessionKey] = [
            'error' => $reply_error,
            'body' => $replyBodyPrefill,
        ];
        $redirectParams = $_GET;
        $redirectParams['id'] = (int)$tid;
        header('Location: view-ticket.php?' . http_build_query($redirectParams));
        exit;
    }
}





$reply_success = false;
if (!empty($_SESSION['reply_success'])) {
    $reply_success = true;
    unset($_SESSION['reply_success']);
}

// Descarga de adjuntos
if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    $aid = (int) $_GET['download'];
    $stmt = $mysqli->prepare(
        "SELECT a.id, a.original_filename, a.mimetype, a.path, a.size\n"
        . "FROM attachments a\n"
        . "JOIN thread_entries te ON te.id = a.thread_entry_id\n"
        . "JOIN threads th ON th.id = te.thread_id\n"
        . "JOIN tickets tk ON tk.id = th.ticket_id\n"
        . "WHERE a.id = ? AND te.thread_id = ? AND tk.empresa_id = ?\n"
        . "LIMIT 1"
    );
    $stmt->bind_param('iii', $aid, $thread_id, $eid);
    $stmt->execute();
    $att = $stmt->get_result()->fetch_assoc();
    if (!$att) {
        http_response_code(404);
        exit('Archivo no encontrado');
    }

    $rel = (string) ($att['path'] ?? '');

    // Directorios base posibles
    $baseUpload = __DIR__;        // upload/
    $baseRoot   = dirname(__DIR__); // sistema-tickets/

    // Rutas candidatas en orden de preferencia
    $full = '';
    if ($rel !== '') {
        // 1. ATTACHMENTS_DIR configurado en config.php (upload/uploads/attachments)
        $fullDir = defined('ATTACHMENTS_DIR')
            ? rtrim(ATTACHMENTS_DIR, '/\\') . '/' . ltrim(str_replace('uploads/attachments/', '', $rel), '/\\')
            : '';
        // 2. Relativo a upload/ → upload/uploads/attachments/...
        $full1 = rtrim($baseUpload, '/\\') . '/' . ltrim($rel, '/\\');
        // 3. Relativo a la raíz → sistema-tickets/uploads/attachments/... (archivos guardados en ubicación antigua)
        $full2 = rtrim($baseRoot, '/\\') . '/' . ltrim($rel, '/\\');
        // 4. La ruta tal cual está en la BD (por si es absoluta)
        $full3 = $rel;

        foreach ([$fullDir, $full1, $full2, $full3] as $candidate) {
            if ($candidate !== '' && is_file($candidate)) {
                $full = $candidate;
                break;
            }
        }
    }

    if ($full === '') {
        http_response_code(404);
        exit('Archivo no encontrado');
    }

    $filename = (string) ($att['original_filename'] ?? 'archivo');
    $mime = (string) ($att['mimetype'] ?? 'application/octet-stream');
    $isInline = isset($_GET['inline']) && $_GET['inline'] == '1';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($full));
    if ($isInline) {
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');
    } else {
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    }
    header('X-Content-Type-Options: nosniff');
    readfile($full);
    exit;
}

// Entradas del hilo
$entries = [];
$attachmentsByEntry = [];
if ($thread_id > 0) {
    $stmt = $mysqli->prepare(
        "SELECT te.id, te.body, te.created, te.user_id, te.staff_id, te.is_internal,\n"
        . "       u.firstname AS user_first, u.lastname AS user_last,\n"
        . "       s.firstname AS staff_first, s.lastname AS staff_last\n"
        . "FROM thread_entries te\n"
        . "LEFT JOIN users u ON u.id = te.user_id\n"
        . "LEFT JOIN staff s ON s.id = te.staff_id\n"
        . "WHERE te.thread_id = ? AND (te.empresa_id = ? OR te.empresa_id IS NULL) AND (te.is_internal IS NULL OR te.is_internal = 0)\n"
        . "ORDER BY te.created ASC, te.id ASC"
    );
    $stmt->bind_param('ii', $thread_id, $eid);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $entries[] = $row;
    }

    if (!empty($entries)) {
        $entryIds = array_map(fn($e) => (int)$e['id'], $entries);
        $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
        $types = str_repeat('i', count($entryIds));
        $attachmentsHasEmpresa = false;
        $colA = $mysqli->query("SHOW COLUMNS FROM attachments LIKE 'empresa_id'");
        $attachmentsHasEmpresa = ($colA && $colA->num_rows > 0);

        $sql = "SELECT id, thread_entry_id, original_filename, mimetype, size FROM attachments WHERE thread_entry_id IN ($placeholders)";
        if ($attachmentsHasEmpresa) {
            $sql .= " AND empresa_id = ?";
            $types .= 'i';
            $entryIds[] = (int)$eid;
        }
        $sql .= " ORDER BY id";
        $stmtA = $mysqli->prepare($sql);
        $stmtA->bind_param($types, ...$entryIds);
        $stmtA->execute();
        $resA = $stmtA->get_result();
        while ($a = $resA->fetch_assoc()) {
            $entryId = (int) ($a['thread_entry_id'] ?? 0);
            if ($entryId <= 0) {
                continue;
            }
            if (!isset($attachmentsByEntry[$entryId])) {
                $attachmentsByEntry[$entryId] = [];
            }
            $attachmentsByEntry[$entryId][] = $a;
        }
    }

    if (function_exists('getThreadEntryReadStatusMap')) {
        $entryReadMap = getThreadEntryReadStatusMap(
            $mysqli,
            array_map(static fn($e) => (int)($e['id'] ?? 0), $entries),
            $eid
        );
    }
}
if (!isset($entryReadMap) || !is_array($entryReadMap)) {
    $entryReadMap = [];
}

function humanSize($bytes) {
    $bytes = (int) $bytes;
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = (int) floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);
    $val = $bytes / pow(1024, $i);
    return ($i === 0 ? (string) $val : number_format($val, 1)) . ' ' . $units[$i];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo html($t['ticket_number']); ?> - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo html(rtrim(defined('APP_URL') ? APP_URL : '', '/')); ?>/publico/img/favicon.ico">
    <link rel="stylesheet" href="scp/css/vendor/bootstrap-5.3.0.min.css">
    <link rel="stylesheet" href="scp/css/vendor/bootstrap-icons-1.11.1.css">
    <link rel="stylesheet" href="scp/css/vendor/summernote-lite.min.css">
    <link rel="stylesheet" href="css/client_dark.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/client_dark.css'); ?>">
    <link rel="stylesheet" href="css/client-ticket-view.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/client-ticket-view.css'); ?>">
    <style>
        body {
            background: #f6f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 62px;
        }

        /* Dynamic badge styles */
        [style*="--badge-bg-light"] {
            background-color: var(--badge-bg-light) !important;
            color: var(--badge-color-light) !important;
            border: 1px solid transparent !important;
            transition: background-color 0.3s, color 0.3s, border-color 0.3s;
        }
        body.dark-mode [style*="--badge-bg-light"] {
            background-color: var(--badge-bg-dark) !important;
            color: var(--badge-color-dark) !important;
            border-color: var(--badge-border-dark) !important;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(700px circle at 12% 0%, rgba(245, 158, 11, 0.08), transparent 52%),
                radial-gradient(900px circle at 88% 10%, rgba(239, 68, 68, 0.10), transparent 55%),
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
            flex-wrap: nowrap !important;
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
            height: 28px;
            width: auto;
            max-height: 28px;
            max-width: 160px;
            object-fit: contain;
            display: block;
            filter: drop-shadow(0 10px 22px rgba(0,0,0,0.22));
        }
        .topbar .user-menu-btn {
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

        .container-main { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .center-wrap { max-width: 980px; margin: 0 auto; }
        .panel-soft {
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(10px);
            border-radius: 22px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.08);
            overflow: hidden;
            padding: 18px;
        }

        .panel-soft {
            background-image:
                radial-gradient(900px circle at 0% 0%, rgba(239, 68, 68, 0.06), transparent 52%),
                radial-gradient(700px circle at 100% 0%, rgba(245, 158, 11, 0.06), transparent 55%);
        }

        .card-soft { transition: box-shadow .15s ease, border-color .15s ease; }
        .card-soft:hover { box-shadow: 0 14px 36px rgba(15, 23, 42, 0.10); border-color: #cbd5e1; }
        .user-badge {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.18);
            padding: 8px 10px;
            border-radius: 999px;
        }
        .user-badge .avatar {
            width: 38px;
            height: 38px;
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
        .user-badge .name { font-weight: 900; }
        .user-badge .mail { opacity: 0.9; font-weight: 700; font-size: 0.85rem; }
        .page-header {
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            padding: 22px 22px;
            border-radius: 16px;
            margin-bottom: 18px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            color: #0f172a;
            border: 1px solid #e2e8f0;
            border-left: 6px solid #ef4444;
        }
        .page-header .sub { color: #64748b; font-weight: 700; }

        .card-soft { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; overflow: hidden; }
        .card-soft .head { padding: 20px 22px; border-bottom: 1px solid #e2e8f0; }
        .card-soft .body { padding: 24px; }

        .ticket-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; }
        .ticket-meta .label { color: #64748b; font-size: 0.85rem; }
        .ticket-meta .value { color: #0f172a; font-weight: 600; }

        .thread { margin-top: 18px; }

        .ticket-view-entry {
            margin-bottom: 24px;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .ticket-view-entry .entry-row {
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }

        .ticket-view-entry.user .entry-row {
            flex-direction: row-reverse;
        }

        .ticket-view-entry .entry-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }

        .ticket-view-entry .entry-avatar-inner {
            font-weight: 800;
            font-size: 0.95rem;
            letter-spacing: 0.05em;
        }

        .ticket-view-entry.staff .entry-avatar {
            background: #0f62fe;
            color: #ffffff;
        }

        .ticket-view-entry.user .entry-avatar {
            background: #eff6ff;
            color: #1e3a8a;
        }

        .ticket-view-entry.internal .entry-avatar {
            background: #ffedd5;
            color: #9a3412;
        }

        .ticket-view-entry .entry-bubble-wrapper {
            display: flex;
            flex-direction: column;
            max-width: 800px;
            flex: 1;
            min-width: 0;
        }


        .ticket-view-entry.user .entry-bubble-wrapper {
            align-items: flex-end;
        }

        .ticket-view-entry .entry-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            padding-left: 2px;
        }

        .ticket-view-entry .author-name {
            font-weight: 800;
            color: #0f172a;
            font-size: 1rem;
        }

        .ticket-view-entry .author-role {
            font-size: 0.75rem;
            font-weight: 700;
            color: #0f62fe;
            background: #eff6ff;
            padding: 2px 8px;
            border-radius: 12px;
        }

        .ticket-view-entry .entry-content {
            border-radius: 16px;
            padding: 16px 20px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
            box-sizing: border-box;
            width: 100%;
            max-width: 100%;
        }

        .ticket-view-entry.user .entry-content {
            background: #f1f5f9;
            border-color: #e2e8f0;
        }

        .ticket-view-entry.staff .entry-content {
            background: #eff6ff;
            border-color: #dbeafe;
        }

        .ticket-view-entry.internal .entry-content {
            background: #fffbeb;
            border-color: #fde68a;
        }

        .ticket-view-entry .entry-meta-top {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .ticket-view-entry .entry-body {
            color: #1e293b;
            white-space: pre-wrap;
            word-break: break-word;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .ticket-view-entry .entry-body p {
            margin: 0 0 0.5em;
        }

        .ticket-view-entry .entry-body p:last-child {
            margin-bottom: 0;
        }

        .ticket-view-entry .entry-footer {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .ticket-view-entry.user .entry-footer {
            justify-content: flex-end;
        }

        .ticket-view-entry.staff .entry-footer {
            justify-content: flex-start;
        }

        .chat-att-list {
            margin-top: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .chat-att-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            padding: 10px 14px;
            max-width: 100%;
            min-width: 0;
            transition: all 0.2s;
        }


        .chat-att-item:hover {
            background: #f1f5f9;
            border-color: #e2e8f0;
        }

        .chat-att-icon {
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chat-att-info {
            flex: 1;
            min-width: 0;
        }


        .chat-att-info .att-filename {
            color: #0f172a;
            font-weight: 700;
            font-size: 0.88rem;
            text-decoration: none;
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            width: 100%;
        }
        @media (max-width: 600px) {
            .chat-att-info .att-filename {
                max-width: 180px;
            }
        }



        .chat-att-info .att-filename:hover {
            color: #ef4444;
            text-decoration: underline;
        }

        .chat-att-info .att-size {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 2px;
            font-weight: 600;
        }

        .chat-att-download {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            text-decoration: none;
        }

        .chat-att-download:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #cbd5e1;
        }

        .ticket-view-entry .entry-body img { max-width: 100% !important; height: auto !important; display: block; object-fit: contain; }
        .ticket-view-entry .entry-body iframe { width: 100% !important; max-width: 100% !important; aspect-ratio: 16 / 9; height: auto !important; display: block; }

            .note-editor .note-editable img { max-width: 100% !important; height: auto !important; display: block; object-fit: contain; }
            .note-editor .note-editable iframe { width: 100% !important; max-width: 100% !important; aspect-ratio: 16 / 9; height: auto !important; display: block; }

            /* ── Dashboard Premium Layout ── */
            .ticket-view-overview {
                margin-bottom: 24px;
                background: #fff;
                border-radius: 16px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
                border: 1px solid #e2e8f0;
                overflow: hidden;
            }

            .ticket-view-overview-desktop {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 0;
            }

            .ticket-view-overview-desktop > div {
                padding: 20px 24px;
                border-right: 1px solid #f1f5f9;
            }

            .ticket-view-overview-desktop > div:last-child {
                border-right: none;
            }

            .ticket-view-overview .field {
                margin-bottom: 16px;
            }

            .ticket-view-overview .field:last-child {
                margin-bottom: 0;
            }

            .ticket-view-overview .field label {
                font-size: 0.65rem;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: #94a3b8;
                font-weight: 800;
                display: flex;
                align-items: center;
                gap: 6px;
                margin-bottom: 8px;
            }

            .ticket-view-overview .field .value {
                font-size: 1rem;
                font-weight: 700;
                color: #1e293b;
            }

            .ticket-view-overview .divider {
                height: 1px;
                background: #f1f5f9;
                margin: 16px 0;
            }

            /* ── Mobile Dashboard ── */
            .mobile-header {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 16px;
                background: #f8fafc;
                border-bottom: 1px solid #f1f5f9;
            }

            .mobile-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                border-radius: 999px;
                font-size: 0.75rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.02em;
            }

            .mobile-badge .dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
            }

            .mobile-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1px;
                background: #f1f5f9;
            }

            .mobile-grid-item {
                background: #fff;
                padding: 16px;
            }

            .mobile-grid-item label {
                display: block;
                font-size: 0.6rem;
                font-weight: 800;
                color: #94a3b8;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin-bottom: 4px;
            }

            .mobile-grid-item .val {
                font-size: 0.88rem;
                font-weight: 700;
                color: #1e293b;
                line-height: 1.2;
            }

            .mobile-user-section {
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 16px;
                background: #fff;
                border-bottom: 1px solid #f1f5f9;
            }

            .mobile-avatar {
                width: 48px;
                height: 48px;
                border-radius: 50%;
                background: #f1f5f9;
                color: #64748b;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.4rem;
                flex-shrink: 0;
            }

            .mobile-user-info {
                flex-grow: 1;
                min-width: 0;
            }

            .mobile-user-info .name {
                font-size: 1rem;
                font-weight: 800;
                color: #0f172a;
                margin-bottom: 2px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .mobile-user-info .sub {
                font-size: 0.78rem;
                font-weight: 600;
                color: #64748b;
                display: flex;
                align-items: center;
                gap: 4px;
            }

            .entry-footer {
                font-size: 0.72rem;
                color: #94a3b8;
                margin-top: 4px;
                padding-left: 4px;
            }
            .ticket-view-entry.user .entry-footer { text-align: right; padding-left: 0; padding-right: 4px; }

            .att-list { margin-top: 10px; display: flex; flex-direction: column; gap: 6px; }
            .att-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 10px; flex-wrap: wrap; }
            .att-item > div:first-child { min-width: 0; }
            .att-item a { text-decoration: none; font-weight: 600; color: #ef4444; display: inline-block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: bottom; }
            .att-item .size { color: #64748b; font-size: 0.85rem; }

        .reply-card { margin-top: 16px; padding: 18px; border-radius: 16px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 4px 24px rgba(0,0,0,0.06); }
        .org-readonly-notice {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            padding: 16px 20px;
            border-radius: 16px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            transition: all 0.3s ease;
        }
        .org-readonly-notice__main {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            min-width: 0;
            flex: 1;
        }
        .org-readonly-notice__icon {
            flex-shrink: 0;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #dbeafe;
            border: 1px solid #93c5fd;
            color: #2563eb;
            font-size: 1.1rem;
        }
        .org-readonly-notice__title {
            margin: 0 0 2px;
            font-size: 0.95rem;
            font-weight: 600;
            color: #1e40af;
            letter-spacing: -0.01em;
        }
        .org-readonly-notice__text {
            margin: 0;
            font-size: 0.875rem;
            color: #3b82f6;
            line-height: 1.4;
        }
        .org-readonly-notice__back {
            flex-shrink: 0;
            font-weight: 500;
            background: #fff;
            border-color: #93c5fd;
            color: #1d4ed8;
        }
        .org-readonly-notice__back:hover {
            background: #dbeafe;
            border-color: #60a5fa;
            color: #1e40af;
        }
        body.dark-mode .org-readonly-notice {
            border-color: #334155;
            background: #000000;
        }
        body.dark-mode .org-readonly-notice__icon {
            background: rgba(255, 255, 255, 0.06);
            border-color: #3f3f46;
            color: #a1a1aa;
        }
        body.dark-mode .org-readonly-notice__title { color: #f4f4f5; }
        body.dark-mode .org-readonly-notice__text { color: #a1a1aa; }
        body.dark-mode .org-readonly-notice__back {
            background: #262626;
            border-color: #52525b;
            color: #e4e4e7;
        }
        body.dark-mode .org-readonly-notice__back:hover {
            background: #333333;
            border-color: #71717a;
            color: #fafafa;
        }

        /* Modificadores de aviso de organización (Aprobaciones) */
        .org-readonly-notice--warning {
            background: linear-gradient(135deg, #ffffff 0%, #fef2f2 100%) !important;
            border: 1px solid #fca5a5 !important;
            box-shadow: 0 4px 15px rgba(185, 28, 28, 0.08) !important;
        }
        .org-readonly-notice--warning .org-readonly-notice__icon {
            background: #b91c1c !important;
            border-color: #b91c1c !important;
            color: #ffffff !important;
            width: 44px !important;
            height: 44px !important;
            border-radius: 12px !important;
            font-size: 1.3rem !important;
            box-shadow: 0 4px 12px rgba(185, 28, 28, 0.25) !important;
        }
        .org-readonly-notice--warning .org-readonly-notice__title {
            color: #000000 !important;
            font-weight: 700 !important;
            font-size: 1rem !important;
        }
        .org-readonly-notice--warning .org-readonly-notice__text {
            color: #444444 !important;
            font-size: 0.9rem !important;
        }

        .org-readonly-notice--success {
            background-color: #f0fdf4 !important;
            border-color: #bbf7d0 !important;
        }
        .org-readonly-notice--success .org-readonly-notice__icon {
            background-color: #dcfce7 !important;
            border-color: #86efac !important;
            color: #16a34a !important;
        }
        .org-readonly-notice--success .org-readonly-notice__title {
            color: #166534 !important;
        }
        .org-readonly-notice--success .org-readonly-notice__text {
            color: #15803d !important;
        }
        .btn-approval-warn {
            background-color: #000000 !important;
            border: 1px solid #000000 !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            border-radius: 999px !important;
            padding: 8px 20px !important;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2) !important;
            transition: all 0.2s ease;
        }
        .btn-approval-warn:hover {
            background-color: #333333 !important;
            border-color: #333333 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3) !important;
        }

        .btn-approval-success {
            background-color: #10b981 !important;
            border: 1px solid #059669 !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            border-radius: 999px !important;
            padding: 8px 20px !important;
            box-shadow: 0 2px 6px rgba(16, 185, 129, 0.2) !important;
            transition: all 0.2s ease;
        }
        .btn-approval-success:hover {
            background-color: #059669 !important;
            border-color: #047857 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3) !important;
        }

        .btn-approval-danger {
            background-color: #ef4444 !important;
            border: 1px solid #dc2626 !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            border-radius: 999px !important;
            padding: 8px 20px !important;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.2) !important;
            transition: all 0.2s ease;
        }
        .btn-approval-danger:hover {
            background-color: #dc2626 !important;
            border-color: #b91c1c !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3) !important;
        }

        /* Dark Mode para modificadores */
        body.dark-mode .org-readonly-notice--warning {
            background: linear-gradient(135deg, rgba(185, 28, 28, 0.12) 0%, rgba(120, 20, 20, 0.08) 100%) !important;
            border-color: rgba(185, 28, 28, 0.35) !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3) !important;
        }
        body.dark-mode .org-readonly-notice--warning .org-readonly-notice__icon {
            background: rgba(185, 28, 28, 0.25) !important;
            border: 1px solid rgba(220, 38, 38, 0.4) !important;
            color: #fca5a5 !important;
            box-shadow: none !important;
        }
        body.dark-mode .org-readonly-notice--warning .org-readonly-notice__title {
            color: #fecaca !important;
        }
        body.dark-mode .org-readonly-notice--warning .org-readonly-notice__text {
            color: #d4d4d8 !important;
        }

        body.dark-mode .org-readonly-notice--success {
            background-color: #142d1e !important;
            border-color: #1b4d2c !important;
        }
        body.dark-mode .org-readonly-notice--success .org-readonly-notice__icon {
            background-color: #1b432a !important;
            border-color: #245e3b !important;
            color: #4ade80 !important;
        }
        body.dark-mode .org-readonly-notice--success .org-readonly-notice__title {
            color: #86efac !important;
        }
        body.dark-mode .org-readonly-notice--success .org-readonly-notice__text {
            color: #a7f3d0 !important;
        }

        body.dark-mode .btn-approval-warn {
            background-color: rgba(255, 255, 255, 0.1) !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
            color: #e4e4e7 !important;
        }
        body.dark-mode .btn-approval-warn:hover {
            background-color: rgba(255, 255, 255, 0.18) !important;
            border-color: rgba(255, 255, 255, 0.35) !important;
            color: #ffffff !important;
        }

        body.dark-mode .btn-approval-success {
            background-color: #059669 !important;
            border-color: #047857 !important;
            color: #ffffff !important;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3) !important;
        }
        body.dark-mode .btn-approval-success:hover {
            background-color: #10b981 !important;
            border-color: #059669 !important;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4) !important;
        }

        body.dark-mode .btn-approval-danger {
            background-color: #ef4444 !important;
            border-color: #dc2626 !important;
            color: #ffffff !important;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.25) !important;
        }
        body.dark-mode .btn-approval-danger:hover {
            background-color: #dc2626 !important;
            border-color: #b91c1c !important;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.35) !important;
        }

        /* Custom Soft Modal */
        .custom-modal-soft {
            border-radius: 24px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(12px);
        }
        body.dark-mode .custom-modal-soft {
            background-color: #000000 !important;
            border-color: #27272a !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            background: rgba(24, 24, 27, 0.95) !important;
        }
        body.dark-mode .custom-modal-soft .modal-title {
            color: #f4f4f5 !important;
        }
        body.dark-mode .custom-modal-soft #approvalModalMsg {
            color: #a1a1aa !important;
        }
        body.dark-mode .custom-modal-soft .btn-light {
            background-color: #000000 !important;
            border-color: #3f3f46 !important;
            color: #e4e4e7 !important;
        }
        body.dark-mode .custom-modal-soft .btn-light:hover {
            background-color: #3f3f46 !important;
            color: #ffffff !important;
        }
        body.dark-mode .custom-modal-soft .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        .attach-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 32px 24px;
            text-align: center;
            background: #f8fafc;
            margin-bottom: 16px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .attach-zone:hover,
        .attach-zone.dragover {
            border-color: #ef4444;
            background: #fef2f2;
        }

        .attach-zone .dz-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #fef2f2;
            color: #ef4444;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 12px;
        }

        .attach-zone input[type="file"] {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
            opacity: 0;
        }

        .attach-zone .attach-text {
            color: #334155;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .attach-zone .attach-text a {
            color: #ef4444;
            text-decoration: none;
            font-weight: 600;
        }

        .attach-zone .attach-text a:hover {
            text-decoration: underline;
        }

        .attach-zone .attach-hint {
            color: #64748b;
            font-size: 0.8rem;
            margin-top: 6px;
        }

        .attach-list {
            margin-top: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            width: 100%;
        }

        .dz-preview-card {
            display: flex;
            align-items: center;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px 10px 10px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            width: auto;
            min-width: 280px;
            max-width: 340px;
            text-align: left;
            gap: 12px;
        }
        .dz-preview-icon {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            background: #f1f5f9;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            flex-shrink: 0;
            overflow: hidden;
        }
        .dz-preview-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .dz-preview-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .dz-preview-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
            line-height: 1.3;
        }
        .dz-preview-size {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
            line-height: 1;
        }
        .dz-preview-remove {
            background: none;
            border: none;
            color: #94a3b8;
            padding: 4px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .dz-preview-remove:hover {
            color: #ef4444;
        }

        .notif-dd {
            border-radius: 18px;
            border: 1px solid rgba(226,232,240,0.95);
            overflow: hidden;
            box-shadow: 0 22px 55px rgba(15, 23, 42, 0.22);
        }
        @media (max-width: 576px) {
            .notif-dd {
                position: fixed !important;
                top: 60px !important;
                left: 50% !important;
                right: auto !important;
                transform: translateX(-50%) !important;
                width: 320px !important;
                min-width: unset !important;
                max-width: 90vw !important;
                margin-top: 0 !important;
            }
        }
        .notif-dd-head {
            background: radial-gradient(900px circle at 0% 0%, rgba(255,255,255,0.35), transparent 55%),
                        linear-gradient(135deg, #ef4444, #f87171);
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

        @media (max-width: 760px) {
            .ticket-meta { grid-template-columns: 1fr 1fr; gap: 16px; }
            .ticket-view-entry .entry-body img { max-height: 260px !important; }
            .att-item a { white-space: normal; overflow-wrap: anywhere; word-break: break-word; }
            .attach-zone { padding: 20px 14px; }
            .attach-zone .attach-text { font-size: 0.85rem; }
            .attach-zone .attach-hint { font-size: 0.72rem; }
        }

        @media (min-width: 761px) {
            .ticket-view-entry .entry-body img { max-width: 340px !important; }
            .ticket-view-entry .entry-body iframe { max-width: 520px !important; }
            .note-editor .note-editable img { max-width: 340px !important; }
            .note-editor .note-editable iframe { max-width: 520px !important; }
        }

        /* Image Preview Styles */
        .att-image-preview-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10000;
            pointer-events: auto;
            display: none;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            padding: 8px;
            max-width: min(90vw, 520px);
            animation: attFadeIn 0.2s ease-out forwards;
        }
        @media (max-width: 768px) {
            .att-image-preview-container {
                margin-top: -20px; /* Leave space for bottom button */
            }
        }
        .att-image-preview-container img {
            max-width: 100%;
            max-height: 450px;
            display: block;
            border-radius: 8px;
            object-fit: contain;
        }
        @keyframes attFadeIn {
            from { opacity: 0; transform: translate(-50%, -50%) scale(0.95); }
            to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        }
        .att-image-preview-container .preview-content-docx {
            padding: 15px;
            font-size: 13px;
            line-height: 1.5;
            color: #334155;
            background: #fdfdfd;
            max-height: 400px;
            overflow-y: auto;
        }
        .att-image-preview-container .preview-loading {
            padding: 20px;
            text-align: center;
            color: #64748b;
            font-size: 12px;
        }
        .att-image-preview-container .preview-error {
            padding: 15px;
            color: #ef4444;
            font-size: 12px;
            text-align: center;
        }
        .att-preview-close {
            position: absolute;
            top: -12px;
            right: -12px;
            width: 30px;
            height: 30px;
            background: #1e293b;
            color: #fff;
            border: 2px solid #fff;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10001;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 0;
            line-height: 1;
        }
        @media (max-width: 768px) {
            .att-preview-close { display: flex; }
            .att-image-preview-container {
                width: 94vw !important;
                max-width: 94vw !important;
                padding: 6px;
            }
            .att-image-preview-container img {
                max-height: 70vh;
            }
        }

        .preview-hint {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f0f7ff;
            color: #0369a1;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            margin-bottom: 12px;
            border: 1px solid #bae6fd;
        }
        .preview-hint i { font-size: 1rem; }
        .preview-hint { transition: opacity 0.8s ease, transform 0.8s ease; }
        /* Botones primarios (Rojo corporativo) */
        .btn-primary { background-color: #ef4444; border-color: #ef4444; color: #fff; }
        .btn-primary:hover, .btn-primary:focus, .btn-primary:active { background-color: #dc2626; border-color: #dc2626; color: #fff; }
        .btn-outline-primary { color: #ef4444; border-color: #ef4444; }
        .btn-outline-primary:hover { background-color: #ef4444; color: #fff; border-color: #ef4444; }
        .text-primary { color: #ef4444 !important; }
        .bg-primary { background-color: #ef4444 !important; }
        a { color: #ef4444; }
        a:hover { color: #dc2626; }

        @media (max-width: 576px) {
            body .container-main {
                padding: 0 10px !important;
                margin: 20px auto !important;
            }
            body .center-wrap {
                max-width: 100% !important;
            }
            body .panel-soft {
                padding: 0 !important;
                background: transparent !important;
                border: none !important;
                box-shadow: none !important;
                backdrop-filter: none !important;
            }
            body.dark-mode .panel-soft {
                background: transparent !important;
                border: none !important;
                box-shadow: none !important;
                backdrop-filter: none !important;
            }
            body .page-header {
                padding: 16px !important;
                margin-bottom: 16px !important;
                border-radius: 12px !important;
            }
            body .card-soft {
                border-radius: 12px !important;
            }
            body .card-soft .head {
                padding: 12px 16px !important;
            }
            body .card-soft .body {
                padding: 14px 10px !important;
            }
            body .reply-card {
                padding: 12px !important;
                border-radius: 12px !important;
            }
            body .ticket-view-entry .entry-row {
                gap: 8px !important;
            }
            body .ticket-view-entry .entry-avatar {
                width: 32px !important;
                height: 32px !important;
            }
            body .ticket-view-entry .entry-avatar-inner {
                font-size: 0.8rem !important;
            }
            body .ticket-view-entry .entry-content {
                padding: 12px 14px !important;
                border-radius: 12px !important;
            }
        }
    </style>
</head>
<body class="<?php echo $isDarkMode ? 'dark-mode' : ''; ?>">
<nav class="navbar navbar-dark topbar" style="position: fixed; top: 0; left: 0; width: 100%; z-index: 1030;">
    <div class="container-fluid">
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
        <a class="navbar-brand profile-brand" href="tickets.php">
            <span class="brand-logo-wrap" aria-hidden="true">
                <img class="brand-logo" src="<?php echo html($companyLogoUrl); ?>" alt="<?php echo html($companyName !== '' ? $companyName : 'Logo'); ?>">
            </span>
        </a>
        <div class="d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="btn btn-outline-light btn-sm user-menu-btn position-relative" type="button" id="notifBellBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Notificaciones" style="width: 34px; height: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center;">
                    <i class="bi bi-bell" style="font-size: 15px;"></i>
                    <span id="notifBellBadge" class="badge bg-danger position-absolute" style="display:none; font-size:.65rem; top: -2px; right: -2px; padding: 3px 5px; border-radius: 50px;">0</span>
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
                <?php if (true): // Siempre disponible para usuarios logueados ?>
            <form method="post" action="toggle_user_dark.php" class="d-inline" style="margin:0" id="clientDarkModeForm">
                <?php csrfField(); ?>
                <input type="hidden" name="dark_mode" value="<?php echo $isDarkMode ? '0' : '1'; ?>">
                <input type="hidden" name="return" value="<?php echo html(basename((string)($_SERVER['PHP_SELF'] ?? 'view-ticket.php')) . (!empty($_SERVER['QUERY_STRING']) ? ('?' . (string)$_SERVER['QUERY_STRING']) : '')); ?>">
                <button type="submit" class="btn btn-outline-light btn-sm user-theme-toggle" id="clientDarkModeBtn" title="Modo oscuro" style="border-radius:999px; font-weight:700; width:34px; height:34px; padding:0; display:inline-flex; align-items:center; justify-content:center;">
                    <i class="bi <?php echo $isDarkMode ? 'bi-sun' : 'bi-moon-stars'; ?> user-theme-toggle-icon" style="font-size:16px;"></i>
                </button>
            </form>
            <?php endif; ?>
            <div class="dropdown">
                <button class="btn btn-outline-light btn-sm dropdown-toggle user-menu-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="uavatar" aria-hidden="true"><?php echo html($navInitials); ?></span>
                    <span class="d-none d-sm-inline"><?php echo html($navUserName); ?></span>
                </button>
                    <style>
                        .profile-dropdown {
                            width: 230px; border-radius: 16px; border: 1px solid rgba(226,232,240,0.95); box-shadow: 0 12px 34px rgba(15, 23, 42, 0.12); padding: 8px; background: #fff;
                        }
                        .profile-dd-item {
                            border-radius: 10px; padding: 8px 12px; font-weight: 600; color: #334155; margin-bottom: 2px; transition: all .15s ease;
                        }
                        .profile-dd-item:hover { background: #f8fafc; color: #0f172a; }
                        .profile-dd-icon {
                            width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.1rem;
                        }
                        .profile-dd-icon-default { background: #f1f5f9; color: #64748b; }
                        .profile-dd-icon-success { background: rgba(16, 185, 129, 0.12); color: #10b981; }
                        .profile-dd-danger { color: #ef4444; }
                        .profile-dd-danger:hover { background: rgba(239, 68, 68, 0.08); color: #ef4444; }
                        .profile-dd-icon-danger { background: rgba(239, 68, 68, 0.12); color: #ef4444; }
                        .profile-dd-divider { border-color: #f1f5f9; opacity: 1; margin: 8px 0; }
                        
                        body.dark-mode .profile-dropdown { background: #000000; border-color: #2a2a2a; box-shadow: 0 12px 34px rgba(0, 0, 0, 0.5); }
                        body.dark-mode .profile-dd-item { color: #cbd5e1; }
                        body.dark-mode .profile-dd-item:hover { background: #000000; color: #f8fafc; }
                        body.dark-mode .profile-dd-icon-default { background: rgba(255, 255, 255, 0.08); color: #94a3b8; }
                        body.dark-mode .profile-dd-icon-success { background: rgba(16, 185, 129, 0.15); color: #10b981; }
                        body.dark-mode .profile-dd-danger { color: #ef4444; }
                        body.dark-mode .profile-dd-danger:hover { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
                        body.dark-mode .profile-dd-icon-danger { background: rgba(239, 68, 68, 0.15); }
                        body.dark-mode .profile-dd-divider { border-color: #2a2a2a; }
                    </style>
                    <ul class="dropdown-menu dropdown-menu-end profile-dropdown">
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-3 profile-dd-item" href="tickets.php">
                                <div class="profile-dd-icon profile-dd-icon-default"><i class="bi bi-inboxes"></i></div> Mis Tickets
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-3 profile-dd-item" href="open.php" <?php if (!empty($sigBlockPortal)): ?> onclick="window.showSigToast && window.showSigToast(); return false;" <?php endif; ?>>
                                <div class="profile-dd-icon profile-dd-icon-success"><i class="bi bi-plus-circle"></i></div> Crear Ticket
                            </a>
                        </li>
                        <li><hr class="dropdown-divider profile-dd-divider"></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-3 profile-dd-item" href="profile.php">
                                <div class="profile-dd-icon profile-dd-icon-default"><i class="bi bi-person"></i></div> Mi perfil
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-3 profile-dd-item profile-dd-danger" href="logout.php">
                                <div class="profile-dd-icon profile-dd-icon-danger"><i class="bi bi-box-arrow-right"></i></div> Cerrar sesión
                            </a>
                        </li>
                    </ul>
            </div>
        </div>
    </div>
</nav>

<div class="container-main">
    <div class="center-wrap">
        <div class="panel-soft">
            <?php if (($_GET['msg'] ?? '') === 'approved'): ?>
                <style>
                    #flash-msg-approved {
                        border-radius: 12px; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534;
                    }
                    #flash-msg-approved i { color: #16a34a; }
                    body.dark-mode #flash-msg-approved {
                        background: rgba(22, 101, 52, 0.15); border-color: rgba(22, 101, 52, 0.4); color: #86efac;
                    }
                    body.dark-mode #flash-msg-approved i { color: #4ade80; }
                </style>
                <div class="alert d-flex align-items-center mb-4" role="alert" id="flash-msg-approved">
                    <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                    <div>
                        <strong style="display: block; font-size: 1.05rem; font-weight: 800; margin-bottom: 2px;">¡Acción completada!</strong> 
                        <span style="font-size: 0.95rem;">La respuesta de revisión ejecutiva se ha procesado correctamente.</span>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
                 <script>
                    (function(){
                        if (window.history.replaceState) {
                            var url = new URL(window.location.href);
                            if (url.searchParams.has('msg')) {
                                url.searchParams.delete('msg');
                                window.history.replaceState({}, document.title, url.pathname + url.search);
                            }
                        }
                        setTimeout(function(){
                            var el = document.getElementById('flash-msg-approved');
                            if(el) {
                                el.style.transition = 'opacity 0.4s ease';
                                el.style.opacity = '0';
                                setTimeout(function(){ if(el.parentNode) el.parentNode.removeChild(el); }, 400);
                            }
                        }, 5000);
                    })();
                </script>
            <?php endif; ?>
            <?php
            $effClientStatus = ticketEffectiveStatusDisplay($t['status_name'] ?? '', $t['status_color'] ?? '', $ticketApprovalStatus);
            $clientDisplayStatusName = (string)($effClientStatus['name'] ?? ($t['status_name'] ?? ''));
            $clientStatusColor = normalizeTicketHexColor((string)($effClientStatus['color'] ?? ($t['status_color'] ?? '')), '#64748b');
            $clientPriorityColor = normalizeTicketHexColor((string)($t['priority_color'] ?? ''), '#64748b');
            $clientTopicName = trim((string)($t['topic_name'] ?? ''));
            if ($clientTopicName === '') {
                $clientTopicName = 'General';
            }
            $clientIsClosed = !empty($t['closed']);
            $clientCreatedAt = formatDate((string)$t['created']);
            $clientUpdatedAt = !empty($t['updated']) ? formatDate((string)$t['updated']) : $clientCreatedAt;
            $clientClosedAt = $clientIsClosed ? formatDate((string)$t['closed']) : '';
            $clientStatusStyle = clientTicketBadgeStyle($clientStatusColor, $isDarkMode);
            $clientPriorityStyle = clientTicketBadgeStyle($clientPriorityColor, $isDarkMode);
            $clientStatusDotStyle = clientTicketBadgeDotStyle($clientStatusColor, $isDarkMode);
            ?>

            <div class="client-ticket-hero">
                <div class="client-ticket-hero__ticket-card">
                    <div class="client-ticket-hero__ticket-accent" aria-hidden="true"></div>
                    <div class="client-ticket-hero__ticket-body">
                        <div class="client-ticket-hero__main">
                            <div class="client-ticket-hero__headline">
                                <span class="client-ticket-hero__number" aria-label="Número <?php echo html($t['ticket_number']); ?>">
                                    <span class="client-ticket-hero__number-mark">#</span><span class="client-ticket-hero__number-val"><?php echo html($t['ticket_number']); ?></span>
                                </span>
                                <h1 class="client-ticket-hero__title"><?php echo html($t['subject']); ?></h1>
                            </div>
                        </div>
                        <div class="client-ticket-hero__actions">
                            <?php if (!empty($user['org_tickets_view'])): ?>
                            <a href="ticket_pdf.php?id=<?php echo (int)$t['id']; ?>" class="client-ticket-hero__back" target="_blank" style="margin-right: 8px;">
                                <i class="bi bi-printer"></i> Imprimir / PDF
                            </a>
                            <?php endif; ?>
                            <a href="<?php echo html($viewTicketBackUrl); ?>" class="client-ticket-hero__back">
                                <i class="bi bi-arrow-left"></i> Volver
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($isOrgPeerView && $ticketOwnerName !== ''): ?>
                    <div class="client-ticket-hero__meta">
                        <i class="bi bi-eye"></i> Consulta de ticket de <?php echo html($ticketOwnerName); ?><?php echo empty($user['org_tickets_view']) ? ' · solo lectura' : ''; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="client-ticket-overview">
                <div class="client-ticket-overview__pills d-md-none">
                    <span class="client-ticket-pill" style="<?php echo html($clientStatusStyle); ?>">
                        <span class="client-ticket-pill__dot" style="<?php echo html($clientStatusDotStyle); ?>;"></span>
                        <?php echo html($clientDisplayStatusName); ?>
                    </span>
                    <span class="client-ticket-pill" style="<?php echo html($clientPriorityStyle); ?>">
                        <i class="bi bi-flag-fill"></i>
                        <?php echo html($t['priority_name']); ?>
                    </span>
                </div>

                <div class="client-ticket-overview__grid d-md-none">
                    <div class="client-ticket-overview__grid-item">
                        <div class="client-ticket-field__label"><i class="bi bi-bookmark"></i> Tema</div>
                        <div class="client-ticket-field__value"><?php echo html($clientTopicName); ?></div>
                    </div>
                    <div class="client-ticket-overview__grid-item">
                        <div class="client-ticket-field__label"><i class="bi bi-building"></i> Departamento</div>
                        <div class="client-ticket-field__value"><?php echo html($t['dept_name']); ?></div>
                    </div>
                    <div class="client-ticket-overview__grid-item">
                        <div class="client-ticket-field__label"><i class="bi bi-calendar-event"></i> Creado</div>
                        <div class="client-ticket-field__value client-ticket-field__value--muted"><?php echo html($clientCreatedAt); ?></div>
                    </div>
                    <div class="client-ticket-overview__grid-item">
                        <div class="client-ticket-field__label"><i class="bi bi-clock-history"></i> Actualizado</div>
                        <div class="client-ticket-field__value client-ticket-field__value--muted"><?php echo html($clientUpdatedAt); ?></div>
                    </div>
                    <?php if ($clientIsClosed): ?>
                    <div class="client-ticket-overview__grid-item" style="grid-column: 1 / -1;">
                        <div class="client-ticket-field__label"><i class="bi bi-check-circle"></i> Cerrado</div>
                        <div class="client-ticket-field__value client-ticket-field__value--muted"><?php echo html($clientClosedAt); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="client-ticket-overview__desktop d-none d-md-grid">
                    <div class="client-ticket-overview__col">
                        <div class="client-ticket-field">
                            <div class="client-ticket-field__label"><i class="bi bi-info-circle"></i> Estado</div>
                            <div class="client-ticket-field__value">
                                <span class="client-ticket-field__badge" style="<?php echo html($clientStatusStyle); ?>">
                                    <span class="client-ticket-pill__dot" style="<?php echo html($clientStatusDotStyle); ?>;"></span>
                                    <?php echo html($clientDisplayStatusName); ?>
                                </span>
                            </div>
                        </div>
                        <hr class="client-ticket-field__divider">
                        <div class="client-ticket-field">
                            <div class="client-ticket-field__label"><i class="bi bi-building"></i> Departamento</div>
                            <div class="client-ticket-field__value"><?php echo html($t['dept_name']); ?></div>
                        </div>
                        <?php if ($clientIsClosed): ?>
                        <hr class="client-ticket-field__divider">
                        <div class="client-ticket-field">
                            <div class="client-ticket-field__label"><i class="bi bi-check-circle"></i> Cerrado</div>
                            <div class="client-ticket-field__value client-ticket-field__value--muted"><?php echo html($clientClosedAt); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="client-ticket-overview__col">
                        <div class="client-ticket-field">
                            <div class="client-ticket-field__label"><i class="bi bi-bookmark"></i> Tema de ayuda</div>
                            <div class="client-ticket-field__value"><?php echo html($clientTopicName); ?></div>
                        </div>
                        <hr class="client-ticket-field__divider">
                        <div class="client-ticket-field">
                            <div class="client-ticket-field__label"><i class="bi bi-calendar-event"></i> Fecha de creación</div>
                            <div class="client-ticket-field__value client-ticket-field__value--muted"><?php echo html($clientCreatedAt); ?></div>
                        </div>
                    </div>

                    <div class="client-ticket-overview__col">
                        <div class="client-ticket-field">
                            <div class="client-ticket-field__label"><i class="bi bi-flag"></i> Prioridad</div>
                            <div class="client-ticket-field__value">
                                <span class="client-ticket-field__badge" style="<?php echo html($clientPriorityStyle); ?>">
                                    <i class="bi bi-bar-chart-fill"></i>
                                    <?php echo html($t['priority_name']); ?>
                                </span>
                            </div>
                        </div>
                        <hr class="client-ticket-field__divider">
                        <div class="client-ticket-field">
                            <div class="client-ticket-field__label"><i class="bi bi-clock-history"></i> Última actualización</div>
                            <div class="client-ticket-field__value client-ticket-field__value--muted"><?php echo html($clientUpdatedAt); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="body">
            
            <div class="card-soft mt-4">
                <div class="client-ticket-thread-head">
                    <h5><i class="bi bi-chat-left-text-fill me-2"></i> Hilo del ticket</h5>
                </div>
                <div class="body p-3 p-md-4 client-ticket-thread-body">
                    <div class="thread mt-0">


                <?php if (empty($entries)): ?>
                    <div class="text-muted">Aún no hay mensajes.</div>
                <?php else: ?>
                    <?php foreach ($entries as $e): ?>
                        <?php
                        $isStaff = !empty($e['staff_id']);
                        $author = $isStaff
                            ? 'Soporte técnico'
                            : (trim(($e['user_first'] ?? '') . ' ' . ($e['user_last'] ?? '')) ?: 'Usuario');
                        $cssClass = $isStaff ? 'staff' : 'user';
                        $initials = '';
                        $parts = preg_split('/\s+/', trim($author));
                        $sub1 = function ($str) {
                            if ($str === null) return '';
                            $str = (string) $str;
                            if ($str === '') return '';
                            return function_exists('mb_substr') ? mb_substr($str, 0, 1) : substr($str, 0, 1);
                        };
                        if (!empty($parts[0])) $initials .= $sub1($parts[0]);
                        if (!empty($parts[1])) $initials .= $sub1($parts[1]);
                        $initials = strtoupper($initials ?: 'U');
                        $entryId = (int) $e['id'];
                        ?>
                        <div class="ticket-view-entry <?php echo $cssClass; ?>">
                            <div class="entry-row">
                                <div class="entry-avatar" aria-hidden="true">
                                    <span class="entry-avatar-inner"><?php echo html($initials); ?></span>
                                </div>
                                <div class="entry-bubble-wrapper">
                                    <div class="entry-header d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="author-name"><?php echo html($author); ?></span>
                                            <?php if ($isStaff): ?>
                                                <span class="author-role">Técnico</span>
                                            <?php endif; ?>
                                            

                                        </div>
                                    </div>
                                    
                                    <div class="entry-content">
                                        <div class="entry-meta-top">
                                            <?php echo !empty($e['created']) ? formatDate($e['created']) : ''; ?>
                                        </div>

                                        <div class="entry-body"><?php
                                            echo sanitizeRichText((string)($e['body'] ?? ''));
                                        ?></div>

                                        <?php if (!empty($attachmentsByEntry[$entryId])): ?>
                                            <div class="chat-att-list">
                                                <?php foreach ($attachmentsByEntry[$entryId] as $a): ?>
                                                    <?php
                                                        $mime = strtolower((string)($a['mimetype'] ?? ''));
                                                        $filename = strtolower((string)($a['original_filename'] ?? ''));
                                                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                                        $isImage = str_starts_with($mime, 'image/');
                                                        $isVideo = str_starts_with($mime, 'video/') || in_array($ext, ['mp4', 'webm', 'mov', 'mkv']);
                                                        $isPdf = ($mime === 'application/pdf' || $ext === 'pdf');
                                                        $isDocx = ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || $ext === 'docx');
                                                        
                                                        $type = 'unknown';
                                                        $iconClass = 'bi-file-earmark-text text-secondary';
                                                        
                                                        if ($isImage) {
                                                            $type = 'image';
                                                            $iconClass = 'bi-file-earmark-image text-primary';
                                                        } elseif ($isVideo) {
                                                            $type = 'video';
                                                            $iconClass = 'bi-file-earmark-play-fill text-warning';
                                                        } elseif ($isPdf) {
                                                            $type = 'pdf';
                                                            $iconClass = 'bi-filetype-pdf text-danger';
                                                        } elseif ($isDocx) {
                                                            $type = 'docx';
                                                            $iconClass = 'bi-file-word text-info';
                                                        }

                                                        $sParam = $isSignatureLink ? '&s=' . rawurlencode($sigToken) : '';
                                                        $previewUrl = "view-ticket.php?id=" . (int)$tid . "&download=" . (int)$a['id'] . "&inline=1&v=2" . $sParam;
                                                    ?>
                                                    <div class="chat-att-item">
                                                        <div class="chat-att-icon"><i class="bi <?php echo $iconClass; ?>"></i></div>
                                                        <div class="chat-att-info">
                                                            <a href="view-ticket.php?id=<?php echo (int)$t['id']; ?>&download=<?php echo (int)$a['id']; ?><?php echo $sParam; ?>" 
                                                               <?php if ($type !== 'unknown'): ?>
                                                               class="att-preview-trigger att-filename" 
                                                               data-preview-url="<?php echo html($previewUrl); ?>"
                                                               data-preview-type="<?php echo $type; ?>"
                                                               <?php if ($type === 'image' || $type === 'pdf' || $type === 'video'): ?>
                                                               data-mobile-inline="1"
                                                               <?php endif; ?>
                                                               <?php else: ?>
                                                               class="att-filename"
                                                               <?php endif; ?>
                                                               title="<?php echo html($a['original_filename'] ?? 'archivo'); ?>"
                                                            ><?php echo html($a['original_filename'] ?? 'archivo'); ?></a>
                                                            <div class="att-size"><?php echo humanSize($a['size'] ?? 0); ?></div>
                                                        </div>
                                                        <a href="view-ticket.php?id=<?php echo (int)$t['id']; ?>&download=<?php echo (int)$a['id']; ?><?php echo $sParam; ?>" class="chat-att-download" title="Descargar"><i class="bi bi-download"></i></a>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="entry-footer text-end">
                                        <?php if (!$isStaff): ?>
                                            <?php
                                            $entryReadByStaff = !empty($entryReadMap[(int)($e['id'] ?? 0)]['staff']);
                                            echo threadEntryReadReceiptHtml($entryReadByStaff, false);
                                            ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="reply-card" id="reply-section">
                <?php if (!empty($user['org_tickets_view']) && $ticketApprovalStatus === 'pending'): ?>
                    <div class="org-readonly-notice org-readonly-notice--warning <?php echo empty($isOrgPeerView) ? 'mb-4' : ''; ?>" role="status">
                        <div class="org-readonly-notice__main">
                            <div class="org-readonly-notice__icon" aria-hidden="true">
                                <i class="bi bi-shield-lock-fill"></i>
                            </div>
                            <div>
                                <p class="org-readonly-notice__title">Autorización Requerida</p>
                                <p class="org-readonly-notice__text">
                                    Este ticket requiere su revisión y aprobación para proceder.
                                </p>
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap mt-3 mt-sm-0">
                            <?php if (!$quoteAlreadySent): ?>
                            <form method="post" style="margin: 0; display: inline-flex;" id="form-aprob-cotizacion">
                                <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                                <input type="hidden" name="action" value="cotizacion">
                                <button type="button" class="btn btn-sm btn-approval-warn" onclick="showApprovalModal('form-aprob-cotizacion', 'Solicitar Cotización', '¿Confirma que desea solicitar la Cotización?', 'btn-approval-warn', 'bi-file-earmark-text')"><i class="bi bi-file-earmark-text me-1"></i>Cotización</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" style="margin: 0; display: inline-flex;" id="form-aprob-aprobado" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                                <input type="hidden" name="action" value="aprobado">
                                <button type="button" class="btn btn-sm btn-approval-success" onclick="showApprovalModal('form-aprob-aprobado', 'Aprobar Solicitud', '¿Confirma que desea APROBAR esta solicitud?', 'btn-approval-success', 'bi-check-circle-fill')"><i class="bi bi-check-circle-fill me-1"></i>Aprobado</button>
                            </form>
                            <form method="post" style="margin: 0; display: inline-flex;" id="form-aprob-rechazado">
                                <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                                <input type="hidden" name="action" value="rechazado">
                                <button type="button" class="btn btn-sm btn-approval-danger" onclick="showApprovalModal('form-aprob-rechazado', 'Rechazar Solicitud', '¿Confirma que desea RECHAZAR esta solicitud? Esta acción es definitiva.', 'btn-approval-danger', 'bi-x-circle-fill')"><i class="bi bi-x-circle-fill me-1"></i>Rechazado</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                 <?php if (!empty($isOrgPeerView) && empty($user['org_tickets_view'])): ?>
                    <?php
                    $orgReadonlyOwner = $ticketOwnerName !== '' ? $ticketOwnerName : 'otro usuario';
                    ?>
                    <?php if (!empty($user['org_tickets_view']) && in_array($ticketApprovalStatus, ['cotizacion', 'aprobado', 'rechazado'])): ?>
                        <?php 
                        $noticeClass = 'org-readonly-notice--success';
                        $noticeIcon = 'bi-check-circle-fill';
                        $noticeTitle = 'Respuesta enviada';
                        if ($ticketApprovalStatus === 'cotizacion') {
                            $noticeClass = 'org-readonly-notice--warning';
                            $noticeIcon = 'bi-file-earmark-text';
                            $noticeTitle = 'Cotización';
                        } elseif ($ticketApprovalStatus === 'rechazado') {
                            $noticeClass = 'org-readonly-notice--danger';
                            $noticeIcon = 'bi-x-circle-fill';
                            $noticeTitle = 'Rechazado';
                        }
                        ?>
                        <div class="org-readonly-notice <?php echo $noticeClass; ?>" role="status">
                            <div class="org-readonly-notice__main">
                                <div class="org-readonly-notice__icon" aria-hidden="true">
                                    <i class="bi <?php echo $noticeIcon; ?>"></i>
                                </div>
                                <div>
                                    <p class="org-readonly-notice__title"><?php echo $noticeTitle; ?></p>
                                    <p class="org-readonly-notice__text">
                                        <?php echo $ticketApprovalStatus === 'cotizacion' ? 'Cotización' : ($ticketApprovalStatus === 'aprobado' ? 'Aprobado' : 'Rechazado'); ?>
                                    </p>
                                </div>
                            </div>
                            <a href="<?php echo html($viewTicketBackUrl); ?>" class="btn btn-sm btn-outline-secondary org-readonly-notice__back">
                                <i class="bi bi-arrow-left me-1"></i>Volver al listado
                            </a>
                        </div>
                    <?php elseif (empty($user['org_tickets_view']) || $ticketApprovalStatus !== 'pending'): ?>
                        <div class="org-readonly-notice" role="status">
                            <div class="org-readonly-notice__main">
                                <div class="org-readonly-notice__icon" aria-hidden="true">
                                    <i class="bi bi-eye"></i>
                                </div>
                                <div>
                                    <p class="org-readonly-notice__title">Solo lectura</p>
                                    <p class="org-readonly-notice__text">
                                        Ticket de <?php echo html($orgReadonlyOwner); ?>
                                    </p>
                                </div>
                            </div>
                            <a href="<?php echo html($viewTicketBackUrl); ?>" class="btn btn-sm btn-outline-secondary org-readonly-notice__back">
                                <i class="bi bi-arrow-left me-1"></i>Volver al listado
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                <h5 class="mb-3">Escriba una respuesta</h5>

                <?php if ($reply_error !== ''): ?>
                    <div class="alert alert-danger mb-3" id="reply-error-alert"><?php echo html($reply_error); ?></div>
                    <script>
                        (function () {
                            try {
                                var el = document.getElementById('reply-error-alert');
                                if (!el) return;
                                setTimeout(function () {
                                    try {
                                        var rect = el.getBoundingClientRect();
                                        var y = window.pageYOffset || document.documentElement.scrollTop || 0;
                                        var top = Math.max(0, Math.floor(y + rect.top - 120));
                                        try { window.scrollTo({ top: top, behavior: 'smooth' }); }
                                        catch (e2) { window.scrollTo(0, top); }
                                    } catch (e3) {}
                                }, 60);
                            } catch (e1) {}
                        })();
                    </script>
                <?php endif; ?>
                <?php if (!empty($reply_success)): ?>
                    <div class="alert alert-success mb-3" id="reply-success-alert">Mensaje enviado correctamente.</div>
                    <script>
                        (function () {
                            try {
                                var el = document.getElementById('reply-success-alert');
                                if (!el) return;
                                setTimeout(function () {
                                    try {
                                        var rect = el.getBoundingClientRect();
                                        var y = window.pageYOffset || document.documentElement.scrollTop || 0;
                                        var top = Math.max(0, Math.floor(y + rect.top - 120));
                                        try { window.scrollTo({ top: top, behavior: 'smooth' }); }
                                        catch (e2) { window.scrollTo(0, top); }
                                    } catch (e3) {}
                                }, 60);
                            } catch (e1) {}
                        })();
                    </script>
                <?php endif; ?>

                <?php if (!empty($t['closed'])): ?>
                    <div class="ticket-closed-banner" role="status" aria-label="Ticket cerrado">
                        <style>
                        .ticket-closed-banner {
                            display: flex;
                            align-items: center;
                            gap: 14px;
                            background: #fff;
                            border: 1px solid #e5e7eb;
                            border-left: 4px solid #dc2626;
                            border-radius: 12px;
                            padding: 14px 18px;
                            margin-bottom: 16px;
                            transition: background 0.25s, border-color 0.25s;
                        }
                        .ticket-closed-banner__icon {
                            flex-shrink: 0;
                            width: 36px;
                            height: 36px;
                            background: #dc2626;
                            border-radius: 10px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: #fff;
                            font-size: 1rem;
                            transition: background 0.25s;
                        }
                        .ticket-closed-banner__title {
                            font-weight: 700;
                            font-size: 0.875rem;
                            color: #111827;
                            margin: 0 0 2px;
                            transition: color 0.25s;
                        }
                        .ticket-closed-banner__sub {
                            font-size: 0.76rem;
                            color: #6b7280;
                            margin: 0;
                            transition: color 0.25s;
                        }
                        body.dark-mode .ticket-closed-banner {
                            background: #0a0a0a;
                            border-color: #1f1f1f;
                            border-left-color: #dc2626;
                        }
                        body.dark-mode .ticket-closed-banner__icon { background: #dc2626; }
                        body.dark-mode .ticket-closed-banner__title { color: #f9fafb; }
                        body.dark-mode .ticket-closed-banner__sub   { color: #6b7280; }
                        </style>


                        <div class="ticket-closed-banner__icon" aria-hidden="true">
                            <i class="bi bi-lock-fill"></i>
                        </div>
                        <div>
                            <p class="ticket-closed-banner__title">Ticket cerrado — sin nuevas respuestas</p>
                            <p class="ticket-closed-banner__sub">
                                <i class="bi bi-calendar-check" aria-hidden="true"></i>
                                <?php echo !empty($t['closed']) ? 'Cerrado el ' . date('d/m/Y \a \l\a\s h:i A', strtotime($t['closed'])) : 'Ticket resuelto'; ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>

                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                        <input type="hidden" name="do" value="reply">

                        <div class="mb-3">
                            <textarea name="body" id="reply_body" class="form-control" rows="6" placeholder="Para ayudarle mejor, sea específico y detallado"><?php echo html($replyBodyPrefill); ?></textarea>
                        </div>

                        <?php 
                            if (!isset($ticketMaxFileMb)) $ticketMaxFileMb = (int)getAppSetting('tickets.ticket_max_file_mb', '10');
                        ?>
                        <div class="attach-zone" id="attach-zone">
                            <input type="file" name="attachments[]" id="attachments" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt,.mp4,.webm,.mov,.mkv">
                            <div class="dz-icon"><i class="bi bi-paperclip"></i></div>
                            <div class="attach-text">Arrastra o <a href="#" id="attach-choose-link">selecciona archivos</a></div>
                            <div class="attach-hint">PDF, JPG, PNG, DOC, Video (Máx. <?php echo $ticketMaxFileMb; ?>MB)</div>
                            <div class="attach-list" id="attach-list"></div>
                        </div>

                        <button type="submit" class="btn btn-primary" id="reply-submit-btn">
                            <span class="btn-label"><i class="bi bi-send"></i> Enviar respuesta</span>
                            <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Enviando…</span>
                        </button>
                    </form>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($ticketClientSignatureUrl !== ''): ?>
                <div class="card-soft mt-3 mb-1">
                    <div class="head py-2">
                        <h6 class="mb-0 text-muted" style="font-size: 0.85rem; font-weight: 800; text-transform: uppercase;"><i class="bi bi-pen-fill"></i> Firma de conformidad</h6>
                    </div>
                    <div class="body py-2 text-center" style="background: #ffffff;">
                        <img src="<?php echo html($ticketClientSignatureUrl); ?>" alt="Firma del cliente" style="max-height: 120px; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.12)); background: #ffffff; padding: 6px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <div class="mt-1 text-muted" style="font-size: 0.75rem;">Documento firmado digitalmente el <?php echo !empty($t['closed']) ? date('d/m/Y h:i A', strtotime($t['closed'])) : '-'; ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<script>
    (function () {
        var zone = document.getElementById('attach-zone');
        var input = document.getElementById('attachments');
        var list = document.getElementById('attach-list');
        var chooseLink = document.getElementById('attach-choose-link');
        if (!zone || !input || !list) return;

        var openPicker = function (e) {
            if (e) {
                try { e.preventDefault(); e.stopPropagation(); } catch (err) {}
            }
            try { input.click(); } catch (err) {}
        };

        zone.addEventListener('click', function (e) {
            if (e.target && e.target.closest && e.target.closest('.dz-preview-remove')) return;
            if (e.target === input) return;
            openPicker(e);
        });

        if (chooseLink) {
            chooseLink.addEventListener('click', openPicker);
        }

        // Drag & Drop
        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            zone.classList.add('dragover');
        });
        zone.addEventListener('dragleave', function() {
            zone.classList.remove('dragover');
        });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('dragover');
            if (e.dataTransfer && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                updateList();
            }
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
                input.value = '';
            }
            updateList();
        }

        function updateList() {
            list.innerHTML = '';
            var maxMb = <?php echo (int)($ticketMaxFileMb ?? 10); ?>;
            var maxSize = maxMb * 1024 * 1024;
            var tooLarge = [];

            if (input.files && input.files.length > 0) {
                var dt = new DataTransfer();
                var validFiles = 0;
                for (var i = 0; i < input.files.length; i++) {
                    var file = input.files[i];
                    if (file.size > maxSize) {
                        tooLarge.push(file.name + ' (' + (file.size / (1024*1024)).toFixed(1) + ' MB)');
                    } else {
                        dt.items.add(file);
                        validFiles++;
                    }
                }

                if (tooLarge.length) {
                    input.files = dt.files;
                    var msg = 'Los siguientes archivos superan el límite de ' + maxMb + 'MB y han sido descartados:\n\n' + tooLarge.join('\n');
                    window.__showCreativePop && window.__showCreativePop(msg, 'Archivo demasiado grande');
                }

                if (input.files.length === 0) return;

                for (var i = 0; i < input.files.length; i++) {
                    var file = input.files[i];
                    var ext = file.name.split('.').pop().toLowerCase();
                    var iconHtml = '<i class="bi bi-file-earmark-text"></i>';
                    
                    if (['pdf'].includes(ext)) {
                        iconHtml = '<i class="bi bi-file-earmark-pdf-fill" style="color: #ef4444;"></i>';
                    } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                        iconHtml = '<i class="bi bi-file-earmark-image" style="color: #ef4444;"></i>';
                    } else if (['doc', 'docx'].includes(ext)) {
                        iconHtml = '<i class="bi bi-file-earmark-word-fill" style="color: #f87171;"></i>';
                    } else if (['xls', 'xlsx'].includes(ext)) {
                        iconHtml = '<i class="bi bi-file-earmark-excel-fill" style="color: #10b981;"></i>';
                    } else if (['zip', 'rar'].includes(ext)) {
                        iconHtml = '<i class="bi bi-file-earmark-zip-fill" style="color: #f59e0b;"></i>';
                    } else if (['mp4', 'webm', 'mov', 'mkv'].includes(ext)) {
                        iconHtml = '<i class="bi bi-file-earmark-play-fill" style="color: #f59e0b;"></i>';
                    }

                    var card = document.createElement('div');
                    card.className = 'dz-preview-card';
                    card.innerHTML = 
                        '<div class="dz-preview-icon" id="preview-icon-' + i + '">' + iconHtml + '</div>' +
                        '<div class="dz-preview-info">' +
                            '<div class="dz-preview-name" title="' + file.name + '">' + file.name + '</div>' +
                            '<div class="dz-preview-size">' + humanSize(file.size) + '</div>' +
                        '</div>' +
                        '<button type="button" class="dz-preview-remove" data-remove-index="' + i + '" title="Eliminar"><i class="bi bi-x"></i></button>';
                    
                    list.appendChild(card);

                    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                        (function(idx, f) {
                            var reader = new FileReader();
                            reader.onload = function(e) {
                                var iconDiv = document.getElementById('preview-icon-' + idx);
                                if (iconDiv) {
                                    iconDiv.innerHTML = '<img src="' + e.target.result + '" alt="preview">';
                                }
                            };
                            reader.readAsDataURL(f);
                        })(i, file);
                    }
                }
            }
        }

        list.addEventListener('click', function (e) {
            var btn = e.target.closest('.dz-preview-remove');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                removeAt(parseInt(btn.getAttribute('data-remove-index')));
            }
        });

        input.addEventListener('change', updateList);

        var form = document.querySelector('.reply-card form');
        if (form) {
            form.addEventListener('submit', function(e) {
                var phpPostMaxSize = <?php echo getPostMaxSize(); ?>;
                var phpUploadMaxSize = <?php echo getUploadMaxSize(); ?>;
                var totalSize = 0;
                var maxFileMb = <?php echo (int)($ticketMaxFileMb ?? 10); ?>;
                var maxFileBytes = maxFileMb * 1024 * 1024;
                if (maxFileBytes > phpUploadMaxSize) maxFileBytes = phpUploadMaxSize;

                if (input.files) {
                    for (var i = 0; i < input.files.length; i++) {
                        var f = input.files[i];
                        totalSize += f.size;
                        
                        if (f.size > maxFileBytes) {
                            e.preventDefault();
                            var msg = 'El archivo "<strong>' + f.name + '</strong>" es demasiado pesado (' + humanSize(f.size) + '). El límite por archivo es de ' + humanSize(maxFileBytes) + '.';
                            window.__showCreativePop && window.__showCreativePop(msg, 'Imagen muy pesada');
                            return false;
                        }
                    }
                }

                // Margen de seguridad del 5%
                var postLimit = phpPostMaxSize * 0.95;
                if (totalSize > postLimit) {
                    e.preventDefault();
                    var msgTotal = 'El total de los archivos adjuntos (' + humanSize(totalSize) + ') excede el límite permitido por el servidor (' + humanSize(phpPostMaxSize) + '). Por favor, sube menos archivos o archivos más pequeños.';
                    window.__showCreativePop && window.__showCreativePop(msgTotal, 'Límite excedido');
                    return false;
                }

                // Mostrar loading en el botón
                var btn = document.getElementById('reply-submit-btn');
                if (btn) {
                    var label = btn.querySelector('.btn-label');
                    var loader = btn.querySelector('.btn-loading');
                    if (label && loader) {
                        label.classList.add('d-none');
                        loader.classList.remove('d-none');
                        btn.disabled = true;
                    }
                }
            });
        }
    })();
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
            <button type="button" class="creative-pop-btn primary" onclick="window.__hideCreativePop && window.__hideCreativePop(); try{ if(window.jQuery && jQuery('#reply_body').summernote){ jQuery('#reply_body').summernote('focus'); } else { var el=document.getElementById('reply_body'); el && el.focus(); } }catch(e){}">Escribir mensaje</button>
        </div>
    </div>
</div>

<script>
    (function () {
        var overlay = document.getElementById('creativePop');
        var msgEl = document.getElementById('creativePopMsg');
        var titleEl = document.getElementById('creativePopTitle');
        window.__showCreativePop = function (msg, title) {
            if (!overlay || !msgEl) return;
            msgEl.textContent = msg || '';
            if (titleEl) titleEl.textContent = title || 'Atención';
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
        };
        window.__hideCreativePop = function () {
            if (!overlay) return;
            overlay.style.display = 'none';
            overlay.setAttribute('aria-hidden', 'true');
        };
        overlay && overlay.addEventListener('click', function (e) {
            if (e.target === overlay) window.__hideCreativePop();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') window.__hideCreativePop();
        });
    })();

    (function () {
        try {
            if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
        } catch (e) {}

        try {
            var ok = document.getElementById('reply-success-alert');
            if (ok) {
                setTimeout(function () {
                    try {
                        ok.style.transition = 'opacity .25s ease, transform .25s ease';
                        ok.style.opacity = '0';
                        ok.style.transform = 'translateY(-2px)';
                        setTimeout(function () { try { ok.remove(); } catch (e2) { ok.parentNode && ok.parentNode.removeChild(ok); } }, 260);
                    } catch (e3) {}
                }, 2600);
            }
        } catch (e4) {}
    })();
</script>

<script src="scp/js/vendor/jquery-3.6.0.min.js"></script>
<script src="scp/js/vendor/bootstrap-5.3.0.bundle.min.js"></script>
<script src="scp/js/vendor/summernote-lite.min.js"></script>
<script src="scp/js/vendor/summernote-es-ES.min.js"></script>

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
                        +     '<div class="flex-shrink-0" style="width:34px;height:34px;border-radius:12px;background:rgba(239,68,68,.12);display:flex;align-items:center;justify-content:center;color:#ef4444;">'
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
                <div class="form-text">Pega un enlace de YouTube/Vimeo y se insertará en tu respuesta.</div>
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
                <div class="form-text">Selecciona una imagen para insertarla en tu respuesta.</div>
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
                            jQuery('#reply_body').summernote('insertImage', fileOrUrl);
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
                            jQuery('#reply_body').summernote('insertImage', json.url);
                        })
                        .catch(function (err) {
                            window.__showCreativePop && window.__showCreativePop('No se pudo subir la imagen. Intenta con otra o usa Adjuntar archivos.', 'Error al subir imagen');
                            try { console.error(err); } catch (e) {}
                        });
                    });
                }
            }).render();
        };

        jQuery('#reply_body').summernote({
            height: 200,
            lang: 'es-ES',
            placeholder: 'Para ayudarle mejor, sea específico y detallado',
            toolbar: [
                ['style', ['bold', 'italic', 'underline']],
                ['para', ['ul', 'ol']],
                ['insert', ['myImage', 'myVideo']],
                ['view', ['codeview']]
            ],
            buttons: {
                myVideo: myVideoBtn,
                myImage: myImageBtn
            }
        });

        // Validación frontend: solo bloquear si no hay mensaje Y no hay adjuntos
        var form = document.querySelector('.reply-card form');
        var fileInput = document.getElementById('attachments');
        form && form.addEventListener('submit', function (ev) {
            try {
                var hasFiles = fileInput && fileInput.files && fileInput.files.length > 0;
                if (hasFiles) return; // Con adjuntos, el mensaje es opcional

                var isEmpty = false;
                try { isEmpty = jQuery('#reply_body').summernote('isEmpty'); } catch (e) {}
                if (isEmpty) {
                    ev.preventDefault();
                    // Resetear el botón de envío que pudo haber quedado en estado loading
                    var replyBtn = document.getElementById('reply-submit-btn');
                    if (replyBtn) {
                        var lbl = replyBtn.querySelector('.btn-label');
                        var ldr = replyBtn.querySelector('.btn-loading');
                        if (lbl) lbl.classList.remove('d-none');
                        if (ldr) ldr.classList.add('d-none');
                        replyBtn.disabled = false;
                    }
                    window.__showCreativePop && window.__showCreativePop(
                        'El mensaje no puede estar vacío si no adjuntas ningún archivo.',
                        'Falta un mensaje'
                    );
                    return false;
                }
            } catch (e2) {}
        });
    });
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
<script>
    // Image Preview Logic
    (function() {
        var previewContainer = document.createElement('div');
        previewContainer.className = 'att-image-preview-container';
        
        var closeBtn = document.createElement('button');
        closeBtn.className = 'att-preview-close';
        closeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        closeBtn.type = 'button';
        closeBtn.onclick = function(e) {
            e.stopPropagation();
            previewContainer.style.display = 'none';
            activeUrl = '';
        };
        previewContainer.appendChild(closeBtn);

        document.body.appendChild(previewContainer);

        var triggers = document.querySelectorAll('.att-preview-trigger');
        var hideTimeout = null;
        var activeUrl = '';
        var isMobile = window.innerWidth <= 768;

        function showPreview(el, e) {
            clearTimeout(hideTimeout);
            var url = el.getAttribute('data-preview-url');
            var type = el.getAttribute('data-preview-type');
            if (!url) return;
            
            if (activeUrl === url && previewContainer.style.display === 'block') return;

            activeUrl = url;
            previewContainer.innerHTML = '';
            previewContainer.appendChild(closeBtn);
            previewContainer.style.display = 'block';
            
            if (type === 'image') {
                var img = document.createElement('img');
                img.src = url;
                previewContainer.appendChild(img);
            } else if (type === 'pdf') {
                var iframe = document.createElement('iframe');
                iframe.src = url + '#toolbar=0&navpanes=0&scrollbar=1';
                iframe.style.width = isMobile ? '100%' : '500px';
                iframe.style.height = isMobile ? '60vh' : '380px';
                iframe.style.border = 'none';
                iframe.style.borderRadius = '8px';
                if (!isMobile) previewContainer.style.maxWidth = '520px';
                previewContainer.appendChild(iframe);
            } else if (type === 'docx') {
                var loader = document.createElement('div');
                loader.className = 'preview-loading';
                loader.innerHTML = '<div class="spinner-border spinner-border-sm text-primary me-2"></div> Cargando documento...';
                previewContainer.appendChild(loader);
                previewContainer.style.width = isMobile ? '100%' : '380px';

                fetch(url)
                    .then(function(r) { return r.arrayBuffer(); })
                    .then(function(arrayBuffer) {
                        if (activeUrl !== url) return;
                        return mammoth.convertToHtml({arrayBuffer: arrayBuffer});
                    })
                    .then(function(result) {
                        if (activeUrl !== url || !result) return;
                        previewContainer.innerHTML = '';
                        previewContainer.appendChild(closeBtn);
                        var content = document.createElement('div');
                        content.className = 'preview-content-docx';
                        content.innerHTML = result.value;
                        previewContainer.appendChild(content);
                    })
                    .catch(function(err) {
                        if (activeUrl !== url) return;
                        previewContainer.innerHTML = '';
                        previewContainer.appendChild(closeBtn);
                        var error = document.createElement('div');
                        error.className = 'preview-error';
                        error.innerHTML = '<i class="bi bi-exclamation-triangle"></i> No se pudo previsualizar el documento Word.';
                        previewContainer.appendChild(error);
                    });
            } else if (type === 'video') {
                var video = document.createElement('video');
                video.src = url;
                video.controls = true;
                video.autoplay = true;
                video.style.width = '100%';
                video.style.maxHeight = isMobile ? '60vh' : '400px';
                video.style.borderRadius = '8px';
                previewContainer.appendChild(video);
                if (!isMobile) previewContainer.style.maxWidth = '600px';
            }
        }

        function hidePreview() {
            if (isMobile) return;
            hideTimeout = setTimeout(function() {
                previewContainer.style.display = 'none';
                previewContainer.innerHTML = '';
                previewContainer.appendChild(closeBtn);
                previewContainer.style.maxWidth = '400px';
                previewContainer.style.width = '';
                activeUrl = '';
            }, 250);
        }

        triggers.forEach(function(el) {
            el.addEventListener('mouseenter', function(e) {
                if (!isMobile) showPreview(el, e);
            });

            el.addEventListener('mouseleave', function() {
                if (!isMobile) hidePreview();
            });

            var pressTimer;
            el.addEventListener('touchstart', function(e) {
                pressTimer = window.setTimeout(function() {
                    showPreview(el, e);
                    if (navigator.vibrate) navigator.vibrate(40);
                }, 600);
            }, {passive: true});

            el.addEventListener('touchend', function() {
                clearTimeout(pressTimer);
            }, {passive: true});

            el.addEventListener('touchmove', function() {
                clearTimeout(pressTimer);
            }, {passive: true});
        });

        previewContainer.addEventListener('mouseenter', function() {
            if (!isMobile) clearTimeout(hideTimeout);
        });

        previewContainer.addEventListener('mouseleave', function() {
            if (!isMobile) hidePreview();
        });
    })();
</script>

<!-- Modal de Firma Digital (Cliente) -->
<?php if ($isSignatureLink): ?>
<div class="modal fade" id="modalClientSignature" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0" style="border-radius: 24px; box-shadow: 0 25px 80px rgba(15, 23, 42, 0.15); overflow: hidden; background: #ffffff;">
            
            <div class="modal-header border-0" style="padding: 24px 32px 16px;">
                <div class="d-flex align-items-center gap-3 w-100">
                    <div style="width: 46px; height: 46px; border-radius: 14px; background: rgba(239, 68, 68, 0.1); color: #ef4444; display: flex; align-items: center; justify-content: center; flex: 0 0 auto;">
                        <i class="bi bi-pen-fill" style="font-size: 1.3rem;"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="modal-title mb-0" style="font-weight: 800; color: #0f172a; letter-spacing: -0.02em;">Firma de Conformidad</h4>
                        <div style="color: #64748b; font-size: 0.9rem; font-weight: 600;">Ticket #<?php echo html($t['ticket_number']); ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="background-color: #f1f5f9; border-radius: 50%; padding: 12px; margin: 0;"></button>
                </div>
            </div>

            <div class="modal-body" style="padding: 16px 32px 24px;">
                <div style="background: #f8fafc; border-radius: 16px; padding: 16px 20px; border: 1px solid #e2e8f0; margin-bottom: 24px;">
                    <p style="color: #334155; font-size: 0.95rem; line-height: 1.5; margin: 0; font-weight: 500;">
                        Hola <strong style="color: #0f172a; font-weight: 700;"><?php echo html($navUserName); ?></strong>, para dar por solucionado tu requerimiento, te pedimos por favor dibujar tu firma a continuación. <span class="d-md-none text-primary" style="font-weight: 600;"><i class="bi bi-phone-landscape"></i> Gira tu dispositivo para mayor comodidad.</span>
                    </p>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div style="font-weight: 800; color: #0f172a; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">Lienzo de firma</div>
                    <button type="button" id="btnClearSignatureClient" class="btn btn-sm" style="font-weight: 700; color: #ef4444; background: rgba(239, 68, 68, 0.1); border-radius: 8px; padding: 4px 12px; display: flex; align-items: center; gap: 6px; border: none; transition: background 0.2s;">
                        <i class="bi bi-eraser-fill"></i> Limpiar
                    </button>
                </div>
                <div style="border: 2px dashed #cbd5e1; border-radius: 20px; background: #ffffff; overflow: hidden; position: relative; transition: all 0.2s ease; height: 230px; width: 100%; touch-action: none;">
                    <canvas id="clientSignatureCanvas" style="width: 100%; height: 100%; cursor: crosshair; touch-action: none; display: block;"></canvas>
                    <div style="position: absolute; bottom: 12px; left: 0; right: 0; text-align: center; color: #94a3b8; font-size: 0.8rem; pointer-events: none; opacity: 0.6; font-weight: 600;">
                        Dibuja tu firma en este espacio
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0" style="background: #f8fafc; padding: 20px 32px; gap: 12px; border-top: 1px solid #e2e8f0 !important;">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="font-weight: 700; border-radius: 12px; padding: 10px 24px; color: #475569; border: 1px solid #e2e8f0; background: #ffffff;">Cancelar</button>
                <button type="button" id="btnConfirmClientSign" class="btn btn-primary" style="font-weight: 800; border-radius: 12px; padding: 10px 28px; background: #ef4444; border: none; box-shadow: 0 8px 20px rgba(239, 68, 68, 0.25); display: flex; align-items: center; gap: 8px; transition: all 0.2s;">
                    Finalizar Ticket <i class="bi bi-check2-circle" style="font-size: 1.15rem;"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modalEl = document.getElementById('modalClientSignature');
    if (!modalEl) return;
    
    var modal = new bootstrap.Modal(modalEl);
    modal.show();

    var canvas = document.getElementById('clientSignatureCanvas');
    var ctx = canvas.getContext('2d', { willReadFrequently: true });
    var drawing = false;
    var hasDrawn = false;
    var lastX = 0, lastY = 0;

    function resizeCanvas() {
        var rect = canvas.parentElement.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = rect.height;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.lineWidth = 3;
        ctx.strokeStyle = '#000000';
    }

    modalEl.addEventListener('shown.bs.modal', function () {
        resizeCanvas();
    });

    window.addEventListener('resize', function() {
        if (hasDrawn) {
            var data = canvas.toDataURL();
            resizeCanvas();
            var img = new Image();
            img.onload = function() { ctx.drawImage(img, 0, 0, canvas.width, canvas.height); };
            img.src = data;
        } else {
            resizeCanvas();
        }
    });

    function getPos(e) {
        var rect = canvas.getBoundingClientRect();
        var clientX = e.touches ? e.touches[0].clientX : e.clientX;
        var clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: clientX - rect.left,
            y: clientY - rect.top
        };
    }

    function startDraw(e) {
        e.preventDefault();
        drawing = true;
        var pos = getPos(e);
        lastX = pos.x; 
        lastY = pos.y;
    }

    function draw(e) {
        if (!drawing) return;
        e.preventDefault();
        var pos = getPos(e);
        
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        
        lastX = pos.x; 
        lastY = pos.y;
        hasDrawn = true;
    }

    function stopDraw() {
        drawing = false;
    }

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDraw);
    canvas.addEventListener('mouseleave', stopDraw);
    
    canvas.addEventListener('touchstart', startDraw, {passive: false});
    canvas.addEventListener('touchmove', draw, {passive: false});
    canvas.addEventListener('touchend', stopDraw);
    canvas.addEventListener('touchcancel', stopDraw);

    document.getElementById('btnClearSignatureClient').addEventListener('click', function() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasDrawn = false;
    });

    document.getElementById('btnConfirmClientSign').addEventListener('click', function() {
        if (!hasDrawn) {
            alert('Por favor, firme antes de continuar.');
            return;
        }

        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Procesando...';

        var formData = new FormData();
        formData.append('ticket_id', '<?php echo $tid; ?>');
        formData.append('token', '<?php echo $sigToken; ?>');
        formData.append('close_message', '');
        formData.append('signature_data', canvas.toDataURL('image/png'));

        fetch('client-sign-ticket.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'tickets.php?msg=signed';
            } else {
                alert('Error: ' + data.error);
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> Aceptar y Cerrar';
            }
        })
        .catch(err => {
            alert('Error de conexión al procesar la firma.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> Aceptar y Cerrar';
        });
    });
});
</script>
<?php endif; ?>

<script>
// Dark Mode Toggle
(function(){
    var form = document.getElementById('clientDarkModeForm');
    if (!form) return;
    var btn = document.getElementById('clientDarkModeBtn');
    var body = document.body;
    var input = form.querySelector('input[name="dark_mode"]');
    var icon = form.querySelector('.user-theme-toggle-icon');

    function setUi(isDark) {
        if (isDark) body.classList.add('dark-mode');
        else body.classList.remove('dark-mode');
        if (icon) {
            icon.classList.remove('bi-sun', 'bi-moon-stars');
            icon.classList.add(isDark ? 'bi-sun' : 'bi-moon-stars');
        }
        if (input) input.value = isDark ? '0' : '1';
    }

    form.addEventListener('submit', function(e){
        e.preventDefault();
        var isDark = body.classList.contains('dark-mode');
        var nextDark = !isDark;
        setUi(nextDark);
        try {
            if (btn) btn.disabled = true;
            var fd = new FormData(form);
            fd.set('dark_mode', nextDark ? '1' : '0');
            fetch(form.getAttribute('action') || 'toggle_user_dark.php', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(function(r){ return r.json().catch(function(){ return null; }); })
              .then(function(data){
                  if (data && typeof data.dark_mode !== 'undefined') {
                      setUi(String(data.dark_mode) === '1' || data.dark_mode === 1);
                  }
              })
              .catch(function(){
                  setUi(isDark);
              })
              .finally(function(){
                  if (btn) btn.disabled = false;
              });
        } catch (err) {
            setUi(isDark);
            if (btn) btn.disabled = false;
        }
    });
})();
</script>



<!-- Modal de confirmación para aprobación -->
<div class="modal fade" id="approvalConfirmModal" tabindex="-1" aria-labelledby="approvalConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 360px;">
    <div class="modal-content custom-modal-soft" style="border-radius: 20px;">
      <div class="modal-header border-0 pb-0 justify-content-end">
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body text-center pt-0 px-3 px-sm-4 pb-3">
        <div id="approvalModalIconWrap" class="mb-3 mx-auto d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; border-radius: 50%; background: #f8fafc; font-size: 1.5rem;">
            <i id="approvalModalIcon" class="bi"></i>
        </div>
        <h5 class="modal-title mb-2" id="approvalConfirmModalLabel" style="font-weight: 800; color: #0f172a;">
            <span id="approvalModalTitle">Confirmar Acción</span>
        </h5>
        <p id="approvalModalMsg" style="font-size: 0.95rem; color: #64748b; margin: 0; line-height: 1.4;"></p>
        <div id="modalPurchaseOrderContainer" class="mt-3 text-start" style="display: none;">
            <label style="font-weight: 800; font-size: 0.82rem; color: #475569; margin-bottom: 8px; display: block; text-transform: uppercase; letter-spacing: 0.03em;">Orden de Compra (Opcional)</label>
            <div class="po-upload-zone" id="po-upload-zone" style="border: 2px dashed #cbd5e1; border-radius: 12px; padding: 16px; text-align: center; background: #f8fafc; cursor: pointer; transition: all 0.2s ease;">
                <input type="file" id="orden_compra_modal" name="orden_compra" style="display: none;">
                <div class="po-upload-icon" style="font-size: 1.5rem; color: #94a3b8; margin-bottom: 6px;"><i class="bi bi-cloud-arrow-up-fill"></i></div>
                <div class="po-upload-text" id="po-upload-text" style="font-size: 0.85rem; font-weight: 700; color: #64748b;">Subir Orden de Compra</div>
                <div class="po-upload-hint" style="font-size: 0.75rem; color: #94a3b8; margin-top: 2px;">PDF, PNG, JPG o DOC (Idem)</div>
            </div>
            <style>
                body.dark-mode .po-upload-zone {
                    background: #1e293b !important;
                    border-color: #475569 !important;
                }
                body.dark-mode .po-upload-zone:hover {
                    border-color: #f87171 !important;
                }
                .po-upload-zone:hover {
                    border-color: #ef4444;
                    background: #f1f5f9;
                }
            </style>
        </div>
      </div>
      <div class="modal-footer border-0 d-flex flex-nowrap justify-content-center gap-2 pb-3 px-3 px-sm-4">
        <button type="button" class="btn btn-light w-50" style="border-radius: 10px; font-weight: 600; padding: 8px;" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn w-50" id="approvalModalConfirmBtn" style="border-radius: 10px; font-weight: 600; padding: 8px;">Confirmar</button>
      </div>
    </div>
  </div>
</div>

<script>
var currentApprovalFormId = null;
function showApprovalModal(formId, title, msg, btnClass, iconClass) {
    currentApprovalFormId = formId;
    document.getElementById('approvalModalTitle').textContent = title;
    document.getElementById('approvalModalMsg').textContent = msg;
    
    // Show/hide purchase order field based on action
    var poContainer = document.getElementById('modalPurchaseOrderContainer');
    if (poContainer) {
        if (formId === 'form-aprob-aprobado') {
            poContainer.style.display = 'block';
        } else {
            poContainer.style.display = 'none';
            var fileIn = document.getElementById('orden_compra_modal');
            if (fileIn) {
                fileIn.value = '';
                var uploadText = document.getElementById('po-upload-text');
                if (uploadText) uploadText.innerHTML = 'Subir Orden de Compra';
                var uploadZone = document.getElementById('po-upload-zone');
                if (uploadZone) uploadZone.style.borderColor = '#cbd5e1';
            }
        }
    }
    
    var iconEl = document.getElementById('approvalModalIcon');
    iconEl.className = 'bi ' + iconClass;
    
    var iconWrap = document.getElementById('approvalModalIconWrap');
    if (btnClass.includes('warn')) {
        iconWrap.style.background = 'rgba(15, 23, 42, 0.05)';
        iconWrap.style.color = '#0f172a';
        if(document.body.classList.contains('dark-mode')) {
            iconWrap.style.background = 'rgba(255, 255, 255, 0.1)';
            iconWrap.style.color = '#fff';
        }
    } else if (btnClass.includes('success')) {
        iconWrap.style.background = 'rgba(16, 185, 129, 0.1)';
        iconWrap.style.color = '#10b981';
    } else {
        iconWrap.style.background = 'rgba(239, 68, 68, 0.1)';
        iconWrap.style.color = '#ef4444';
    }
    
    var btnEl = document.getElementById('approvalModalConfirmBtn');
    btnEl.className = 'btn ' + btnClass + ' w-50';
    
    var modalEl = document.getElementById('approvalConfirmModal');
    var modal = new bootstrap.Modal(modalEl);
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    var fileInput = document.getElementById('orden_compra_modal');
    var uploadZone = document.getElementById('po-upload-zone');
    var uploadText = document.getElementById('po-upload-text');

    if (uploadZone && fileInput) {
        uploadZone.addEventListener('click', function() {
            fileInput.click();
        });
        
        fileInput.addEventListener('change', function() {
            if (fileInput.files && fileInput.files.length > 0) {
                var filename = fileInput.files[0].name;
                uploadText.innerHTML = '<span style="color: #10b981;"><i class="bi bi-file-earmark-check-fill me-1"></i> ' + filename + '</span>';
                uploadZone.style.borderColor = '#10b981';
            } else {
                uploadText.innerHTML = 'Subir Orden de Compra';
                uploadZone.style.borderColor = '#cbd5e1';
            }
        });
    }
});

document.getElementById('approvalModalConfirmBtn').addEventListener('click', function() {
    if (currentApprovalFormId) {
        var form = document.getElementById(currentApprovalFormId);
        if (form) {
            // If it's the approved form, move the file input into it
            if (currentApprovalFormId === 'form-aprob-aprobado') {
                var fileInput = document.getElementById('orden_compra_modal');
                if (fileInput) {
                    form.appendChild(fileInput);
                }
            }
            form.submit();
        }
    }
});
</script>

</body>
</html>

