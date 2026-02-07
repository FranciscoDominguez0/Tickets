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
        if ($body === '') {
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

                // No enviar notificaciones por correo cuando el usuario responde
                // El sistema ya registra la respuesta sin necesidad de enviar correos

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
    <style>
        body { background: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding-top: 56px; }
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
        .ticket-view-entry .entry-body img { max-width: 420px !important; max-height: 260px !important; width: auto !important; height: auto !important; display: block; object-fit: contain; }
        .ticket-view-entry .entry-body iframe { max-width: 420px !important; width: 100% !important; aspect-ratio: 16 / 9; height: auto !important; display: block; }

        .note-editor .note-editable img { max-width: 420px !important; max-height: 260px !important; width: auto !important; height: auto !important; display: block; object-fit: contain; }
        .note-editor .note-editable iframe { max-width: 420px !important; width: 100% !important; aspect-ratio: 16 / 9; height: auto !important; display: block; }

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
<nav class="navbar navbar-dark bg-dark" style="position: fixed; top: 0; left: 0; width: 100%; z-index: 1030;">
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
                                        echo sanitizeRichText((string)($e['body'] ?? ''));
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
                    <div class="alert alert-warning mb-3">Este ticket está cerrado y no admite nuevas respuestas.</div>
                <?php else: ?>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                        <input type="hidden" name="do" value="reply">

                        <div class="mb-3">
                            <textarea name="body" id="reply_body" class="form-control" rows="6" placeholder="Para ayudarle mejor, sea específico y detallado"></textarea>
                        </div>

                        <div class="attach-zone" id="attach-zone">
                            <input type="file" name="attachments[]" id="attachments" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt">
                            <div class="attach-text"><i class="bi bi-paperclip"></i> Adjuntar archivos o <a href="#" id="attach-choose-link">elegirlos</a></div>
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
        var zone = document.getElementById('attach-zone');
        var input = document.getElementById('attachments');
        var list = document.getElementById('attach-list');
        var chooseLink = document.getElementById('attach-choose-link');
        if (!zone || !input || !list) return;

        var openPicker = function () {
            try { input.click(); } catch (e) {}
        };

        zone.addEventListener('click', function (e) {
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
    .creative-pop-overlay{position:fixed; inset:0; background:rgba(15,23,42,.55); display:none; align-items:center; justify-content:center; padding:18px; z-index:2000;}
    .creative-pop{max-width:520px; width:100%; background:linear-gradient(180deg,#ffffff,#f8fafc); border:1px solid #e2e8f0; border-radius:18px; box-shadow:0 24px 80px rgba(0,0,0,.25); overflow:hidden;}
    .creative-pop-head{display:flex; align-items:center; gap:12px; padding:16px 18px; background:linear-gradient(135deg,#0f172a,#1d4ed8); color:#fff;}
    .creative-pop-icon{width:38px; height:38px; border-radius:12px; background:rgba(255,255,255,.18); display:flex; align-items:center; justify-content:center; flex:0 0 auto;}
    .creative-pop-title{font-weight:900; margin:0; font-size:14px; letter-spacing:.02em;}
    .creative-pop-body{padding:16px 18px; color:#0f172a; font-weight:600; line-height:1.35;}
    .creative-pop-actions{display:flex; gap:10px; justify-content:flex-end; padding:0 18px 16px;}
    .creative-pop-btn{border:0; border-radius:12px; padding:10px 14px; font-weight:800; cursor:pointer;}
    .creative-pop-btn.primary{background:#2563eb; color:#fff;}
    .creative-pop-btn.ghost{background:#e2e8f0; color:#0f172a;}
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
            <button type="button" class="creative-pop-btn primary" onclick="window.__hideCreativePop && window.__hideCreativePop()">Escribir mensaje</button>
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
        var form = document.querySelector('.reply-card form');
        if (!form) return;
        var fileInput = document.getElementById('attachments');
        var editor = document.getElementById('reply_body');

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

        jQuery('#reply_body').summernote({
            height: 200,
            lang: 'es-ES',
            placeholder: 'Para ayudarle mejor, sea específico y detallado',
            toolbar: [
                ['style', ['bold', 'italic', 'underline']],
                ['para', ['ul', 'ol']],
                ['insert', ['link', 'picture', 'myVideo']],
                ['view', ['codeview']]
            ],
            buttons: {
                myVideo: myVideoBtn
            },
            callbacks: {
                onImageUpload: function (files) {
                    if (!files || !files.length) return;
                    var data = new FormData();
                    data.append('file', files[0]);
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
                }
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
</body>
</html>
