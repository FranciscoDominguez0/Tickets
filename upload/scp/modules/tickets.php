<?php
// Módulo: Solicitudes (tickets)
// a=open: abrir nuevo ticket (uid= preselecciona usuario). id=X: vista detallada.

$ticketView = null;
$reply_errors = [];
$reply_success = false;

// Abrir nuevo ticket (tickets.php?a=open&uid=X)
if (isset($_GET['a']) && $_GET['a'] === 'open' && isset($_SESSION['staff_id'])) {
    $open_uid = isset($_GET['uid']) && is_numeric($_GET['uid']) ? (int) $_GET['uid'] : 0;
    $open_errors = [];
    $preSelectedUser = null;
    if ($open_uid > 0) {
        $stmt = $mysqli->prepare("SELECT id, firstname, lastname, email FROM users WHERE id = ?");
        $stmt->bind_param('i', $open_uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $preSelectedUser = $res ? $res->fetch_assoc() : null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'open') {
        if (!isset($_POST['csrf_token']) || !Auth::validateCSRF($_POST['csrf_token'])) {
            $open_errors[] = 'Token de seguridad inválido.';
        } else {
            $user_id = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
            $subject = trim($_POST['subject'] ?? '');
            $body = trim($_POST['body'] ?? '');
            $dept_id = isset($_POST['dept_id']) && is_numeric($_POST['dept_id']) ? (int) $_POST['dept_id'] : 1;
            $priority_id = isset($_POST['priority_id']) && is_numeric($_POST['priority_id']) ? (int) $_POST['priority_id'] : 2;
            $staff_id = isset($_POST['staff_id']) && is_numeric($_POST['staff_id']) ? (int) $_POST['staff_id'] : null;
            if ($staff_id === 0) $staff_id = null;
            if ($user_id <= 0) $open_errors[] = 'Seleccione un usuario.';
            if ($subject === '') $open_errors[] = 'El asunto es obligatorio.';
            if (empty($open_errors)) {
                $ticket_number = 'TKT-' . date('Ymd') . '-' . str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $stmt = $mysqli->prepare("INSERT INTO tickets (ticket_number, user_id, staff_id, dept_id, status_id, priority_id, subject, created) VALUES (?, ?, ?, ?, 1, ?, ?, NOW())");
                // tipos: s (ticket_number), i (user_id), i (staff_id), i (dept_id), i (priority_id), s (subject)
                $stmt->bind_param('siiiis', $ticket_number, $user_id, $staff_id, $dept_id, $priority_id, $subject);
                if ($stmt->execute()) {
                    $new_tid = (int) $mysqli->insert_id;
                    $mysqli->query("INSERT INTO threads (ticket_id, created) VALUES ($new_tid, NOW())");
                    $thread_id = (int) $mysqli->insert_id;
                    if ($body !== '') {
                        $staff_id_entry = (int) ($_SESSION['staff_id'] ?? 0);
                        $stmtE = $mysqli->prepare("INSERT INTO thread_entries (thread_id, staff_id, body, is_internal, created) VALUES (?, ?, ?, 0, NOW())");
                        $stmtE->bind_param('iis', $thread_id, $staff_id_entry, $body);
                        $stmtE->execute();
                    }
                    header('Location: tickets.php?id=' . $new_tid . '&msg=created');
                    exit;
                }
                $open_errors[] = 'Error al crear el ticket.';
            }
        }
    }

    $open_departments = [];
    $r = $mysqli->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
    if ($r) while ($row = $r->fetch_assoc()) $open_departments[] = $row;
    $open_priorities = [];
    $r = $mysqli->query("SELECT id, name FROM priorities ORDER BY level");
    if ($r) while ($row = $r->fetch_assoc()) $open_priorities[] = $row;
    $open_staff = [];
    $r = $mysqli->query("SELECT id, firstname, lastname FROM staff WHERE is_active = 1 ORDER BY firstname, lastname");
    if ($r) while ($row = $r->fetch_assoc()) $open_staff[] = $row;

    // Búsqueda de usuario (como osTicket): no listar todos por defecto
    $open_user_query = trim($_GET['uq'] ?? '');
    $open_user_results = [];
    if ($open_user_query !== '') {
        $term = '%' . $open_user_query . '%';
        $stmt = $mysqli->prepare(
            "SELECT id, firstname, lastname, email, phone
             FROM users
             WHERE firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR phone LIKE ?
             ORDER BY firstname, lastname
             LIMIT 25"
        );
        $stmt->bind_param('ssss', $term, $term, $term, $term);
        $stmt->execute();
        $open_user_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    require __DIR__ . '/ticket-open.inc.php';
    return;
}

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

// Sin id o ticket no encontrado: listado creativo de solicitudes
?>
<?php
$filters = [
    'open' => ['label' => 'Abiertos', 'where' => 't.closed IS NULL'],
    'closed' => ['label' => 'Cerrados', 'where' => 't.closed IS NOT NULL'],
    'mine' => ['label' => 'Asignados a mí', 'where' => 't.staff_id = ?'],
    'unassigned' => ['label' => 'Sin asignar', 'where' => 't.staff_id IS NULL'],
    'all' => ['label' => 'Todos', 'where' => '1=1'],
];
$filterKey = $_GET['filter'] ?? 'open';
if (!isset($filters[$filterKey])) $filterKey = 'open';
$query = trim($_GET['q'] ?? '');

// Contadores rápidos
$countOpen = (int) ($mysqli->query("SELECT COUNT(*) c FROM tickets WHERE closed IS NULL")->fetch_assoc()['c'] ?? 0);
$countClosed = (int) ($mysqli->query("SELECT COUNT(*) c FROM tickets WHERE closed IS NOT NULL")->fetch_assoc()['c'] ?? 0);
$countUnassigned = (int) ($mysqli->query("SELECT COUNT(*) c FROM tickets WHERE staff_id IS NULL AND closed IS NULL")->fetch_assoc()['c'] ?? 0);
$countMine = 0;
if (!empty($_SESSION['staff_id'])) {
    $sid = (int) $_SESSION['staff_id'];
    $countMine = (int) ($mysqli->query("SELECT COUNT(*) c FROM tickets WHERE staff_id = $sid AND closed IS NULL")->fetch_assoc()['c'] ?? 0);
}

// Query listado
$whereClauses = [];
$types = '';
$params = [];
if ($filterKey === 'mine') {
    $whereClauses[] = 't.staff_id = ?';
    $types .= 'i';
    $params[] = (int) ($_SESSION['staff_id'] ?? 0);
} elseif ($filterKey !== 'all') {
    $whereClauses[] = $filters[$filterKey]['where'];
}
if ($query !== '') {
    $like = '%' . $query . '%';
    $whereClauses[] = '(t.ticket_number LIKE ? OR t.subject LIKE ? OR u.email LIKE ? OR CONCAT(u.firstname, " ", u.lastname) LIKE ?)';
    $types .= 'ssss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$sql = "SELECT t.id, t.ticket_number, t.subject, t.created, t.updated, t.closed,
               ts.name AS status_name, ts.color AS status_color,
               p.name AS priority_name, p.color AS priority_color,
               u.firstname AS user_first, u.lastname AS user_last, u.email AS user_email,
               s.firstname AS staff_first, s.lastname AS staff_last
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN staff s ON t.staff_id = s.id
        JOIN ticket_status ts ON t.status_id = ts.id
        JOIN priorities p ON t.priority_id = p.id
        $whereSql
        ORDER BY t.updated DESC
        LIMIT 200";

$tickets = [];
if ($types !== '') {
    $stmt = $mysqli->prepare($sql);
    $bind = [$types];
    foreach ($params as $k => $v) { $bind[] = &$params[$k]; }
    call_user_func_array([$stmt, 'bind_param'], $bind);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $mysqli->query($sql);
}
if ($res) {
    while ($row = $res->fetch_assoc()) $tickets[] = $row;
}
?>
<style>
.tickets-shell { max-width: 1200px; margin: 0 auto; }
.tickets-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #0f172a 100%);
    color: #fff;
    padding: 24px 28px;
    border-radius: 18px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.25);
}
.tickets-hero h1 { font-size: 1.6rem; margin: 0; font-weight: 700; }
.tickets-hero .btn-new {
    background: linear-gradient(135deg, #38bdf8, #2563eb);
    color: #fff;
    padding: 10px 18px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
}
.tickets-metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 20px; }
.tickets-metrics .metric-card {
    background: #fff;
    border-radius: 14px;
    padding: 16px 18px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
}
.metric-card .label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; }
.metric-card .value { font-size: 1.4rem; font-weight: 700; color: #0f172a; margin-top: 6px; }
.tickets-toolbar { display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; margin-bottom: 16px; }
.tickets-filters { display: flex; flex-wrap: wrap; gap: 8px; }
.tickets-filters a {
    padding: 8px 14px;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    text-decoration: none;
    color: #475569;
    font-weight: 600;
    background: #fff;
}
.tickets-filters a.active { background: #2563eb; color: #fff; border-color: #2563eb; }
.tickets-search { min-width: 280px; }
.tickets-search input { border-radius: 10px; }
.tickets-table-wrap {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
    overflow: hidden;
}
.tickets-table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; }
.ticket-row:hover { background: #f8fafc; }
.ticket-number { font-weight: 700; color: #0f172a; }
.ticket-subject { font-weight: 600; color: #1e293b; }
.ticket-meta { font-size: 0.82rem; color: #94a3b8; }
.badge-status { padding: 6px 10px; border-radius: 999px; font-weight: 600; font-size: 0.8rem; }
.badge-priority { padding: 6px 10px; border-radius: 8px; font-weight: 600; font-size: 0.8rem; }
.empty-state { padding: 48px 20px; text-align: center; color: #64748b; }
</style>

<div class="tickets-shell">
    <?php if (isset($_GET['id']) && !$ticketView): ?>
        <div class="alert alert-warning">Ticket no encontrado.</div>
    <?php endif; ?>

    <div class="tickets-hero">
        <div>
            <h1>Solicitudes</h1>
        </div>
        <a href="tickets.php?a=open" class="btn-new"><i class="bi bi-plus-lg me-1"></i> Nuevo Ticket</a>
    </div>

    <div class="tickets-metrics">
        <div class="metric-card"><div class="label">Abiertos</div><div class="value"><?php echo $countOpen; ?></div></div>
        <div class="metric-card"><div class="label">Cerrados</div><div class="value"><?php echo $countClosed; ?></div></div>
        <div class="metric-card"><div class="label">Sin asignar</div><div class="value"><?php echo $countUnassigned; ?></div></div>
        <div class="metric-card"><div class="label">Míos</div><div class="value"><?php echo $countMine; ?></div></div>
    </div>

    <div class="tickets-toolbar">
        <div class="tickets-filters">
            <a href="tickets.php?filter=open" class="<?php echo $filterKey === 'open' ? 'active' : ''; ?>">Abiertos</a>
            <a href="tickets.php?filter=unassigned" class="<?php echo $filterKey === 'unassigned' ? 'active' : ''; ?>">Sin asignar</a>
            <a href="tickets.php?filter=mine" class="<?php echo $filterKey === 'mine' ? 'active' : ''; ?>">Asignados a mí</a>
            <a href="tickets.php?filter=closed" class="<?php echo $filterKey === 'closed' ? 'active' : ''; ?>">Cerrados</a>
            <a href="tickets.php?filter=all" class="<?php echo $filterKey === 'all' ? 'active' : ''; ?>">Todos</a>
        </div>
        <form class="tickets-search" method="get">
            <input type="hidden" name="filter" value="<?php echo html($filterKey); ?>">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Buscar" value="<?php echo html($query); ?>">
            </div>
        </form>
    </div>

    <div class="tickets-table-wrap">
        <table class="table table-hover tickets-table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Ticket</th>
                    <th>Asunto</th>
                    <th>Cliente</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th>Asignado</th>
                    <th>Actualizado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <i class="bi bi-inbox" style="font-size: 2rem; opacity: 0.6;"></i>
                                <div class="mt-2">No hay tickets para esta vista.</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <?php
                        $clientName = trim($t['user_first'] . ' ' . $t['user_last']) ?: $t['user_email'];
                        $staffName = trim($t['staff_first'] . ' ' . $t['staff_last']);
                        $statusColor = $t['status_color'] ?: '#2563eb';
                        $priorityColor = $t['priority_color'] ?: '#94a3b8';
                        ?>
                        <tr class="ticket-row">
                            <td>
                                <div class="ticket-number"><?php echo html($t['ticket_number']); ?></div>
                                <div class="ticket-meta"><?php echo formatDate($t['created']); ?></div>
                            </td>
                            <td>
                                <div class="ticket-subject"><?php echo html($t['subject']); ?></div>
                            </td>
                            <td>
                                <div class="ticket-meta"><?php echo html($clientName); ?></div>
                            </td>
                            <td>
                                <span class="badge-priority" style="background: <?php echo html($priorityColor); ?>22; color: <?php echo html($priorityColor); ?>;">
                                    <?php echo html($t['priority_name']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-status" style="background: <?php echo html($statusColor); ?>22; color: <?php echo html($statusColor); ?>;">
                                    <?php echo html($t['status_name']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="ticket-meta"><?php echo $staffName ?: '— Sin asignar —'; ?></div>
                            </td>
                            <td>
                                <div class="ticket-meta"><?php echo formatDate($t['updated'] ?: $t['created']); ?></div>
                            </td>
                            <td>
                                <a href="tickets.php?id=<?php echo (int) $t['id']; ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
