<?php
/**
 * VER TICKET (USUARIO)
 * Detalle de ticket con hilo y adjuntos
 */

require_once '../config.php';
require_once '../includes/helpers.php';

requireLogin('cliente');

$user = getCurrentUser();
$uid = (int) ($_SESSION['user_id'] ?? 0);
$eid = (int)($_SESSION['empresa_id'] ?? 0);
if ($eid <= 0) $eid = 1;
$tid = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;

if ($tid <= 0) {
    header('Location: tickets.php');
    exit;
}

// Cargar ticket y validar pertenencia
$stmt = $mysqli->prepare(
    "SELECT t.id, t.ticket_number, t.subject, t.created, t.updated, t.closed, t.status_id, t.staff_id,\n"
    . "       ts.name AS status_name, ts.color AS status_color,\n"
    . "       p.name AS priority_name, p.color AS priority_color,\n"
    . "       d.name AS dept_name\n"
    . "FROM tickets t\n"
    . "JOIN ticket_status ts ON t.status_id = ts.id\n"
    . "JOIN priorities p ON t.priority_id = p.id\n"
    . "JOIN departments d ON t.dept_id = d.id\n"
    . "WHERE t.id = ? AND t.user_id = ? AND t.empresa_id = ?\n"
    . "LIMIT 1"
);
$stmt->bind_param('iii', $tid, $uid, $eid);
$stmt->execute();
$t = $stmt->get_result()->fetch_assoc();
if (!$t) {
    header('Location: tickets.php');
    exit;
}

// Thread id
$stmt = $mysqli->prepare('SELECT id FROM threads WHERE ticket_id = ? AND (empresa_id = ? OR empresa_id IS NULL)');
$stmt->bind_param('ii', $tid, $eid);
$stmt->execute();
$threadRow = $stmt->get_result()->fetch_assoc();
$thread_id = (int)($threadRow['id'] ?? 0);

$reply_error = '';
$replyBodyPrefill = '';

