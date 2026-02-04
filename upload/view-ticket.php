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
    . "WHERE t.id = ? AND t.user_id = ?\n"
    . "LIMIT 1"
);
$stmt->bind_param('ii', $tid, $uid);
$stmt->execute();
$t = $stmt->get_result()->fetch_assoc();
if (!$t) {
    header('Location: tickets.php');
    exit;
}

// Thread id
$stmt = $mysqli->prepare('SELECT id FROM threads WHERE ticket_id = ?');
$stmt->bind_param('i', $tid);
$stmt->execute();
$threadRow = $stmt->get_result()->fetch_assoc();
$thread_id = (int)($threadRow['id'] ?? 0);

$reply_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'reply') {
    if (!validateCSRF()) {
        $reply_error = 'Token de seguridad inválido';
    } elseif (!empty($t['closed'])) {
        $reply_error = 'Este ticket está cerrado y no admite nuevas respuestas.';
    } else {
        $body = trim($_POST['body'] ?? '');
        if ($body === '') {
            $reply_error = 'El mensaje no puede estar vacío.';
        } elseif ($thread_id <= 0) {
            $reply_error = 'No se encontró el hilo del ticket.';
        } else {
            $stmt = $mysqli->prepare('INSERT INTO thread_entries (thread_id, user_id, body, created) VALUES (?, ?, ?, NOW())');
            $stmt->bind_param('iis', $thread_id, $uid, $body);
            if ($stmt->execute()) {
                $entry_id = (int) $mysqli->insert_id;
                $mysqli->query('UPDATE tickets SET updated = NOW() WHERE id = ' . (int) $tid);

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

                // Notificar por correo a agentes (asignado -> depto -> todos)
                $agents = [];
                $assignedId = (int) ($t['staff_id'] ?? 0);
                if ($assignedId > 0) {
                    $stmtAg = $mysqli->prepare('SELECT id, email, firstname, lastname FROM staff WHERE is_active = 1 AND id = ? AND TRIM(COALESCE(email, "")) != "" LIMIT 1');
                    $stmtAg->bind_param('i', $assignedId);
                    $stmtAg->execute();
                    if ($r = $stmtAg->get_result()->fetch_assoc()) {
                        $agents[] = $r;
                    }
                }
                if (empty($agents)) {
                    $deptName = (string) ($t['dept_name'] ?? 'Soporte');
                    $stmtDeptId = $mysqli->prepare('SELECT dept_id FROM tickets WHERE id = ?');
                    $stmtDeptId->bind_param('i', $tid);
                    $stmtDeptId->execute();
                    $deptRow = $stmtDeptId->get_result()->fetch_assoc();
                    $dept_id = (int) ($deptRow['dept_id'] ?? 0);
                    if ($dept_id > 0) {
                        $stmtAg = $mysqli->prepare('SELECT id, email, firstname, lastname FROM staff WHERE is_active = 1 AND dept_id = ? AND TRIM(COALESCE(email, "")) != "" ORDER BY id');
                        $stmtAg->bind_param('i', $dept_id);
                        $stmtAg->execute();
                        $resAg = $stmtAg->get_result();
                        while ($row = $resAg->fetch_assoc()) {
                            $agents[] = $row;
                        }
                    }
                }
                if (empty($agents)) {
                    $resAll = $mysqli->query('SELECT id, email, firstname, lastname FROM staff WHERE is_active = 1 AND TRIM(COALESCE(email, "")) != "" ORDER BY id');
                    if ($resAll) {
                        while ($row = $resAll->fetch_assoc()) {
                            $agents[] = $row;
                        }
                    }
                }

                if (!empty($agents)) {
                    $ticketNo = (string) ($t['ticket_number'] ?? ('#' . $tid));
                    $subject = '[Respuesta del usuario] ' . $ticketNo . ' - ' . (string) ($t['subject'] ?? 'Ticket');
                    $clientName = trim((string)($user['name'] ?? 'Cliente'));
                    $clientEmail = (string)($user['email'] ?? '');
                    $viewUrl = (defined('APP_URL') ? APP_URL : '') . '/upload/scp/tickets.php?id=' . (int) $tid;
                    $bodyHtml = '<div style="font-family: Segoe UI, sans-serif; max-width: 700px; margin: 0 auto;">'
                        . '<h2 style="color:#1e3a5f; margin: 0 0 8px;">Nueva respuesta del usuario</h2>'
                        . '<p style="color:#64748b; margin: 0 0 12px;">Ticket: <strong>' . html($ticketNo) . '</strong></p>'
                        . '<table style="width:100%; border-collapse: collapse; margin: 12px 0;">'
                        . '<tr><td style="padding:6px 0; border-bottom:1px solid #eee;"><strong>Cliente:</strong></td><td style="padding:6px 0; border-bottom:1px solid #eee;">' . html($clientName) . ' &lt;' . html($clientEmail) . '&gt;</td></tr>'
                        . '<tr><td style="padding:6px 0; border-bottom:1px solid #eee;"><strong>Departamento:</strong></td><td style="padding:6px 0; border-bottom:1px solid #eee;">' . html((string)($t['dept_name'] ?? '')) . '</td></tr>'
                        . '<tr><td style="padding:6px 0;"><strong>Mensaje:</strong></td><td style="padding:6px 0;"></td></tr>'
                        . '</table>'
                        . '<div style="background:#f8fafc; border:1px solid #e2e8f0; padding:14px; border-radius:10px;">' . nl2br(html($body)) . '</div>'
                        . '<p style="margin: 14px 0 0;"><a href="' . html($viewUrl) . '" style="display:inline-block; background:#2563eb; color:#fff; padding:10px 16px; text-decoration:none; border-radius:8px;">Ver ticket</a></p>'
                        . '<p style="color:#94a3b8; font-size:12px; margin-top: 14px;">' . html(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets') . '</p>'
                        . '</div>';
                    $bodyText = "Nueva respuesta del usuario\nTicket: $ticketNo\nCliente: $clientName <$clientEmail>\n\n$body\n\nVer: $viewUrl";

                    $sent = [];
                    foreach ($agents as $ag) {
                        $to = trim((string)($ag['email'] ?? ''));
                        if ($to === '' || isset($sent[$to])) continue;
                        $sent[$to] = true;
                        Mailer::send($to, $subject, $bodyHtml, $bodyText);
                    }
                }

                header('Location: view-ticket.php?id=' . (int) $tid);
                exit;
            }
            $reply_error = 'No se pudo enviar la respuesta.';
        }
    }
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
        . "WHERE a.id = ? AND te.thread_id = ? AND tk.user_id = ?\n"
        . "LIMIT 1"
    );
    $stmt->bind_param('iii', $aid, $thread_id, $uid);
    $stmt->execute();
    $att = $stmt->get_result()->fetch_assoc();
    if (!$att) {
        http_response_code(404);
        exit('Archivo no encontrado');
    }

    $rel = (string) ($att['path'] ?? '');
    $full = __DIR__ . '/' . ltrim($rel, '/');
    if (($rel === '' || !is_file($full)) && $rel !== '') {
        // Compatibilidad con adjuntos subidos antes del fix de ruta (guardados en upload/scp/uploads/attachments)
        $legacy = __DIR__ . '/scp/' . ltrim($rel, '/');
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
        . "WHERE te.thread_id = ? AND (te.is_internal IS NULL OR te.is_internal = 0)\n"
        . "ORDER BY te.created ASC"
    );
    $stmt->bind_param('i', $thread_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $entries[] = $row;
    }

    if (!empty($entries)) {
        $entryIds = array_map(fn($e) => (int)$e['id'], $entries);
        $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
        $types = str_repeat('i', count($entryIds));
        $sql = "SELECT id, thread_entry_id, original_filename, mimetype, size FROM attachments WHERE thread_entry_id IN ($placeholders) ORDER BY id";
        $stmtA = $mysqli->prepare($sql);
        $stmtA->bind_param($types, ...$entryIds);
        $stmtA->execute();
        $resA = $stmtA->get_result();
        while ($a = $resA->fetch_assoc()) {
            $eid = (int) $a['thread_entry_id'];
            if (!isset($attachmentsByEntry[$eid])) $attachmentsByEntry[$eid] = [];
            $attachmentsByEntry[$eid][] = $a;
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .container-main { max-width: 1100px; margin: 40px auto; padding: 0 20px; }
        .page-header {
            background: linear-gradient(135deg, #0f172a, #1d4ed8);
            padding: 26px 28px;
            border-radius: 16px;
            margin-bottom: 18px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.25);
            color: #fff;
        }
        .page-header .sub { color: rgba(255,255,255,0.85); }

        .card-soft { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; overflow: hidden; }
        .card-soft .head { padding: 20px 22px; border-bottom: 1px solid #e2e8f0; }
        .card-soft .body { padding: 24px; }

        .ticket-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; }
        .ticket-meta .label { color: #64748b; font-size: 0.85rem; }
        .ticket-meta .value { color: #0f172a; font-weight: 600; }

        .thread { margin-top: 18px; }

        .ticket-view-entry { margin-bottom: 16px; }
        .ticket-view-entry .entry-row { display: flex; align-items: flex-start; gap: 12px; }
        .ticket-view-entry.user .entry-row { flex-direction: row-reverse; }
        .ticket-view-entry .entry-avatar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #e2e8f0;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        .ticket-view-entry .entry-avatar-inner { font-weight: 800; font-size: 0.9rem; letter-spacing: 0.08em; }
        .ticket-view-entry.staff .entry-avatar { background: #dcfce7; color: #065f46; }
        .ticket-view-entry.user .entry-avatar { background: #dbeafe; color: #1e3a8a; }

        .ticket-view-entry .entry-content {
            max-width: 820px;
            width: 100%;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            padding: 14px 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        }
        .ticket-view-entry.user .entry-content { background: #eff6ff; border-color: #bfdbfe; }
        .ticket-view-entry.staff .entry-content { background: #fff7ed; border-color: #fed7aa; }

        .ticket-view-entry .entry-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 0.85rem;
            color: #475569;
        }
        .ticket-view-entry.user .entry-meta { flex-direction: row-reverse; }
        .ticket-view-entry .entry-meta .author { font-weight: 700; color: #0f172a; }
        .ticket-view-entry .entry-body { color: #0f172a; white-space: pre-wrap; word-break: break-word; }
        .ticket-view-entry .entry-body p { margin: 0 0 0.5em; }
        .ticket-view-entry .entry-body p:last-child { margin-bottom: 0; }

        .entry-footer {
            font-size: 0.78rem;
            color: #94a3b8;
            margin-top: 6px;
            padding-left: 56px;
        }
        .ticket-view-entry.user .entry-footer { text-align: right; padding-left: 0; padding-right: 56px; }

        .att-list { margin-top: 10px; display: flex; flex-direction: column; gap: 6px; }
        .att-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 10px; }
        .att-item a { text-decoration: none; font-weight: 600; color: #2563eb; }
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

        @media (max-width: 760px) { .ticket-meta { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <span class="navbar-brand"><?php echo APP_NAME; ?></span>
        <div>
            <a href="tickets.php" class="btn btn-outline-light btn-sm">Mis Tickets</a>
            <a href="open.php" class="btn btn-outline-light btn-sm">Crear Ticket</a>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
        </div>
    </div>
</nav>

<div class="container-main">
    <div class="page-header">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div>
                <h2 class="mb-1"><?php echo html($t['subject']); ?></h2>
                <div class="sub">Ticket <strong><?php echo html($t['ticket_number']); ?></strong> · Departamento <strong><?php echo html($t['dept_name']); ?></strong></div>
            </div>
            <div class="text-end">
                <div class="mb-2">
                    <span class="badge" style="background-color: <?php echo html($t['status_color']); ?>"><?php echo html($t['status_name']); ?></span>
                </div>
                <a href="tickets.php" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
            </div>
        </div>
    </div>

    <div class="card-soft">
        <div class="head">
            <div class="ticket-meta">
                <div>
                    <div class="label">Correo electrónico:</div>
                    <div class="value"><?php echo html($user['email']); ?></div>
                </div>
                <div>
                    <div class="label">Cliente:</div>
                    <div class="value"><?php echo html($user['name']); ?></div>
                </div>
                <div>
                    <div class="label">Prioridad:</div>
                    <div class="value"><?php echo html($t['priority_name']); ?></div>
                </div>
                <div>
                    <div class="label">Creado:</div>
                    <div class="value"><?php echo !empty($t['created']) ? date('d/m/Y H:i', strtotime($t['created'])) : '-'; ?></div>
                </div>
            </div>
        </div>
        <div class="body">
            <div class="thread">
                <h5 class="mb-3">Hilo del ticket</h5>

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
                                        $b = (string) ($e['body'] ?? '');
                                        if (strpos($b, '<') !== false) {
                                            echo strip_tags($b, '<p><br><strong><em><b><i><u><s><ul><ol><li><a><span>');
                                        } else {
                                            echo nl2br(html($b));
                                        }
                                    ?></div>

                                    <?php if (!empty($attachmentsByEntry[$eid])): ?>
                                        <div class="att-list">
                                            <?php foreach ($attachmentsByEntry[$eid] as $a): ?>
                                                <div class="att-item">
                                                    <div>
                                                        <i class="bi bi-paperclip"></i>
                                                        <a href="view-ticket.php?id=<?php echo (int)$t['id']; ?>&download=<?php echo (int)$a['id']; ?>"><?php echo html($a['original_filename'] ?? 'archivo'); ?></a>
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

            <div class="reply-card">
                <h5 class="mb-3">Escriba una respuesta</h5>

                <?php if ($reply_error !== ''): ?>
                    <div class="alert alert-danger mb-3"><?php echo html($reply_error); ?></div>
                <?php endif; ?>

                <?php if (!empty($t['closed'])): ?>
                    <div class="alert alert-warning mb-0">Este ticket está cerrado.</div>
                <?php else: ?>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                        <input type="hidden" name="do" value="reply">

                        <div class="mb-3">
                            <textarea name="body" class="form-control" rows="6" placeholder="Para ayudarle mejor, sea específico y detallado" required></textarea>
                        </div>

                        <div class="attach-zone" id="attach-zone" onclick="document.getElementById('attachments').click();">
                            <input type="file" name="attachments[]" id="attachments" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt">
                            <div class="attach-text"><i class="bi bi-paperclip"></i> Adjuntar archivos o <a href="#" onclick="document.getElementById('attachments').click(); return false;">elegirlos</a></div>
                            <div class="attach-list" id="attach-list"></div>
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Enviar respuesta</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var input = document.getElementById('attachments');
        var list = document.getElementById('attach-list');
        if (!input || !list) return;

        function humanSize(bytes) {
            if (!bytes) return '0 B';
            var units = ['B', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(1024));
            i = Math.min(i, units.length - 1);
            return (bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
        }

        function updateList() {
            list.innerHTML = '';
            if (!input.files || input.files.length === 0) return;
            for (var i = 0; i < input.files.length; i++) {
                var f = input.files[i];
                var row = document.createElement('div');
                row.className = 'attach-item';
                var name = document.createElement('div');
                name.className = 'name';
                name.textContent = f.name;
                var size = document.createElement('div');
                size.className = 'size';
                size.textContent = humanSize(f.size);
                row.appendChild(name);
                row.appendChild(size);
                list.appendChild(row);
            }
        }

        input.addEventListener('change', updateList);
    })();
</script>
</body>
</html>
