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
                    $stmt = $mysqli->prepare("UPDATE tickets SET staff_id = ?, updated = NOW() WHERE id = ?");
                    $stmt->bind_param('ii', $val, $tid);
                    $ok = $stmt->execute();
                    $msg = 'assigned';
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