$replySessionKey = 'reply_form_' . (int)$tid;
if (isset($_SESSION[$replySessionKey]) && is_array($_SESSION[$replySessionKey])) {
    $reply_error = (string)($_SESSION[$replySessionKey]['error'] ?? '');
    $replyBodyPrefill = (string)($_SESSION[$replySessionKey]['body'] ?? '');
    unset($_SESSION[$replySessionKey]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'reply') {
    if (!validateCSRF()) {
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
        } elseif ($body === '') {
            $reply_error = 'El mensaje no puede estar vacío.';
        } elseif ($hasFiles && $plain === '' && stripos($body, '<img') === false && stripos($body, '<iframe') === false) {
            $reply_error = 'Debes escribir un mensaje para enviar archivos. Si solo quieres adjuntar, escribe una breve descripción.';
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
                $stmtUpdTicket = $mysqli->prepare('UPDATE tickets SET updated = NOW() WHERE id = ? AND user_id = ? AND empresa_id = ?');
                if ($stmtUpdTicket) {
                    $stmtUpdTicket->bind_param('iii', $tid, $uid, $eid);
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
                header('Location: view-ticket.php?id=' . (int) $tid);
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
        header('Location: view-ticket.php?id=' . (int)$tid);
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
        . "WHERE a.id = ? AND te.thread_id = ? AND tk.user_id = ? AND tk.empresa_id = ?\n"
        . "LIMIT 1"
    );
    $stmt->bind_param('iiii', $aid, $thread_id, $uid, $eid);
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
            if ($entryId <= 0) continue;
            if (!isset($attachmentsByEntry[$entryId])) $attachmentsByEntry[$entryId] = [];
            $attachmentsByEntry[$entryId][] = $a;
        }
    }
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
                radial-gradient(900px circle at 0% 0%, rgba(37, 99, 235, 0.06), transparent 52%),
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
            border-left: 6px solid #2563eb;
        }
        .page-header .sub { color: #64748b; font-weight: 700; }

        .card-soft { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; overflow: hidden; }
        .card-soft .head { padding: 20px 22px; border-bottom: 1px solid #e2e8f0; }
        .card-soft .body { padding: 24px; }

        .ticket-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; }
        .ticket-meta .label { color: #64748b; font-size: 0.85rem; }
        .ticket-meta .value { color: #0f172a; font-weight: 600; }

        .thread { margin-top: 18px; }

        .ticket-view-entry { margin-bottom: 12px; }
        .ticket-view-entry .entry-row { display: flex; align-items: flex-start; gap: 10px; }
        .ticket-view-entry.user .entry-row { flex-direction: row-reverse; }
        .ticket-view-entry .entry-avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #e2e8f0;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            box-shadow: 0 3px 10px rgba(0,0,0,0.06);
        }
        .ticket-view-entry .entry-avatar-inner { font-weight: 800; font-size: 0.78rem; letter-spacing: 0.06em; }
        .ticket-view-entry.staff .entry-avatar { background: #dcfce7; color: #065f46; }
        .ticket-view-entry.user .entry-avatar { background: #dbeafe; color: #1e3a8a; }

        .ticket-view-entry .entry-content {
            max-width: 720px;
            width: 100%;
            min-width: 0;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            padding: 10px 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.06);
        }
        .ticket-view-entry.user .entry-content { background: #eff6ff; border-color: #bfdbfe; }
        .ticket-view-entry.staff .entry-content { background: #fff7ed; border-color: #fed7aa; }

        .ticket-view-entry .entry-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 6px;
            font-size: 0.78rem;
            color: #475569;
        }
        .ticket-view-entry.user .entry-meta { flex-direction: row-reverse; }
        .ticket-view-entry .entry-meta .author { font-weight: 700; color: #0f172a; }
        .ticket-view-entry .entry-body { color: #0f172a; white-space: pre-wrap; word-break: break-word; font-size: 0.9rem; line-height: 1.45; }
        .ticket-view-entry .entry-body p { margin: 0 0 0.4em; }
        .ticket-view-entry .entry-body p:last-child { margin-bottom: 0; }
        .ticket-view-entry .entry-body img { max-width: 100% !important; height: auto !important; display: block; object-fit: contain; }
        .ticket-view-entry .entry-body iframe { width: 100% !important; max-width: 100% !important; aspect-ratio: 16 / 9; height: auto !important; display: block; }

            .note-editor .note-editable img { max-width: 100% !important; height: auto !important; display: block; object-fit: contain; }
            .note-editor .note-editable iframe { width: 100% !important; max-width: 100% !important; aspect-ratio: 16 / 9; height: auto !important; display: block; }

            .entry-footer {
                font-size: 0.72rem;
                color: #94a3b8;
                margin-top: 4px;
                padding-left: 46px;
            }
            .ticket-view-entry.user .entry-footer { text-align: right; padding-left: 0; padding-right: 46px; }

            .att-list { margin-top: 10px; display: flex; flex-direction: column; gap: 6px; }
            .att-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 10px; flex-wrap: wrap; }
            .att-item > div:first-child { min-width: 0; }
            .att-item a { text-decoration: none; font-weight: 600; color: #2563eb; display: inline-block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: bottom; }
            .att-item .size { color: #64748b; font-size: 0.85rem; }

        .reply-card { margin-top: 16px; padding: 18px; border-radius: 16px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 4px 24px rgba(0,0,0,0.06); }
        .attach-zone { border: 2px dashed #cbd5e1; background: #f8fafc; border-radius: 12px; padding: 14px; cursor: pointer; margin-bottom: 12px; }
        .attach-zone:hover { border-color: #94a3b8; }
        .attach-zone input[type="file"] { display: none; }
        .attach-text { color: #64748b; font-size: 0.95rem; }
        .attach-list { margin-top: 10px; display: flex; flex-direction: column; gap: 6px; }
        .attach-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 10px; color: #0f172a; }
        .attach-item .name { font-weight: 600; font-size: 0.9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .attach-item .size { color: #64748b; font-size: 0.85rem; flex: 0 0 auto; }

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

        @media (max-width: 760px) {
            .ticket-meta { grid-template-columns: 1fr; }
            .ticket-view-entry .entry-body img { max-height: 260px !important; }
            .att-item a { white-space: normal; overflow-wrap: anywhere; word-break: break-word; }
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
    </style>
</head>
<body>
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
    <div class="center-wrap">
        <div class="panel-soft">
            <div class="page-header" style="margin-top: 0;">
                <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                    <div>
                        <h2 class="mb-1"><?php echo html($t['subject']); ?></h2>
                        <div class="sub">Ticket #<?php echo html($t['ticket_number']); ?> · <?php echo date('d/m/Y H:i', strtotime($t['created'])); ?></div>
                    </div>
                    <div class="text-end">
                        <div class="mb-2">
                            <span class="badge" style="background-color: <?php echo html($t['status_color']); ?>"><?php echo html($t['status_name']); ?></span>
                        </div>
                        <a href="tickets.php" class="btn btn-light btn-sm" style="border-radius: 999px; font-weight: 800;"><i class="bi bi-arrow-left"></i> Volver</a>
                    </div>
                </div>
            </div>

            <div class="card-soft">
                <div class="head">
                    <div class="ticket-meta">
                        <div>
                            <div class="label">Departamento</div>
                            <div class="value"><?php echo html($t['dept_name']); ?></div>
                        </div>
                        <div>
                            <div class="label">Prioridad</div>
                            <div class="value"><?php echo html($t['priority_name']); ?></div>
                        </div>
                        <div>
                            <div class="label">Estado</div>
                            <div class="value"><?php echo html($t['status_name']); ?></div>
                        </div>
                        <div>
                            <div class="label">Creado</div>
                            <div class="value"><?php echo !empty($t['created']) ? date('d/m/Y H:i', strtotime($t['created'])) : '-'; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="body">
            <div class="thread">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <h5 class="mb-0">Hilo del ticket</h5>
                    <div class="preview-hint">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>Tip: <span class="d-none d-md-inline">Pasa el ratón</span><span class="d-md-none">Deja presionado</span> sobre una imagen para verla</span>
                    </div>
                </div>

                <?php if (empty($entries)): ?>
                    <div class="text-muted">Aún no hay mensajes.</div>
                <?php else: ?>
                    <?php foreach ($entries as $e): ?>
                        <?php
                        $isStaff = !empty($e['staff_id']);
                        $author = $isStaff
                            ? (trim(($e['staff_first'] ?? '') . ' ' . ($e['staff_last'] ?? '')) ?: 'Agente')
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
                        $eid = (int) $e['id'];
                        ?>
                        <div class="ticket-view-entry <?php echo $cssClass; ?>">
                            <div class="entry-row">
                                <div class="entry-avatar" aria-hidden="true">
                                    <span class="entry-avatar-inner"><?php echo html($initials); ?></span>
                                </div>
                                <div class="entry-content">
                                    <div class="entry-meta">
                                        <span class="author"><?php echo html($author); ?></span>
                                        <span><?php echo !empty($e['created']) ? date('d/m/Y H:i', strtotime($e['created'])) : ''; ?></span>
                                    </div>
                                    <div class="entry-body"><?php
                                        echo sanitizeRichText((string)($e['body'] ?? ''));
                                    ?></div>

                                    <?php if (!empty($attachmentsByEntry[$eid])): ?>
                                        <div class="att-list">
                                            <?php foreach ($attachmentsByEntry[$eid] as $a): ?>
                                                <?php
                                                    $mime = strtolower((string)($a['mimetype'] ?? ''));
                                                    $filename = strtolower((string)($a['original_filename'] ?? ''));
                                                    $isImage = str_starts_with($mime, 'image/');
                                                    $isPdf = ($mime === 'application/pdf' || str_ends_with($filename, '.pdf'));
                                                    $isDocx = ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || str_ends_with($filename, '.docx'));
                                                    
                                                    $type = 'unknown';
                                                    if ($isImage) $type = 'image';
                                                    elseif ($isPdf) $type = 'pdf';
                                                    elseif ($isDocx) $type = 'docx';

                                                    $previewUrl = "view-ticket.php?id=" . (int)$tid . "&download=" . (int)$a['id'] . "&inline=1";
                                                ?>
                                                <div class="att-item">
                                                    <div>
                                                        <i class="bi bi-paperclip"></i>
                                                        <a href="view-ticket.php?id=<?php echo (int)$t['id']; ?>&download=<?php echo (int)$a['id']; ?>"
                                                           <?php if ($type !== 'unknown'): ?>
                                                           class="att-preview-trigger"
                                                           data-preview-url="<?php echo html($previewUrl); ?>"
                                                           data-preview-type="<?php echo $type; ?>"
                                                           <?php if ($type === 'image' || $type === 'pdf'): ?>
                                                           data-mobile-inline="1"
                                                           <?php endif; ?>
                                                           <?php endif; ?>
                                                        ><?php echo html($a['original_filename'] ?? 'archivo'); ?></a>
                                                    </div>
                                                    <div class="size"><?php echo humanSize($a['size'] ?? 0); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="entry-footer">Creado por <?php echo html($author); ?> <?php echo !empty($e['created']) ? date('d/m/Y H:i', strtotime($e['created'])) : ''; ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="reply-card" id="reply-section">
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
                    <div class="alert alert-warning mb-3">Este ticket está cerrado y no admite nuevas respuestas.</div>
                <?php else: ?>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                        <input type="hidden" name="do" value="reply">

                        <div class="mb-3">
                            <textarea name="body" id="reply_body" class="form-control" rows="6" placeholder="Para ayudarle mejor, sea específico y detallado"><?php echo html($replyBodyPrefill); ?></textarea>
                        </div>

                        <div class="attach-zone" id="attach-zone">
                            <input type="file" name="attachments[]" id="attachments" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt">
                            <div class="attach-text"><i class="bi bi-paperclip"></i> Adjuntar archivos o <a href="#" id="attach-choose-link">elegirlos</a></div>
                            <div class="attach-list" id="attach-list"></div>
                        </div>

                        <button type="submit" class="btn btn-primary" id="reply-submit-btn">
                            <span class="btn-label"><i class="bi bi-send"></i> Enviar respuesta</span>
                            <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Enviando…</span>
                        </button>
                    </form>
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

        var form = document.querySelector('.reply-card form');
        if (!form) return;
        var fileInput = document.getElementById('attachments');
        var editor = document.getElementById('reply_body');
        var submitBtn = document.getElementById('reply-submit-btn');

        var getPlainTextFromHtml = function (html) {
            var tmp = document.createElement('div');
            tmp.innerHTML = html || '';
            return (tmp.textContent || tmp.innerText || '').replace(/\u00A0/g, ' ').trim();
        };

        // Capture-phase listener to ensure it always runs
        form.addEventListener('submit', function (ev) {
            try {
                var hasFiles = fileInput && fileInput.files && fileInput.files.length > 0;
                if (!hasFiles) return;

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
                    ev.preventDefault();
                    window.__showCreativePop && window.__showCreativePop(
                        'Adjuntaste un archivo, pero el mensaje está vacío. Escribe una breve descripción para poder enviarlo.',
                        'Falta un mensaje'
                    );
                    return false;
                }
            } catch (e2) {}
        }, true);
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

        // Popup preventivo: adjuntos sin mensaje
        var form = document.querySelector('.reply-card form');
        var fileInput = document.getElementById('attachments');
        form && form.addEventListener('submit', function (ev) {
            try {
                var hasFiles = fileInput && fileInput.files && fileInput.files.length > 0;
                if (!hasFiles) return;

                var isEmpty = false;
                try { isEmpty = jQuery('#reply_body').summernote('isEmpty'); } catch (e) {}
                if (isEmpty) {
                    ev.preventDefault();
                    window.__showCreativePop && window.__showCreativePop(
                        'Adjuntaste un archivo, pero el mensaje está vacío. Escribe una breve descripción para poder enviarlo.',
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
</body>
</html>
