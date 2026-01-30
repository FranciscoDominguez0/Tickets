<?php
// Módulo: Solicitudes (tickets)
// Vista detallada por id (tickets.php?id=X) con hilo y respuesta; lista si no hay id.

$ticketView = null;
$reply_errors = [];
$reply_success = false;

// Vista de un ticket concreto (tickets.php?id=X)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $tid = (int) $_GET['id'];

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

        // Procesar respuesta o nota interna (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do'])) {
            if (!isset($_POST['csrf_token']) || !Auth::validateCSRF($_POST['csrf_token'])) {
                $reply_errors[] = 'Token de seguridad inválido.';
            } else {
                $body = trim($_POST['body'] ?? '');
                $is_internal = isset($_POST['do']) && $_POST['do'] === 'internal';
                $new_status_id = isset($_POST['status_id']) && is_numeric($_POST['status_id']) ? (int) $_POST['status_id'] : (int) $ticketView['status_id'];
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
                        $uploadDir = dirname(__DIR__, 2) . '/uploads/attachments';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0755, true);
                        }
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'];
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
                                $mime = $files['type'][$i] ?? '';
                                if (!in_array($mime, $allowedTypes)) continue;
                                $orig = $files['name'][$i] ?? 'file';
                                $ext = pathinfo($orig, PATHINFO_EXTENSION) ?: 'bin';
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

        require __DIR__ . '/ticket-view.inc.php';
        return;
    }
}

// Sin id o ticket no encontrado: listado simple o mensaje
?>
<style>
.tickets-list-placeholder { max-width: 900px; margin: 0 auto; padding: 24px; }
.tickets-list-placeholder .card { border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); border: 1px solid #f1f5f9; }
.tickets-list-placeholder .page-title { font-size: 1.75rem; font-weight: 700; color: #0f172a; margin-bottom: 20px; border-left: 4px solid #2563eb; padding-left: 16px; }
.tickets-list-placeholder .btn-back { color: #2563eb; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 16px; }
.tickets-list-placeholder .btn-back:hover { text-decoration: underline; }
</style>
<div class="tickets-list-placeholder">
    <?php if (isset($_GET['id']) && !$ticketView): ?>
        <a href="tickets.php" class="btn-back"><i class="bi bi-arrow-left"></i> Volver a solicitudes</a>
        <div class="alert alert-warning">Ticket no encontrado.</div>
    <?php else: ?>
        <h1 class="page-title">Solicitudes</h1>
        <div class="card p-4">
            <p class="text-muted mb-0">
                <i class="bi bi-ticket-perforated" style="font-size: 2rem; opacity: 0.5;"></i><br>
                Para ver un ticket, ábrelo desde <a href="users.php">Usuarios</a> (perfil del usuario → pestaña Tickets) o desde el panel.
            </p>
        </div>
    <?php endif; ?>
</div>
