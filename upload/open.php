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

        if (empty($subject) || empty($body)) {
            $error = 'Asunto y descripción son requeridos';
        } elseif ($hasFiles && $plain === '' && stripos($body, '<img') === false && stripos($body, '<iframe') === false) {
            $error = 'Debes escribir una descripción para enviar archivos. Si solo quieres adjuntar, escribe una breve descripción.';
        } elseif (stripos($body, 'data:image/') !== false) {
            $error = 'Las imágenes pegadas dentro del texto no están soportadas. Adjunta la imagen usando la opción de archivos.';
        } elseif (strlen($body) > 500000) {
            $error = 'La descripción es demasiado grande. Por favor adjunta archivos en vez de pegarlos dentro del texto.';
        } else {
            // Generar número de ticket
            $ticket_number = 'TKT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Verificar si existe la estructura de temas
            $hasTopicCol = false;
            $hasTopicsTable = false;
            $c = $mysqli->query("SHOW COLUMNS FROM tickets LIKE 'topic_id'");
            if ($c && $c->num_rows > 0) $hasTopicCol = true;
            $t = $mysqli->query("SHOW TABLES LIKE 'help_topics'");
            if ($t && $t->num_rows > 0) $hasTopicsTable = true;
            
            // Insertar ticket
            error_log('[tickets] INSERT tickets via upload/open.php uri=' . ($_SERVER['REQUEST_URI'] ?? '') . ' user_id=' . (string)($_SESSION['user_id'] ?? '') . ' dept_id=' . (string)$dept_id . ' topic_id=' . (string)$topic_id);
            
            if ($hasTopicCol && $hasTopicsTable && $topic_id > 0) {
                $stmt = $mysqli->prepare(
                    'INSERT INTO tickets (ticket_number, user_id, dept_id, topic_id, status_id, subject, created)
                     VALUES (?, ?, ?, ?, 1, ?, NOW())'
                );
                $stmt->bind_param('siiis', $ticket_number, $_SESSION['user_id'], $dept_id, $topic_id, $subject);
            } else {
                $stmt = $mysqli->prepare(
                    'INSERT INTO tickets (ticket_number, user_id, dept_id, status_id, subject, created)
                     VALUES (?, ?, ?, 1, ?, NOW())'
                );
                $stmt->bind_param('siis', $ticket_number, $_SESSION['user_id'], $dept_id, $subject);
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
                $adminEmail = defined('ADMIN_NOTIFY_EMAIL') ? trim((string) ADMIN_NOTIFY_EMAIL) : '';
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
            background: #f1f5f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 56px;
        }
        .container-main {
            max-width: 980px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .page-header {
            background: linear-gradient(135deg, #0f172a, #1d4ed8);
            padding: 26px 28px;
            border-radius: 16px;
            margin-bottom: 18px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.25);
            color: #fff;
        }
        .page-header .sub { color: rgba(255,255,255,0.85); }

        .form-card {
            background: #fff;
            padding: 28px;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
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
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark" style="position: fixed; top: 0; left: 0; width: 100%; z-index: 1030;">
        <div class="container-fluid">
            <span class="navbar-brand"><?php echo APP_NAME; ?></span>
            <div>
                <a href="tickets.php" class="btn btn-outline-light btn-sm">Mis Tickets</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container-main">
        <div class="page-header">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div>
                    <h2 class="mb-1">Abrir un nuevo Ticket</h2>
                    <div class="sub">Completa el formulario para crear una nueva solicitud.</div>
                </div>
                <div>
                    <a href="tickets.php" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="section-title">
                <h4><i class="bi bi-chat-left-text"></i> Ticket Details</h4>
                <div class="help">Correo: <strong><?php echo html($user['email']); ?></strong></div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="subject" class="form-label">Asunto</label>
                    <input type="text" class="form-control" id="subject" name="subject" required>
                </div>

                <?php if ($hasTopics): ?>
                <div class="mb-3">
                    <label for="topic_id" class="form-label">Tema</label>
                    <select class="form-select" id="topic_id" name="topic_id" onchange="updateDepartmentFromTopic()">
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
                    <textarea class="form-control" id="body" name="body" rows="8" required></textarea>
                </div>

                <div class="attach-zone" id="attach-zone">
                    <input type="file" name="attachments[]" id="attachments" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt">
                    <div class="attach-text"><i class="bi bi-paperclip"></i> Agregar archivos aquí o <a href="#" id="attach-choose-link">elegirlos</a></div>
                    <div class="attach-list" id="attach-list"></div>
                </div>

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <button type="submit" class="btn btn-primary">Crear Ticket</button>
                <a href="tickets.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
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

            var form = document.querySelector('form[enctype="multipart/form-data"]');
            if (!form) return;
            var fileInput = document.getElementById('attachments');
            var editor = document.getElementById('body');

            var getPlainTextFromHtml = function (html) {
                var tmp = document.createElement('div');
                tmp.innerHTML = html || '';
                return (tmp.textContent || tmp.innerText || '').replace(/\u00A0/g, ' ').trim();
            };

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
                        window.__showCreativePop('Adjuntaste un archivo, pero la descripción está vacía. Escribe una breve descripción para poder enviarlo.', 'Falta una descripción');
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

            // Popup preventivo: adjuntos sin descripción
            var form = document.querySelector('form[enctype="multipart/form-data"]');
            var fileInput = document.getElementById('attachments');
            form && form.addEventListener('submit', function (ev) {
                try {
                    var hasFiles = fileInput && fileInput.files && fileInput.files.length > 0;
                    if (!hasFiles) return;
                    var isEmpty = false;
                    try { isEmpty = jQuery('#body').summernote('isEmpty'); } catch (e) {}
                    if (isEmpty) {
                        ev.preventDefault();
                        window.__showCreativePop && window.__showCreativePop('Adjuntaste un archivo, pero la descripción está vacía. Escribe una breve descripción para poder enviarlo.', 'Falta una descripción');
                        return false;
                    }
                } catch (e2) {}
            });
        });
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
                <button type="button" class="creative-pop-btn primary" onclick="window.__hideCreativePop && window.__hideCreativePop()">Escribir</button>
            </div>
        </div>
    </div>
</body>
</html>
