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
        $priority_id = intval($_POST['priority_id'] ?? 2);
        $dept_id = intval($_POST['dept_id'] ?? 1);

        if (empty($subject) || empty($body)) {
            $error = 'Asunto y descripción son requeridos';
        } else {
            // Generar número de ticket
            $ticket_number = 'TKT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Insertar ticket
            $stmt = $mysqli->prepare(
                'INSERT INTO tickets (ticket_number, user_id, dept_id, status_id, priority_id, subject, created)
                 VALUES (?, ?, ?, 1, ?, ?, NOW())'
            );
            $stmt->bind_param('siiis', $ticket_number, $_SESSION['user_id'], $dept_id, $priority_id, $subject);
            
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

                // Notificar por correo a los agentes del departamento (o a todos si no hay del depto)
                $agents = [];
                $stmtAg = $mysqli->prepare('SELECT id, email, firstname, lastname FROM staff WHERE is_active = 1 AND dept_id = ? AND TRIM(COALESCE(email, "")) != "" ORDER BY id');
                $stmtAg->bind_param('i', $dept_id);
                $stmtAg->execute();
                $resAg = $stmtAg->get_result();
                while ($row = $resAg->fetch_assoc()) {
                    $agents[] = $row;
                }
                if (empty($agents)) {
                    $resAll = $mysqli->query('SELECT id, email, firstname, lastname FROM staff WHERE is_active = 1 AND TRIM(COALESCE(email, "")) != "" ORDER BY id');
                    if ($resAll) {
                        while ($row = $resAll->fetch_assoc()) {
                            $agents[] = $row;
                        }
                    }
                }
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
                        <div style="background: #f5f5f5; padding: 12px; border-radius: 6px; margin: 12px 0;">' . nl2br(htmlspecialchars($body)) . '</div>
                        <p><a href="' . htmlspecialchars($viewUrl) . '" style="display: inline-block; background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Ver ticket</a></p>
                        <p style="color: #7f8c8d; font-size: 12px;">' . htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets') . '</p>
                    </div>';
                $emailSubject = '[Nuevo ticket] ' . $ticket_number . ' - ' . $subject;
                $mailSent = 0;
                $mailError = '';
                foreach ($agents as $agent) {
                    $agentEmail = trim($agent['email'] ?? '');
                    if ($agentEmail !== '') {
                        if (Mailer::send($agentEmail, $emailSubject, $bodyHtml)) {
                            $mailSent++;
                        } else {
                            $mailError = Mailer::$lastError;
                        }
                    }
                }
                $success = 'Ticket creado exitosamente! Número: ' . $ticket_number;
                if ($mailSent > 0) {
                    $success .= ' Se envió notificación por correo a ' . $mailSent . ' agente(s).';
                } elseif (!empty($agents) && $mailError !== '') {
                    $success .= ' <strong>No se pudo enviar el correo:</strong> ' . htmlspecialchars($mailError);
                } elseif (empty($agents)) {
                    $success .= ' (No hay agentes con email en el departamento para notificar.)';
                }
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

// Obtener departamentos y prioridades
$departments = [];
$stmt = $mysqli->query('SELECT id, name FROM departments WHERE is_active = 1');
while ($row = $stmt->fetch_assoc()) {
    $departments[] = $row;
}

$priorities = [];
$stmt = $mysqli->query('SELECT id, name, level FROM priorities ORDER BY level ASC');
while ($row = $stmt->fetch_assoc()) {
    $priorities[] = $row;
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
    <style>
        body {
            background: #f1f5f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container-main {
            max-width: 1000px;
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
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
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

                <div class="mb-3">
                    <label for="dept_id" class="form-label">Departamento</label>
                    <select class="form-select" id="dept_id" name="dept_id" required>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo html($dept['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="priority_id" class="form-label">Prioridad</label>
                    <select class="form-select" id="priority_id" name="priority_id" required>
                        <?php foreach ($priorities as $priority): ?>
                            <option value="<?php echo $priority['id']; ?>"><?php echo html($priority['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="body" class="form-label">Descripción</label>
                    <textarea class="form-control" id="body" name="body" rows="8" required></textarea>
                </div>

                <div class="attach-zone" id="attach-zone" onclick="document.getElementById('attachments').click();">
                    <input type="file" name="attachments[]" id="attachments" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt">
                    <div class="attach-text"><i class="bi bi-paperclip"></i> Agregar archivos aquí o <a href="#" onclick="document.getElementById('attachments').click(); return false;">elegirlos</a></div>
                    <div class="attach-list" id="attach-list"></div>
                </div>

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <button type="submit" class="btn btn-primary">Crear Ticket</button>
                <a href="tickets.php" class="btn btn-secondary">Cancelar</a>
            </form>
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
